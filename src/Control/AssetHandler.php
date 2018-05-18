<?php

namespace Datahouse\Elements\Control;

use Assetic\Asset\AssetCollection;
use Assetic\Asset\AssetCollectionInterface;
use Assetic\Asset\AssetInterface;
use Assetic\Asset\FileAsset;
use Assetic\Filter\GoogleClosure;
use Assetic\Filter\FilterInterface;
use Assetic\Filter\MinifyCssCompressorFilter;
use Assetic\Filter\Sass;
use Assetic\FilterManager;

/**
 * The asset handler, using assetic underneath. This is where your stylesheet
 * and javascripts get munged.
 *
 * @package Datahouse\Elements\Control
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class AssetHandler
{
    private $combine;

    /** @var FilterManager */
    private $filterManager;

    /** @var AssetCollectionInterface[] $assetCollections */
    protected $assetCollections;

    protected $staticAssets;

    /**
     * AssetHandler constructor - initializes some basic collections used by
     * elements itself.
     */
    public function __construct()
    {
        // Initialize the assetic filter manager
        $this->filterManager = $this->initAsseticFilterManager();
        $this->assetCollections = [];
        $this->staticAssets = [];

        $this->registerBaseCollection();
        $this->registerAdminCollection();

        $this->combine = false;
    }

    /**
     * @param bool $combine enable or disable combination of collections
     * @return void
     */
    public function setCombine(bool $combine)
    {
        $this->combine = $combine;
    }

    /**
     * @return bool true if this handler is supposed to ship a sinlge js or
     * css file per collection
     */
    public function isCombined() : bool
    {
        return $this->combine;
    }

    /**
     * Initialize a basic set of assetic filters to use.
     *
     * @return FilterManager
     */
    private function initAsseticFilterManager()
    {
        $fm = new FilterManager();
        $fm->set('scss', new Sass\ScssFilter());
        $fm->set('minify_css', new MinifyCssCompressorFilter());
        $fm->set('compress_js', new GoogleClosure\CompilerJarFilter(
            // This path matches what we install in the Dockerfile.
            '/usr/local/share/java/closure-compiler.jar'
        ));
        return $fm;
    }

    /**
     * Register a new filter.
     *
     * @param string          $name   under which to register the filter
     * @param FilterInterface $filter the thing to register
     * @return void
     */
    public function registerAsseticFilter(
        string $name,
        FilterInterface $filter
    ) {
        $this->filterManager->set($name, $filter);
    }

    /**
     * Lookup a registered filter.
     *
     * @param string $filterName to lookup
     * @return FilterInterface
     */
    public function lookupAsseticFilter($filterName) : FilterInterface
    {
        return $this->filterManager->get($filterName);
    }

    /**
     * @return void
     */
    private function registerBaseCollection()
    {
        $elementsBaseDir = realpath(__DIR__ . '/../..');
        assert($elementsBaseDir !== false);

        // Check for the vendor directory in two different places: In the
        // normal case, elements is included via composer and lives side
        // to side with other dependencies of the main project:
        $vendorDir = $elementsBaseDir . '/../../../vendor';
        if (!is_dir($vendorDir)) {
            // If that's not the case, this may be a direct checkout with a
            // top level vendor directory populated by composer for elements.
            $vendorDir = $elementsBaseDir . '/vendor';
        }
        assert(is_dir($vendorDir));

        assert(is_file($vendorDir . '/components/jquery/jquery.js'));
        assert(is_file($vendorDir . '/components/jqueryui/jquery-ui.js'));
        assert(is_file($vendorDir . '/components/bootstrap/js/bootstrap.js'));

        $this->registerAssetCollection('base', [
            'css' => [
                'jquery-ui.css' => new FileAsset(
                    $vendorDir . '/components/jqueryui/themes/base/jquery-ui.css'
                ),
                'font-awesome.css' => new FileAsset(
                    $elementsBaseDir . '/css/font-awesome.css'
                ),
                'toastr.min.css' => new FileAsset(
                    $elementsBaseDir . '/css/toastr.min.css'
                ),
                'elements-design.css' => new FileAsset(
                    $elementsBaseDir . '/scss/design.scss',
                    [$this->filterManager->get('scss')]
                )
            ],
            'js' => [
                'jquery.js' => new FileAsset(
                    $vendorDir . '/components/jquery/jquery.js'
                ),
                'jquery-ui.js' => new FileAsset(
                    $vendorDir . '/components/jqueryui/jquery-ui.js'
                ),
                'bootstrap.js' => new FileAsset(
                    $vendorDir . '/components/bootstrap/js/bootstrap.js'
                ),
                'jquery.mjs.nestedSortable.js' => new FileAsset(
                    $elementsBaseDir . '/js/jquery.mjs.nestedSortable.js'
                )
            ]
        ]);
    }

    /**
     * @return void
     */
    private function registerAdminCollection()
    {
        $baseDir = realpath(__DIR__ . '/../..');

        $enabledFroalaPlugins = ['align', 'char_counter', 'code_view',
            'colors', 'emoticons', 'entities', 'file', 'font_family',
            'font_size', 'fullscreen', 'image', 'image_manager',
            'inline_style', 'line_breaker', 'link', 'lists',
            'paragraph_format', 'paragraph_style', 'quote', 'save', 'table',
            'url', 'video', 'csv'
        ];
        $adminCssDef = [
            // froala itself
            'froala_editor.min.css' => new FileAsset(
                $baseDir . '/css/froala_editor.min.css'
            ),
            'froala_style.min.css' => new FileAsset(
                $baseDir . '/css/froala_style.min.css'
            )
        ];
        $adminJsDef = [
            'froala_editor.min.js' => new FileAsset(
                $baseDir . '/js/froala_editor.min.js'
            ),
            'admin.js' => new FileAsset(
                $baseDir . '/js/admin.js'
            ),
            'adminTree.js' => new FileAsset(
                $baseDir . '/js/adminTree.js'
            )
        ];

        foreach ($enabledFroalaPlugins as $plugin) {
            $cssFile = $baseDir . "/css/plugins/$plugin.css";
            if (file_exists($cssFile)) {
                $adminCssDef["froala_plugin_$plugin"] = new FileAsset(
                    $baseDir . "/css/plugins/$plugin.css"
                );
            }
            $jsFile = $baseDir . "/js/plugins/$plugin.js";
            $jsMinFile = $baseDir . "/js/plugins/$plugin.min.js";
            if (file_exists($jsFile)) {
                $adminJsDef["froala_plugin_$plugin.js"] = new FileAsset(
                    $jsFile
                );
            } elseif (file_exists($jsMinFile)) {
                $adminJsDef["froala_plugin_$plugin.min.js"] = new FileAsset(
                    $jsMinFile
                );
            } else {
                throw new \RuntimeException(
                    "I think every froala plugin needs at least a js file,"
                    . " but none was found for plugin $plugin."
                );
            }
        }

        $enabledFroalaLanguages = ['de', 'en_gb', 'es', 'fr', 'it'];
        foreach ($enabledFroalaLanguages as $language) {
            $adminJsDef["froala_language_$language"] = new FileAsset(
                $baseDir . "/js/languages/$language.js"
            );
        }

        $adminJsDef['config.js'] = new FileAsset($baseDir . '/js/config.js');
        $adminJsDef['toastr.min.js'] = new FileAsset($baseDir . '/js/toastr.min.js');
        $adminJsDef['adminHeader.js'] = new FileAsset($baseDir . '/js/adminHeader.js');

        $this->registerAssetCollection('admin', [
            'css' => $adminCssDef,
            'js' => $adminJsDef
        ]);
    }

    /**
     * @param string             $collectionName to register
     * @param AssetInterface[][] $assets         map of target path to asset
     * @return void
     */
    public function registerAssetCollection($collectionName, array $assets)
    {
        assert(array_key_exists('css', $assets));
        assert(array_key_exists('js', $assets));
        foreach ($assets as $type => $typedAssets) {
            assert(
                $type === 'css' || $type === 'js' ||
                $type == 'static-css' || $type == 'static-js'
            );

            $key = "$type/$collectionName";
            $entries = [];
            if (substr($type, 0, 7) == 'static-') {
                foreach ($typedAssets as $url) {
                    assert(is_string($url));
                    $entries[] = $url;
                }

                $this->staticAssets[$key] = $entries;
            } else {
                // dynamic stuff
                foreach ($typedAssets as $targetPath => $asset) {
                    $asset->setTargetPath($targetPath);
                    $entries[] = $asset;
                }

                $filters = [];
                if ($this->combine) {
                    if ($type === 'css') {
                        $filters[] = $this->filterManager->get('minify_css');
                    } elseif ($type === 'js') {
                        $filters[] = $this->filterManager->get('compress_js');
                    } else {
                        assert(false);
                    }
                }

                $this->assetCollections[$key] = new AssetCollection(
                    $entries,
                    $filters
                );
            }
        }
    }

    /**
     * @param string $type           of the collection to lookup
     * @param string $collectionName to lookup
     * @return AssetCollectionInterface|null
     */
    public function getAssetCollection($type, $collectionName)
    {
        return $this->assetCollections["$type/$collectionName"] ?? null;
    }

    /**
     * A general purpose asset hash function, mainly used for protecting
     * against loading cached contents, not cryptographically strong.
     *
     * @param AssetInterface $asset to check
     * @return string hash of the asset
     */
    public static function getAssetHash(AssetInterface $asset)
    {
        $mTime = $asset->getLastModified();
        return hash('adler32', (string) $mTime);
    }

    /**
     * Retrieves all URLs of assets to load for a single collection. Note
     * that depending on the 'combine' setting, this may either be just one
     * URL for the collection or (uncombined) multiple URLs, one per file
     * contained in the collection.
     *
     * @param string $type           of assets to get urls for
     * @param string $collectionName to fetch
     * @return string[]
     */
    private function getUrlsForCollection(
        string $type,
        string $collectionName
    ) : array {
        $collection = $this->getAssetCollection($type, $collectionName);
        if (is_null($collection)) {
            error_log("referenced unknown asset collection: $collectionName");
            return [];
        } else {
            $hash = static::getAssetHash($collection);
            if ($this->combine) {
                $url = "$type/$collectionName/$hash";
                return [$url];
            } else {
                $urls = [];
                /** @var AssetInterface $asset */
                foreach ($collection->all() as $asset) {
                    $tgt = $asset->getTargetPath();
                    $urls[] = $type . '/' . $collectionName . '/'
                        . $tgt . '?version=' . $hash;
                }
                return $urls;
            }
        }
    }

    /**
     * Retrieves all URLs of assets to load for the given collections (by name).
     *
     * @param string   $type        ether 'css' or 'js'
     * @param string[] $collections to get urls for
     * @return string[] urls to include
     */
    public function getUrlsForCollections($type, $collections)
    {
        assert($type === 'css' || $type === 'js');
        $urls = [];
        foreach ($collections as $collectionName) {
            $staticKey = "static-$type/$collectionName";
            $urls = array_merge(
                $urls,
                $this->getUrlsForCollection($type, $collectionName),
                $this->staticAssets[$staticKey] ?? []
            );
        }
        return $urls;
    }
}
