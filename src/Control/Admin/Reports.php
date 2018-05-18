<?php

namespace Datahouse\Elements\Control\Admin;

use RuntimeException;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementContents;
use Datahouse\Elements\Abstraction\ElementWalker;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Configuration;
use Datahouse\Elements\Constants;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\EleDefRegistry;
use Datahouse\Elements\Control\Exceptions\NoUrlPointer;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\HttpResponse;
use Datahouse\Elements\Control\IUrlResolver;
use Datahouse\Elements\Presentation\IElementDefinition;

/**
 * A preliminary (eh.. primitive) report controller.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2017 by Datahouse AG
 */
class Reports extends AdminPageController
{
    /* @var Configuration $config */
    private $config;
    /* @var IStorageAdapter $adapter */
    private $adapter;
    /* @var EleDefRegistry $eleDefRegistry */
    private $eleDefRegistry;
    /* @var IUrlResolver */
    private $resolver;
    /* @var ElementWalker $walker */
    private $walker;
    /* @var string|null $reqType */
    private $reqType;

    /**
     * @param BaseRouter         $router  invoking this controller
     * @param BaseRequestHandler $handler in charge of the request
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler
    ) {
        parent::__construct($router, $handler);

        // Instantiate ISetupCheck classes provided by the application.
        $factory = $this->handler->getReFactory()->getFactory();
        $this->adapter = $factory->getStorageAdapter();
        $this->config = $factory->getConfiguration();
        $this->eleDefRegistry = $factory->createClass(EleDefRegistry::class);
        $this->resolver = $factory->getUrlResolver();
        $this->walker = new ElementWalker($this->adapter);

        // per element results
        $this->results = [];
        $this->reqType = null;
    }

    /**
     * Enum the methods supported by this controller. Note that this
     * shouldn't include OPTIONS or HEAD.
     *
     * @return string[]
     */
    public function enumAllowedMethods()
    {
        return ['GET'];
    }

    public function analyzeElementField(string $value)
    {
        $result = [];

        // Blatantly copied from the InternalLinkFilter! But note the lack of
        // element|filemeta...
        $pattern = '/<a([^>]*)\s+href\s*\=\s*\"([^#\"]+)((?:#[^\"]*)?)\"/i';

        if (preg_match_all(
            $pattern,
            $value,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        ) === false) {
            throw new RuntimeException("preg_match failed");
        }

        $rootUrl = $this->config->rootUrl;
        $rootUrlLen = strlen($rootUrl);

        foreach (array_reverse($matches) as $match) {
            $link = $match[2][0];
            $linkParts = explode(':', $link, 2);
            if ($linkParts[0] === "element" && count($linkParts) > 1) {
                $linkedId = $linkParts[1];
                assert(is_string($linkedId));
                $linkedElement = $this->adapter->loadElement(strval($linkedId));
                if ($linkedElement) {
                    // a correct link, not of interest
                    //
                    // FIXME: modulo perhaps deleted or not-yet-published stuff?
                } else {
                    $result['missingElements'][] = $linkedId;
                    // invalid link?!?
                }
            } elseif ($linkParts[0] === "filemeta" && count($linkParts) > 1) {
                $linkedId = $linkParts[1];
                $fileMeta = $this->adapter->loadFileMeta($linkedId);
                if ($fileMeta) {
                    $result['linkedBlobs'][] = $fileMeta->getOrigFileName();
                } else {
                    $result['missingBlobs'][] = $linkedId;
                }
            } else {
                // Either an external link or an invalid one.
                if (substr($link, 0, $rootUrlLen) == $rootUrl) {
                    // An internal link
                    $result['danglingLinks'][] = $link;
                } elseif (substr($link, 0, 5) === 'http:' ||
                    substr($link, 0, 6) === 'https:' ||
                    substr($link, 0, 7) === 'mailto:'
                ) {
                    $result['externalLinks'][] = $link;
                } else {
                    $result['danglingLinks'][] = $link;
                }
            }
        }

        $pattern = '/<img([^>]*)\s+src\s*\=\s*\"filemeta\:([^#\"]+)\"/i';

        if (preg_match_all(
            $pattern,
            $value,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        ) === false) {
            throw new RuntimeException("preg_match failed");
        }

        foreach ($matches as $match) {
            $linkedId = $match[2][0];
            $fileMeta = $this->adapter->loadFileMeta($linkedId);
            if ($fileMeta) {
                $result['linkedImages'][] = $fileMeta->getOrigFileName();
            } else {
                $result['missingImages'][] = $linkedId;
            }
        }

        $filteredResult = [];
        if (array_key_exists($this->reqType, $result)) {
            $filteredResult[$this->reqType] = $result[$this->reqType];
        }
        return $filteredResult;
    }

    /**
     * @param IElementDefinition $eleDef for the given $ec
     * @param ElementContents    $ec     to analyze
     * @return array
     */
    public function analyzeElementContents(
        IElementDefinition $eleDef,
        ElementContents $ec
    ) : array {
        return $this->walker->visitSubElementContents(
            $eleDef,
            $ec,
            function (
                string $subName,
                ElementContents $ec,
                IElementDefinition $eleDef
            ) {
                $aggResults = [];
                foreach (array_keys(
                    $eleDef->getKnownContentFields()
                ) as $fieldName) {
                    $fieldResults = $this->analyzeElementField(
                        $ec->{$fieldName} ?? ''
                    );
                    foreach ($fieldResults as $key => $value) {
                        $aggResults[$key] = array_merge(
                            $aggResults[$key] ?? [],
                            $value
                        );
                    }
                }
                return $aggResults;
            }
        );
    }

    /**
     * @return array
     */
    private function collectReportData():array
    {
        $rootElement = $this->adapter->loadElement(
            Constants::ROOT_ELEMENT_ID
        );
        $elementInfo = array_values($this->walker->visitChildrenOf(
            $rootElement,
            function (Element $element) {
                if (!in_array($element->getType(), ['page', 'search'])) {
                    return [null, '', []];
                }
                $process = $this->handler->getChangeProcess();
                $versions = $process->getPublishedVersionNumberByLanguage(
                    $element
                );
                $perElementResults = [];
                foreach ($versions as $language => $vno) {
                    $ev = $element->getVersion($vno);
                    $eleDefName = $ev->getDefinition();
                    $eleDef = $this->eleDefRegistry->getEleDefById($eleDefName);
                    foreach ($ev->getLanguages() as $evLang => $ec) {
                        if ($evLang != $language) {
                            continue;
                        }

                        $ecResults = $this->analyzeElementContents($eleDef, $ec);
                        $aggResults = [];
                        foreach ($ecResults as $subPath => $subResults) {
                            foreach ($subResults as $key => $alues) {
                                $aggResults[$key] = array_unique(array_merge(
                                    $aggResults[$key] ?? [],
                                    $alues
                                ));
                            }
                        }
                        $perElementResults[$language] = $aggResults;
                    }
                }

                // Collect stuff that's common for all languages.
                $valuesToCheck = reset($perElementResults);
                $commonValues = [];
                if (is_array($valuesToCheck)) {
                    foreach ($valuesToCheck as $key => $alues) {
                        assert(is_array($alues));
                        foreach ($alues as $testValue) {
                            $includedInAllLanguages = true;
                            foreach ($perElementResults as $language => $info) {
                                if (!in_array($testValue, $info[$key] ?? [])) {
                                    $includedInAllLanguages = false;
                                }
                            }

                            if ($includedInAllLanguages) {
                                $commonValues[$key][] = $testValue;
                            }
                        }
                    }

                    // Eliminate all values that are common from the individual
                    // lists.
                    foreach (array_keys($perElementResults) as $language) {
                        foreach ($commonValues as $key => $cValues) {
                            $newValues = array_diff(
                                $perElementResults[$language][$key],
                                $cValues
                            );
                            if (count($newValues) == 0) {
                                unset($perElementResults[$language][$key]);
                            } else {
                                $perElementResults[$language][$key] = $newValues;
                            }
                        }
                    }

                    if (count($commonValues) > 0) {
                        $perElementResults['all'] = $commonValues;
                    }
                }

                $displayName = $element->getDisplayName();
                try {
                    $urlp = $this->resolver->getLinkForElement($element);
                    $id = rawurldecode($urlp->getUrl());
                } catch (NoUrlPointer $e) {
                    $id = $element->getId();
                }
                return [$id, $displayName, $perElementResults];
            }
        ));
        $elementInfo = array_filter($elementInfo, function ($entry) {
            $typeCount = 0;
            foreach ($entry[2] as $language => $perLanguageInfo) {
                $typeCount +=
                    count($perLanguageInfo[$this->reqType] ?? []);
            }
            return !is_null($entry[0]) && $typeCount > 0;
        });
        sort($elementInfo);
        return $elementInfo;
    }

    /**
     * @param HttpRequest $request to process
     * @param User        $user    for which to process things
     * @return HttpResponse
     */
    public function processRequest(
        HttpRequest $request,
        User $user
    ) : HttpResponse {
        $this->reqType = $request->getParameter('type');
        if ($this->reqType) {
            $elementInfo = $this->collectReportData();
        } else {
            $elementInfo = [];
        }

        $renderer = $this->getAdminRenderer();
        $renderer->setTemplateData([
            'type' => $this->reqType,
            'elementInfo' => $elementInfo
        ]);

        $response = new HttpResponse();
        $response->setStatusCode(200);
        $response->setRenderer($renderer);
        return $response;
    }
}
