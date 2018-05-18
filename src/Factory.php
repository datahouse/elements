<?php

namespace Datahouse\Elements;

use RuntimeException;

use Dice\Dice;
use Dice\Rule as DiceRule;
use Dice\Instance;

use Datahouse\Libraries\JSON;
use Datahouse\Libraries\Database\DbFactory;

use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Control\Authorization\IAuthorizationHandler;
use Datahouse\Elements\Control\AssetHandler;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\ContentCollector;
use Datahouse\Elements\Control\EleDefRegistry;
use Datahouse\Elements\Control\IUrlResolver;
use Datahouse\Elements\Control\TextSearch\ElasticsearchInterface;

/**
 * A factory that helps orchestrating the different pieces of 'elements' via
 * Dice, based on a single configuration object.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class Factory
{
    protected $config;
    /* @var Dice $dice */
    protected $dice;

    protected $subs;

    /**
     * Factory constructor.
     *
     * @param Configuration $config to use
     */
    public function __construct(Configuration $config)
    {
        $this->config = $config;
        $this->dice = new Dice();

        // Add rules for the database library
        DbFactory::addDiceRules($this->dice);

        // Add a global JSON parser config
        $rule = new DiceRule;
        $rule->shared = true;
        $rule->constructParams = [true, 512];
        $this->dice->addRule(JSON\Converter\Config::class, $rule);

        // Add a rule for the storage adapter
        $rule = new DiceRule;
        $rule->shared = true;
        $rule->constructParams = $config->storageAdapterArgs;
        $this->dice->addRule($config->storageAdapterClass, $rule);

        // ..and one for the EleDefRegistry
        $rule = new DiceRule;
        $rule->shared = true;
        $rule->constructParams = [
            $config->defaultEleDefId,
            $config->elementDefinitions
        ];
        $this->dice->addRule(EleDefRegistry::class, $rule);

        // ..the ContentCollector
        $rule = new DiceRule;
        $rule->shared = true;
        $rule->constructParams = [$this];
        $this->dice->addRule(ContentCollector::class, $rule);

        // ..and another one for the ElasticsearchClient
        $rule = new DiceRule;
        $rule->shared = true;
        $rule->constructParams = [$this];
        $this->dice->addRule(ElasticsearchInterface::class, $rule);

        // ..plus the ReFactory.
        $rule = new DiceRule;
        $rule->shared = true;
        $rule->constructParams = [$this];
        $this->dice->addRule(ReFactory::class, $rule);

        // Prepare substitution array
        $ns = 'Datahouse\\Elements\\Control\\';
        $this->subs = [
            IStorageAdapter::class
                => new Instance($config->storageAdapterClass)
        ];
        foreach ($config->replacements as $iface => $className) {
            if (is_null($className)) {
                throw new RuntimeException("No class defined for $iface");
            } elseif ($ns . $iface != $className) {
                $this->subs[$ns . $iface] = new Instance($className);
            }
        }

        // Add rules for all other element classes
        foreach ($config->replacements as $iface => $className) {
            $rule = new DiceRule;
            $rule->shared = true;
            $rule->substitutions = $this->subs;
            $this->dice->addRule($className, $rule);
        }

        // Allow setting this flag via environment variable.
        $combine = getenv('ASSET_COMPOSE') === false
            ? true // if not set, default to compose collections
            : (bool) getenv('ASSET_COMPOSE');
        $this->getAssetHandler()->setCombine($combine);
    }

    /**
     * @return Configuration
     */
    public function getConfiguration() : Configuration
    {
        return $this->config;
    }

    /**
     * Creates the main request handler object using dice.
     *
     * @return BaseRequestHandler
     */
    public function getRequestHandler() : BaseRequestHandler
    {
        $className = $this->config->replacements['BaseRequestHandler'];
        return $this->dice->create($className, [$this]);
    }

    /**
     * @return AssetHandler an asset handler allowing the application to
     * register custom asset collections.
     */
    public function getAssetHandler() : AssetHandler
    {
        $className = $this->config->replacements['AssetHandler'];
        return $this->dice->create($className);
    }

    /**
     * @return ContentCollector pre-initialized with other configured objects
     */
    public function getContentCollector() : ContentCollector
    {
        return $this->dice->create(ContentCollector::class);
    }

    /**
     * @return IUrlResolver the resolver configured to use
     */
    public function getUrlResolver() : IUrlResolver
    {
        $instance = $this->subs[IUrlResolver::class];
        return $this->dice->create($instance->name);
    }

    /**
     * @return IStorageAdapter the storage adapter configured to use
     */
    public function getStorageAdapter() : IStorageAdapter
    {
        $instance = $this->subs[IStorageAdapter::class];
        return $this->dice->create($instance->name);
    }

    /**
     * @return IAuthorizationHandler the configured authorization handler
     */
    public function getAuthorizationHandler() : IAuthorizationHandler
    {
        $instance = $this->subs[IAuthorizationHandler::class];
        return $this->dice->create($instance->name);
    }

    /**
     * @return BaseRouter the application has configured
     */
    public function getRouter() : BaseRouter
    {
        $className = $this->config->replacements['BaseRouter'];
        return $this->dice->create($className);
    }

    /**
     * @param string $className to create
     * @return Object of the type requested
     */
    public function createClass(string $className)
    {
        $rule = new DiceRule;
        $rule->shared = true;
        $rule->substitutions = $this->subs;
        $this->dice->addRule($className, $rule);

        $instance = $this->dice->create($className);
        assert(isset($instance));
        return $instance;
    }
}
