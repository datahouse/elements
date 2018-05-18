<?php

namespace Datahouse\Elements\Control;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Configuration;
use Datahouse\Elements\Control\Authentication\IAuthenticator;
use Datahouse\Elements\Control\Session\ISessionHandler;
use Datahouse\Elements\Control\TextSearch\ElasticsearchInterface;
use Datahouse\Elements\Factory;
use Datahouse\Elements\ReFactory;

/**
 * A BaseRequestHandler that sports some abstract methods that are not HTTP
 * specific. Therefore, none of the $_SERVER or $_REQUEST globals should ever
 * be used here, as that's considered HTTP specific.
 *
 * However, anything that's common and useful for multiple controllers fits
 * in here, as all controllers have access to such the request handler.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class BaseRequestHandler
{
    protected $refactory;
    protected $adapter;
    protected $authenticator;
    protected $sessionHandler;
    protected $asset_handler;
    protected $process;
    /* @var array $globalOptions settable by the application */
    protected $globalOptions;
    protected $collector;
    /* @var array $searchIndex FTS indices with Elasticsearch */
    protected $searchIndex;
    protected $esInterface;

    /**
     * @param ReFactory              $refactory      class factory fetcher
     * @param IStorageAdapter        $adapter        for storage access
     * @param IChangeProcess         $process        the change process
     * @param IAuthenticator         $authenticator  for user authentication
     * @param AssetHandler           $assetHandler   for serving css and js
     * @param ISessionHandler        $sessionHandler session handler
     * @param ContentCollector       $collector      content collector
     * @param ElasticsearchInterface $esInterface    for full text search
     */
    public function __construct(
        ReFactory $refactory,
        IStorageAdapter $adapter,
        IChangeProcess $process,
        IAuthenticator $authenticator,
        AssetHandler $assetHandler,
        ISessionHandler $sessionHandler,
        ContentCollector $collector,
        ElasticsearchInterface $esInterface
    ) {
        $this->refactory = $refactory;
        $this->adapter = $adapter;
        $this->process = $process;
        $this->authenticator = $authenticator;
        $this->asset_handler = $assetHandler;
        $this->sessionHandler = $sessionHandler;
        $this->collector = $collector;
        $this->esInterface= $esInterface;

        $this->globalOptions = [
            'DEFAULT_LANGUAGE' => 'en',
            'ENABLE_ELEMENT_ADD_REMOVE' => true,
            'ENABLE_METADATA_UPDATE' => true,
            'ENABLE_TEMPLATE_SWITCH' => true,
        ];
    }

    /**
     * @param string $name  option name to set
     * @param mixed  $value new value to set
     * @return void
     */
    public function setGlobalOption(string $name, $value)
    {
        $this->globalOptions[$name] = $value;
    }

    /**
     * @return array a map of global options
     */
    public function getGlobalOptions() : array
    {
        return $this->globalOptions;
    }

    /**
     * Refine a single menu entry. Deriving classes may override this method
     * to add custom information to a menu entry.
     *
     * @param Element $element element itself
     * @param string  $lang    chosen language
     * @param array   $entry   the menu entry to populate
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('element')
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('lang')
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('entry')
     */
    public function refineMenuEntry(
        Element $element,
        $lang,
        array &$entry
    ) {
        // default implementation intentionally left blank
    }

    /**
     * @deprecated Callers should use Dice to get a ReFactory instance.
     * @return Factory
     */
    public function getFactory()
    {
        return $this->refactory->getFactory();
    }

    /**
     * @return IStorageAdapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @return IAuthenticator
     */
    public function getAuthenticator() : IAuthenticator
    {
        return $this->authenticator;
    }

    /**
     * @return ISessionHandler
     */
    public function getSessionHandler() : ISessionHandler
    {
        return $this->sessionHandler;
    }

    /**
     * @return AssetHandler
     */
    public function getAssetHandler() : AssetHandler
    {
        return $this->asset_handler;
    }

    /**
     * @return IChangeProcess
     */
    public function getChangeProcess() : IChangeProcess
    {
        return $this->process;
    }

    /**
     * @return ContentCollector
     */
    public function getContentCollector() : ContentCollector
    {
        return $this->collector;
    }

    /**
     * @return ElasticsearchInterface
     */
    public function getElasticsearchInterface() : ElasticsearchInterface
    {
        return $this->esInterface;
    }

    /**
     * Mark deprecated
     *
     * @param Element $currentElement to load ancestors for
     * @return Element[] of ancestors, including the current element
     */
    public function loadAncestors(Element $currentElement) : array
    {
        trigger_error("use the ContentCollector", E_USER_DEPRECATED);
        return $this->collector->loadAncestors($currentElement);
    }

    /**
     * @deprecated
     * @return Configuration
     */
    public function getConfiguration() : Configuration
    {
        // FIXME: should probably go via the factory, directly. Or some global
        // element. Or whatever.
        return $this->refactory->getFactory()->getConfiguration();
    }

    /**
     * @return ReFactory wrapping the Factory itself
     */
    public function getReFactory() : ReFactory
    {
        // FIXME: callers should use Dice to get an instance of the ReFactory,
        // instead.
        return $this->refactory;
    }

    /**
     * Mark deprecated, non-functional
     *
     * @param Element  $parent         element from which to fetch children
     * @param string[] $activeChildren ids of the active children
     * @param int|null $depth          depth limit of the menu to generate
     * @param User     $user           for authorization
     * @param callable $refinerFunc    for menu entry refinement
     * @return array of menu entries, possibly nested
     */
    public function assembleMenuFrom(
        Element $parent,
        array $activeChildren,
        $depth,
        User $user,
        callable $refinerFunc
    ) : array {
        trigger_error("use the ContentCollector", E_USER_DEPRECATED);
        $this->collector->assembleMenuFrom(
            'view',
            $parent,
            $activeChildren,
            $depth,
            $user,
            $refinerFunc
        );
    }
}
