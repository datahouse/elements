<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Configuration;
use Datahouse\Elements\Factory;
use Datahouse\Elements\Abstraction\YamlAdapter;
use Datahouse\Elements\Control\Authentication\NoOpAuthenticator;
use Datahouse\Elements\Control\HttpRequestHandler;
use Datahouse\Elements\Tests\Helpers\ExampleChangeProcess;

/**
 * FactoryTest - exercises request handler instantiation
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class FactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test if a basic invocation of the factory works.
     *
     * @return void
     */
    public function test()
    {
        $config = new Configuration(
            'SomeDefaultTemplate',
            [],
            ExampleChangeProcess::class,
            YamlAdapter::class,
            [__DIR__ . '/data/1/yaml', __DIR__ . '/data/1/blobs']
        );
        $config->setAuthenticator(NoOpAuthenticator::class);

        // Try a somewhat standard configuration...
        $factory = new Factory($config);

        // Try instantiating a controller via the factor.
        $handler = $factory->getRequestHandler();
        $this->assertInstanceOf(HttpRequestHandler::class, $handler);
    }
}
