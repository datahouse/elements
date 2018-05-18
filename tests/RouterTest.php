<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Constants;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\EleDefRegistry;
use Datahouse\Elements\Control\Exceptions\BadRequest;
use Datahouse\Elements\Control\Exceptions\ResourceNotFound;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\IController;
use Datahouse\Elements\Control\IUrlResolver;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Presentation\IElementDefinition;

/**
 * @package Datahouse\Elements\Tests
 * @author Dena Moshfegh (dmo) <dena.moshfegh@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class RouterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * A helper method for all tests below.
     *
     * @param string $url    of the request to test
     * @param string $method of the request to test
     * @return IController|null
     */
    public function invokeRouterFor(string $url, string $method = 'GET')
    {
        $eleDef = $this->getMockBuilder(IElementDefinition::class)
            ->getMock();
        $knownRefs = [
            'selectableRef' => ['selectable' => true],
            'loopRef' => ['selectable' => false],
        ];
        $eleDef->method('getKnownReferences')->willReturn($knownRefs);
        $fakeEleDefId = 'fakeTestDefinition';

        $eleDefRegistry = $this->getMockBuilder(EleDefRegistry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $eleDefRegistry->method('getEleDefById')->will($this->returnValueMap([
            [$fakeEleDefId, $eleDef]
        ]));

        $adapter = $this->getMockBuilder(IStorageAdapter::class)
            ->getMock();
        $adapter->method('isInitialized')->willReturn(true);
        $adapter->method('getStorageVersion')->willReturn(
            Constants::STORAGE_VERSION
        );
        $adapter->method('isValidElementId')->willReturn(true);
        $adapter->method('isValidFieldName')->willReturn(true);
        $adapter->method('isValidLanguage')->willReturn(true);

        $element = new Element();
        $element->setType('page');
        $element->addVersion(1, new ElementVersion());

        $ev = new ElementVersion();
        $ev->setDefinition($fakeEleDefId);
        $element->addVersion(2, $ev);

        $adapter->method('loadElement')->willReturn($element);

        $resolver = $this->getMockBuilder(IUrlResolver::class)->getMock();
        $resolver->method('lookupUrl')->willReturn([$element->getId(), null]);

        $requestHandler = $this->getMockBuilder(BaseRequestHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new HttpRequest();
        $request->method = $method;
        $request->url = $url;
        $router = new BaseRouter($adapter, $resolver, $eleDefRegistry);
        return $router->startRouting($requestHandler, $request);
    }

    /**
     * Trivial test of the routing of the admin api.
     *
     * @return void
     */
    public function testRouteAdmin()
    {
        $controller = $this->invokeRouterFor('/admin/tree');
        $this->assertInstanceOf(
            'Datahouse\Elements\Control\Admin\Tree',
            $controller
        );

        $controller = $this->invokeRouterFor('/admin/tree/data');
        $this->assertInstanceOf(
            'Datahouse\Elements\Control\Admin\TreeData',
            $controller
        );

        $this->expectException(ResourceNotFound::class);
        $this->invokeRouterFor('/admin/foo');
    }

    /**
     * Trivial test of assetic resource routing.
     *
     * @return void
     */
    public function testRouteAssetic()
    {
        $controller = $this->invokeRouterFor('/js/collection/file.js');
        $this->assertInstanceOf(
            'Datahouse\Elements\Control\AsseticResourceController',
            $controller
        );
    }

    /**
     * Test routing for element field editing. Expects an ElementField
     * controller.
     *
     * @return void
     */
    public function testRouteElement()
    {
        $fakeElementId = str_repeat('1', 40);

        $controller = $this->invokeRouterFor(
            "/admin/element/$fakeElementId/state/2"
        );
        $this->assertInstanceOf(
            'Datahouse\Elements\Control\Admin\ElementState',
            $controller
        );

        $controller = $this->invokeRouterFor(
            "/admin/element/$fakeElementId/field/2/title/de"
        );
        $this->assertInstanceOf(
            'Datahouse\Elements\Control\Admin\ElementField',
            $controller
        );
    }

    /**
     * Test routing for reference setting.
     *
     * @return void
     */
    public function testRouteElementReference()
    {
        $fakeElementId = str_repeat('1', 40);

        // the GET variant
        $exp = 'Datahouse\Elements\Control\Admin\ElementListReferences';
        $controller = $this->invokeRouterFor(
            "/admin/element/$fakeElementId/reference/2/selectableRef"
        );
        $this->assertInstanceOf($exp, $controller);

        // the POST variants
        $exp = 'Datahouse\Elements\Control\Admin\ElementSetReference';
        $controller = $this->invokeRouterFor(
            "/admin/element/$fakeElementId/reference/2/selectableRef",
            'POST'
        );
        $this->assertInstanceOf($exp, $controller);
    }

    /**
     * Try routing a non-selectable reference. Should fail.
     *
     * @return void
     */
    public function testRouteNonSelectableReference()
    {
        $this->expectException(BadRequest::class);

        $fakeElementId = str_repeat('1', 40);

        $this->invokeRouterFor(
            "/admin/element/$fakeElementId/reference/2/loopRef",
            'POST'
        );
    }

    /**
     * Test fetching an invalid element id.
     *
     * @return void
     */
    public function testRouteElementBad1()
    {
        $this->expectException(ResourceNotFound::class);
        $this->invokeRouterFor("/admin/element/1234/");
    }

    /**
     * Test fetching an unknown version of a known element.
     *
     * @return void
     */
    public function testRouteElementUnknownVersion()
    {
        $fakeElementId = str_repeat('1', 40);

        $this->expectException(ResourceNotFound::class);
        $this->invokeRouterFor("/admin/element/$fakeElementId/state/99");
    }

    /**
     * Test routing of an url for an element that contains spaces.
     *
     * @return void
     */
    public function testRouteSlugWithSpaces()
    {
        $controller = $this->invokeRouterFor("/Alpha%20and%20Omega");
        $exp = 'Datahouse\Elements\Control\PageController';
        $this->assertInstanceOf($exp, $controller);
    }
}
