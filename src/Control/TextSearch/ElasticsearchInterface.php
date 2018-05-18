<?php

namespace Datahouse\Elements\Control\TextSearch;

use ReflectionMethod;
use RuntimeException;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\UrlPointer;
use Datahouse\Elements\Configuration;
use Datahouse\Elements\Control\ContentCollector;
use Datahouse\Elements\Presentation\Exceptions\ConfigurationError;
use Datahouse\Elements\ReFactory;

/**
 * @package Datahouse\Elements\Control\TextSearch
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElasticsearchInterface
{
    protected $factory;
    protected $collector;
    protected $client;

    /* @var array $filterInstances */
    protected $filterInstances;
    /* @var array $refinerInstances */
    protected $refinerInstances;

    /**
     * @param ReFactory        $refactory to get the factory
     * @param ContentCollector $collector used to collect text to index
     */
    public function __construct(
        ReFactory $refactory,
        ContentCollector $collector
    ) {
        $this->factory = $refactory->getFactory();
        $this->collector = $collector;
        $this->client = null;

        // Filter and refiner objects configurable by the application
        $this->filterInstances = [];
        $this->refinerInstances = [];
    }

    /**
     * @return Client|null connection to the elasticsearch client
     * @throws ConfigurationError
     */
    public function getEsClient()
    {
        if (is_null($this->client)) {
            $esHost = getenv('ELASTICSEARCH_PORT_9200_TCP_ADDR');
            $esPort = getenv('ELASTICSEARCH_PORT_9200_TCP_PORT');
            if ($esHost === false || $esPort === false) {
                throw new ConfigurationError("Elasticsearch container not linked");
            }
            $hosts = [$esHost . ':' . $esPort];

            $clientBuilder = ClientBuilder::create();
            $clientBuilder->setHosts($hosts);
            $this->client = $clientBuilder->build();
        }
        return $this->client;
    }

    /**
     * (Re)register an index with Elasticsearch.
     *
     * @param string $indexName       name of the index for elasticsearch
     * @param bool   $forceRecreation of the index
     * @return void
     * @throws ConfigurationError
     */
    private function registerIndex(
        string $indexName,
        bool $forceRecreation = false
    ) {
        $config = $this->factory->getConfiguration();
        $indexDef = $config->searchIndices[$indexName];
        if (!array_key_exists('languages', $indexDef)) {
            throw new ConfigurationError(
                "missing languages in index definition for index $indexName"
            );
        }
        $indices = $this->getEsClient()->indices();
        $exists = boolval($indices->exists(['index' => $indexName]));
        $fields = [];
        if ($exists || $forceRecreation) {
            if ($exists) {
                $result = $indices->delete(['index' => $indexName]);
                assert(array_key_exists('acknowledged', $result));
                assert($result['acknowledged']);
            }
            foreach ($indexDef['languages'] as $language => $analyzer) {
                $fields += [
                    'title_' . $language => [
                        'type' => 'text',
                        'store' => true,
                        'term_vector' => 'with_positions_offsets',
                        'analyzer' => $analyzer
                    ],
                    'contents_' . $language => [
                        'type' => 'text',
                        'store' => true,
                        'term_vector' => 'with_positions_offsets',
                        'analyzer' => $analyzer
                    ]
                ];
            }

            $params = [
                'index' => $indexName,
                'body' => [
                    'settings' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 0
                    ],
                    'mappings' => [
                        'my_type' => [
                            '_source' => ['enabled' => true],
                            'properties' => $fields
                        ]
                    ]
                ]
            ];

            $response = $indices->create($params);
            if (!$response['acknowledged']) {
                throw new RuntimeException("Couldn't create ES index");
            }
        }
    }

    /**
     * Drop and re-create (but not re-populate) all indices.
     * @return void
     */
    public function recreateAllIndices()
    {
        $config = $this->factory->getConfiguration();
        foreach (array_keys($config->searchIndices) as $indexName) {
            $this->registerIndex($indexName, true);
        }
    }

    /**
     * @param Element $element to check
     * @return array of index names this element should be added to
     */
    public function getIndicesPerElement(Element $element)
    {
        $config = $this->factory->getConfiguration();
        $result = [];
        foreach ($config->searchIndices as $indexName => $indexDef) {
            if (array_key_exists('filter-class', $indexDef)
                && array_key_exists('filter-method', $indexDef)
            ) {
                $className = $indexDef['filter-class'];
                $filterFunc = $indexDef['filter-method'];

                if (!array_key_exists($className, $this->filterInstances)) {
                    $filter = $this->factory->createClass($className);
                    $this->filterInstances[$className] = $filter;
                } else {
                    $filter = $this->filterInstances[$className];
                }

                $method = new ReflectionMethod($className, $filterFunc);
                $includeInIndex = $method->invokeArgs($filter, [
                    $indexName,
                    $element
                ]);
                if ($includeInIndex) {
                    $result[] = $indexName;
                }
            }
        }
        return $result;
    }

    /**
     * @param string  $indexName used to search
     * @param Element $element   snippet that matched
     * @return UrlPointer
     * @throws ConfigurationError
     */
    public function getLinkForSnippet(string $indexName, Element $element)
    {
        $config = $this->factory->getConfiguration();
        $indexDef = $config->searchIndices[$indexName];
        if (array_key_exists('refiner-class', $indexDef)) {
            $className = $indexDef['refiner-class'];
            $filterFunc = 'getLinkForSnippet';

            if (!array_key_exists($className, $this->refinerInstances)) {
                $refiner = $this->factory->createClass($className);
                $this->refinerInstances[$className] = $refiner;
            } else {
                $refiner = $this->refinerInstances[$className];
            }

            $method = new ReflectionMethod($className, $filterFunc);
            return $method->invokeArgs($refiner, [$indexName, $element]);
        } else {
            throw new ConfigurationError(
                "snippet " . $element->getId() .
                " has been indexed, but no refiner-class has been defined"
            );
        }
    }

    /**
     * Add or update a single element to the given index.
     *
     * @param string $indexName   to modify
     * @param string $elementId   to add or update
     * @param array  $allTitles   titles (per language)
     * @param array  $allContents contents (per language)
     * @return string
     */
    public function indexPutElement(
        string $indexName,
        string $elementId,
        array $allTitles,
        array $allContents
    ) : string {
        $config = $this->factory->getConfiguration();
        assert(array_key_exists($indexName, $config->searchIndices));
        $indexDef = $config->searchIndices[$indexName];

        $fields = [];
        foreach ($allTitles as $language => $title) {
            if (array_key_exists($language, $indexDef['languages'])) {
                $fields['title_' . $language] = $title;
            }
        }
        foreach ($allContents as $language => $contents) {
            if (array_key_exists($language, $indexDef['languages'])) {
                $fields['contents_' . $language] = $contents;
            }
        }
        $params = [
            'index' => $indexName,
            'type' => 'my_type',
            'id' => $elementId,
            'body' => $fields
        ];

        $response = $this->getEsClient()->index($params);
        if (!in_array($response['result'], ['updated', 'created'])) {
            error_log(
                "ERROR: indexing element $elementId failed (unknown result): " .
                print_r($response, true)
            );
        }
        return $response['result'];
    }

    /**
     * Looks up an index name and its definition given the element id of a
     * search result page.
     *
     * @param string $elementId id of the search result page element
     * @return array of index name and index definition found
     * @throws ConfigurationError
     */
    public function getIndicesBySearchElementId(string $elementId)
    {
        $config = $this->factory->getConfiguration();
        // Determine the indices to use.
        $result = [];
        foreach ($config->searchIndices as $indexName => $indexDef) {
            if (!array_key_exists('result-page', $indexDef)) {
                throw new ConfigurationError(
                    "Definition of index $indexName lacks a result-page"
                );
            }
            if ($indexDef['result-page'] == $elementId) {
                $result[] = [$indexName, $indexDef];
            }
        }
        return $result;
    }

    /**
     * @param string $elementId           search result page element
     * @param array  $languagePreferences from IContentSelector
     * @return array tuple of indexName and chosen language
     */
    public function getBestIndex(string $elementId, array $languagePreferences)
    {
        $indices = $this->getIndicesBySearchElementId($elementId);

        if (count($indices) == 0) {
            throw new RuntimeException(
                "No index defined for search result page " . $elementId
            );
        }

        $bestScore = 0.0;
        $bestIndex = null;
        foreach ($indices as list ($indexName, $indexDef)) {
            foreach (array_keys($indexDef['languages']) as $indexLang) {
                if (array_key_exists($indexLang, $languagePreferences)) {
                    $factor = $languagePreferences[$indexLang];
                } else {
                    $factor = 0.01;
                }
                if ($factor > $bestScore) {
                    $bestScore = $factor;
                    $bestIndex = [$indexName, $indexLang];
                }
            }
        }
        if (is_null($bestIndex)) {
            throw new RuntimeException("no matching index");
        }
        return $bestIndex;
    }

    /**
     * @param string $elementId           element id of the search result page
     * @param array  $languagePreferences map of lang to factor
     * @param string $query               search query
     * @return array
     */
    public function search(
        string $elementId,
        array $languagePreferences,
        string $query
    ) {
        list ($indexName, $language) = $this->getBestIndex(
            $elementId,
            $languagePreferences
        );

        error_log("Using index $indexName");

        $queryFields = ['title_' . $language, 'contents_' . $language];
        $highlightFields = [
            'title_' . $language => [
                'type' => 'fvh',
                'fragment_size' => 120,
                'number_of_fragments' => 3
            ],
            'contents_' . $language => [
                'type' => 'fvh',
                'fragment_size' => 120,
                'number_of_fragments' => 3
            ]
        ];
        $params = [
            'index' => $indexName,
            'type' => 'my_type',
            'body' => [
                'query' => [
                    'multi_match' => [
                        'query' => $query,
                        'fields' => $queryFields
                    ]
                ],
                'highlight' => [
                    'pre_tags' => ['<em>'],
                    'post_tags' => ['</em>'],
                    'fields' => $highlightFields
                ]
            ]
        ];
        return [$indexName, $this->getEsClient()->search($params)];
    }

    /**
     * @return Configuration
     */
    public function getConfiguration() : Configuration
    {
        return $this->factory->getConfiguration();
    }
}
