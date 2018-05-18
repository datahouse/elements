<?php

namespace Datahouse\Elements\Presentation;

use Datahouse\Elements\Constants;
use Datahouse\Elements\Control\AssetHandler;
use Datahouse\Elements\Presentation\TwigExtensions\Elements\ExtensionFactory;
use Twig_Environment;
use Twig_Loader_Filesystem;
use Twig_Extension_Debug;

use Datahouse\Elements\Abstraction\ElementContents;

/**
 * Abstract base class for anything twig based - admin frontend or web page.
 *
 * @package Datahouse\Elements\Presentation
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class TwigRenderer implements IRenderer
{
    /** @var array $data */
    protected $data;

    /** @var \Twig_Loader_Filesystem */
    private $loader;

    /** @var bool $debug */
    protected $debug;

    protected $templateFilename;
    protected $addCollections;

    /**
     * @param string   $templateFilename to pass to Twig
     * @param string[] $collections      additional collections to use
     */
    public function __construct(string $templateFilename, $collections = [])
    {
        $this->templateFilename = $templateFilename;
        $templates_dir = __DIR__ . '/../../templates/';
        $this->loader = new Twig_Loader_Filesystem($templates_dir);
        $this->addCollections = $collections;
        $this->debug = true;
    }

    /**
     * Applications using this library can add template paths via this method.
     *
     * @param string $path to other template files to use
     * @throws \Twig_Error_Loader
     * @return void
     */
    public function addTemplateDirectory($path)
    {
        $this->loader->addPath($path);
    }

    /**
     * @param array $data for the template
     * @return void
     */
    public function setTemplateData(array $data)
    {
        $this->data = $data;
    }

    /**
     * Twig cannot handle stdClass, therefore this converter method.
     *
     * @param ElementContents $contents to convert
     * @return array to be used by twig
     */
    public static function contentsToArray(ElementContents $contents)
    {
        return json_decode(json_encode($contents), true);
    }

    /**
     * Merge template data given by the controller with data from this or
     * derivative template classes that needs to be passed to the twig
     * template engine.
     *
     * @param AssetHandler $ah for fetching asset collections
     * @throws \Exception
     * @return void
     */
    protected function mergeTemplateData(AssetHandler $ah)
    {
        $collections = $this->enumCollectionsUsed();
        $stylesheets = $ah->getUrlsForCollections('css', $collections);
        $javascripts = $ah->getUrlsForCollections('js', $collections);
        $this->data['stylesheets'] = $stylesheets;
        $this->data['javascripts'] = $javascripts;
        $this->data['root_url'] = Constants::getRootUrl();
        $this->data['jsUserData']['ROOT_URL'] = $this->data['root_url'];
        $this->data['jsUserData']['SERVER_TIME'] = time();
    }

    /**
     * Adds the 'base' and 'admin' asset collections.
     *
     * @return string[] collections used by this template
     */
    public function enumCollectionsUsed() : array
    {
        $collections = array('base');
        if (isset($this->data['permissions']['admin']) &&
            !is_null($this->data['permissions']['admin']) &&
            $this->data['permissions']['admin']
        ) {
            $collections[] = 'admin';
        }
        return array_merge($collections, $this->addCollections);
    }

    /**
     * Actually fill the template with the data provided and return the
     * results.
     *
     * @param AssetHandler $assetHandler to use during rendering for assets.
     * @return string[] pair of string for type and content
     * @throws \Exception
     */
    public function render(AssetHandler $assetHandler) : array
    {
        $twig_cfg = ['debug' => $this->debug];
        if (!$this->debug) {
            $twig_cfg['cache'] = '/tmp/template-cache';
        }
        $twig = new Twig_Environment($this->loader, $twig_cfg);
        ExtensionFactory::registerExtensions($twig);

        if ($this->debug) {
            $twig->addExtension(new Twig_Extension_Debug());
        }

        $this->mergeTemplateData($assetHandler);

        $html_data = $twig->render($this->templateFilename, $this->data);
        return ['text/html', $html_data];
    }
}
