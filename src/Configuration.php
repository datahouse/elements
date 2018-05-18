<?php

namespace Datahouse\Elements;

use stdClass;

use Datahouse\Elements\Abstraction\ISerializable;
use Datahouse\Elements\Abstraction\Exceptions\SerDesException;
use Datahouse\Elements\Control\AssetHandler;
use Datahouse\Elements\Control\Authentication\PasswordAuthenticator;
use Datahouse\Elements\Control\Authorization\StackedAllowDenyAuthorizationHandler;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\BaseUrlResolver;
use Datahouse\Elements\Control\HttpRequestHandler;
use Datahouse\Elements\Control\ContentCollector;
use Datahouse\Elements\Control\ContentSelection;
use Datahouse\Elements\Control\Session\PhpSessionHandler;
use Datahouse\Elements\Presentation\Exceptions\ConfigurationError;

/**
 * A simple and serializable struct holding global configuration settings for
 * Elements.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class Configuration implements ISerializable
{
    public $rootUrl;

    public $defaultEleDefId;
    public $elementDefinitions;

    public $storageAdapterClass;
    public $storageAdapterArgs;
    public $routerClass;
    public $replacements;
    public $searchIndices;

    public $setupChecks;

    /**
     * @param string $defaultEleDefId        default element definition
     * @param array  $elementDefinitions     provided by the application
     * @param string $changeProcessClass     change process to use
     * @param string $storageAdapterClass    storage adapter to use
     * @param array  $adapterConstructorArgs storage adapter arguments
     */
    public function __construct(
        string $defaultEleDefId,
        array $elementDefinitions,
        string $changeProcessClass,
        string $storageAdapterClass,
        array $adapterConstructorArgs
    ) {
        $this->defaultEleDefId = $defaultEleDefId;
        $this->elementDefinitions = $elementDefinitions;
        $this->storageAdapterClass = $storageAdapterClass;
        $this->storageAdapterArgs = $adapterConstructorArgs;

        // Replacements per interface, pre-populated with useful defaults.
        $this->replacements = [
            'AssetHandler' => AssetHandler::class,
            'BaseRequestHandler' => HttpRequestHandler::class,
            'BaseRouter' => BaseRouter::class,
            'Authentication\\IAuthenticator' => PasswordAuthenticator::class,
            'Authorization\\IAuthorizationHandler' =>
                StackedAllowDenyAuthorizationHandler::class,
            'ContentCollector' => ContentCollector::class,
            'ContentSelection\\IContentSelector' =>
                ContentSelection\BestLanguageSelector::class,
            'IChangeProcess' => $changeProcessClass,
            'IUrlResolver' => BaseUrlResolver::class,
            'Session\\ISessionHandler' => PhpSessionHandler::class
        ];
    }

    /**
     * Loads the configured ROOT_URL from the environment.
     *
     * @throws ConfigurationError
     * @return void
     */
    public function loadRootUrlFromEnvironment()
    {
        $this->rootUrl = getenv('ROOT_URL');
        if (!$this->rootUrl) {
            throw new ConfigurationError('Fatal error: ROOT_URL is not set');
        }
    }

    /**
     * @return stdClass serialized or at least a serializable representation
     * of the object
     * @throws SerDesException
     */
    public function serialize() : stdClass
    {
        $replacementMap = new stdClass();
        foreach ($this->replacements as $key => $value) {
            $replacementMap->{$key} = $value;
        }
        $result = new stdClass();
        $result->{'rootUrl'} = $this->rootUrl;
        $result->{'defaultEleDefId'} = $this->defaultEleDefId;
        $result->{'elementDefinitions'} = $this->elementDefinitions;
        $result->{'replacements'} = $replacementMap;
        $result->{'storageAdapterClass'} = $this->storageAdapterClass;
        $result->{'storageAdapterArgs'} = $this->storageAdapterArgs;
        $result->{'searchIndices'} = $this->searchIndices;
        return $result;
    }

    /**
     * @param stdClass $data to deserialize from
     * @return void
     * @throws SerDesException
     */
    public function deserialize(stdClass $data)
    {
        $this->dice = null;
        $this->initialized = false;

        $this->rootUrl = $data->{'rootUrl'};

        $this->replacements = [];
        foreach (get_object_vars($data->{'replacements'}) as $key => $value) {
            $this->replacements[$key] = $value;
        }
        $this->storageAdapterClass = $data->{'storageAdapterClass'};
        $this->storageAdapterArgs = $data->{'storageAdapterArgs'};
        $this->searchIndices = json_decode(
            json_encode($data->{'searchIndices'}),
            true
        );

        $this->defaultEleDefId = $data->{'defaultEleDefId'};
        $this->elementDefinitions = json_decode(
            json_encode($data->{'elementDefinitions'}),
            true
        );
    }

    /**
     * @return string class name of the configured change process
     */
    public function getChangeProcessClass() : string
    {
        return $this->replacements['IChangeProcess'];
    }

    /**
     * @param string $className content selector to use
     * @return void
     */
    public function setContentSelector(string $className)
    {
        $this->replacements['IContentSelector'] = $className;
    }

    /**
     * @param string $className the IUrlResolver class to use
     * @return void
     */
    public function setResolver(string $className)
    {
        $this->replacements['IUrlResolver'] = $className;
    }

    /**
     * @param string $className the authorization handler to use
     * @return void
     */
    public function setAuthorizationHandler(string $className)
    {
        $this->replacements['Authorization\\IAuthorizationHandler'] = $className;
    }

    /**
     * @param string $className the authenticator to use
     * @return void
     */
    public function setAuthenticator(string $className)
    {
        $this->replacements['Authentication\\IAuthenticator'] = $className;
    }

    /**
     * @param string $className the session handler to use
     * @return void
     */
    public function setSessionHandler(string $className)
    {
        $this->replacements['Session\\ISession'] = $className;
    }

    /**
     * @param string $className the router to use
     * @return void
     */
    public function setRouter(string $className)
    {
        $this->replacements['BaseRouter'] = $className;
    }

    /**
     * @param array $searchIndices definition of search indices
     * @return void
     */
    public function setSearchIndices(array $searchIndices)
    {
        $this->searchIndices = $searchIndices;
    }

    public function registerSetupChecks(array $setupChecks)
    {
        $this->setupChecks = $setupChecks;
    }
}
