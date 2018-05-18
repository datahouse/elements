<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementContents;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Constants;
use Datahouse\Elements\Control\AssetHandler;
use Datahouse\Elements\Control\Authentication\NoOpAuthenticator;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\ContentCollector;
use Datahouse\Elements\Control\ContentSelection\NewestVersionSelector;
use Datahouse\Elements\Control\EleDefRegistry;
use Datahouse\Elements\Control\IUrlResolver;
use Datahouse\Elements\Control\Session\ISessionHandler;
use Datahouse\Elements\Control\TextSearch\ElasticsearchInterface;
use Datahouse\Elements\Presentation\IElementDefinition;
use Datahouse\Elements\ReFactory;
use Datahouse\Elements\Tests\Helpers\ExampleChangeProcess;
use Datahouse\Elements\Tests\Helpers\ExampleElementDefinition;

/**
 * Trait HttpRequestHandlerCommon featuring test cases for the
 * HttpRequestHandler that are storage adapter agnostic.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
trait BaseRequestHandlerCommon
{
    /** @var ContentCollector $collector to test */
    protected $collector;
    /** @var BaseRequestHandler $handler to test */
    protected $handler;

    /**
     * Setup the object under test - the BaseRequestHandler - after the base
     * class initializes the storage adapter.
     * @return void
     */
    protected function setUpTestObject()
    {
        $refactory = $this->getMockBuilder(ReFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $sessionHandler = $this->getMockBuilder(ISessionHandler::class)
            ->getMock();
        $sessionHandler->method('getLanguage')->willReturn('');

        $esInterface = $this->getMockBuilder(ElasticsearchInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $auth = new NullAuthorizationHandler();
        $csel = new NewestVersionSelector($auth);
        $csel->setLanguagePreferences([['en', '1.0']], 'en');
        $process = new ExampleChangeProcess();
        $authenticator = new NoOpAuthenticator($this->adapter);
        $resolver = $this->getMockBuilder(IUrlResolver::class)->getMock();

        $eleDefRegistry = new EleDefRegistry(
            'Default',
            [
                'Article' => '',
                'ExampleElementDefinition' => ExampleElementDefinition::class,
            ]
        );

        $this->collector = new ContentCollector(
            $refactory,
            $this->adapter,
            $process,
            $csel,
            $resolver,
            $eleDefRegistry
        );
        $this->handler = new BaseRequestHandler(
            $refactory,
            $this->adapter,
            $process,
            $authenticator,
            new AssetHandler(false),
            $sessionHandler,
            $this->collector,
            $esInterface
        );
    }

    /**
     * Smiplistic check against the version hard-coded in Constants.
     *
     * @return void
     */
    public function testStorageVersion()
    {
        $version = $this->adapter->getStorageVersion();
        $expVersion = Constants::STORAGE_VERSION;
        $this->assertEquals($expVersion, $version);
    }

    /**
     * Helper function loading a test element and user.
     *
     * @return Element
     */
    public function loadTestElement() : Element
    {
        $eleId = '5a8ca84c7d4d9b055f05c55b1f707f223979d387';
        $element = $this->adapter->loadElement($eleId);
        $this->assertNotNull($element, "Element not found in test storage");
        return $element;
    }

    /**
     * Tests a simple element without links.
     * @return void
     */
    public function testSimpleElement()
    {
        $userAlice = $this->adapter->loadUser('alice');
        $this->assertNotNull($userAlice);

        $element = $this->loadTestElement();

        /* @var ElementVersion $ev */
        list ($vno, $language, $ev) = $this->collector->loadBestVersion(
            $element,
            $userAlice
        );

        $ec = $ev->getContentsFor($language);
        $this->assertTrue($ec instanceof ElementContents);

        $eleDef = $this->getMockBuilder(IElementDefinition::class)
            ->getMock();

        // Simulate no known references, should yield no links.
        $elementData = $this->collector->collectElementContentsData(
            $userAlice,
            $element->getId(),
            $ec,
            $ev,
            1,
            true,
            $eleDef,
            $vno,
            $language
        );
        $this->assertEquals([], $elementData['subs']);
        $this->assertEquals([], $elementData['refs']);
    }

    /**
     * Helper function for the tests below.
     *
     * @param User    $user      user to test with
     * @param Element $element   element to load
     * @param array   $knownRefs definition of known references for the element
     * @return array data collected for referenced elements
     */
    public function runElementDataCollection(
        User $user,
        Element $element,
        array $knownRefs
    ) {
        /* @var ElementVersion $ev */
        list ($vno, $language, $ev) =
            $this->collector->loadBestVersion($element, $user);
        $this->assertEquals($language, 'en');

        /* @var ElementContents $ec */
        $ec = $ev->getContentsFor($language);
        $this->assertTrue($ec instanceof ElementContents);

        $eleDef = $this->getMockBuilder(IElementDefinition::class)
            ->getMock();
        $eleDef->method('getKnownContentFields')->willReturn([]);
        $eleDef->method('getKnownReferences')->willReturn($knownRefs);
        $eleDef->method('getKnownSubElements')->willReturn([]);

        $elementData = $this->collector->collectElementContentsData(
            $user,
            $element->getId(),
            $ec,
            $ev,
            1,
            true,
            $eleDef,
            $vno,
            $language
        );

        $this->assertEquals(1, $elementData['version']);
        return $elementData['refs'];
    }

    /**
     * Tests an element with two links.
     * @return void
     */
    public function testElementMissingAllLinks()
    {
        $user = User::getAnonymousUser();
        $element = $this->loadTestElement();

        // Test simulating a template that knows no links.
        $links = $this->runElementDataCollection($user, $element, []);
        $this->assertEquals($links, []);
    }

    /**
     * Test against a page definition with one link defined.
     *
     * @return void
     */
    public function testElementWithOneLink()
    {
        $user = User::getAnonymousUser();
        $element = $this->loadTestElement();

        // Test simulating a template that knows one link.
        $linkDef = [
            'selectable' => true,
            'parent' => '0ad052dd9f32405521e43c6ebdc52f5a025493b2'
        ];
        $knownRefs = ['side_panel' => $linkDef];
        $links = $this->runElementDataCollection($user, $element, $knownRefs);
        $this->assertEquals(1, count($links));
        $this->assertEquals(
            '54fd1711209fb1c0781092374132c66e79e2241b',
            $links['side_panel']['selected']
        );
        $this->assertEquals(1, count($links['side_panel']['children']));
        $this->assertEquals(
            $links['side_panel']['children'][0]['fields']['contents'],
            "The Grand Approved Side Panel"
        );
    }

    /**
     * Test against a page definition with two links defined, the test
     * element featuring both references.
     *
     * @return void
     */
    public function testElementWithTwoLinks()
    {
        $user = User::getAnonymousUser();
        $element = $this->loadTestElement();

        $linkDef = [
            'selectable' => true,
            'parent' => '0ad052dd9f32405521e43c6ebdc52f5a025493b2'
        ];
        $knownRefs = [
            'contact' => $linkDef,
            'side_panel' => $linkDef
        ];
        $links = $this->runElementDataCollection($user, $element, $knownRefs);
        $this->assertEquals(2, count($links));
        $this->assertEquals(
            '54fd1711209fb1c0781092374132c66e79e2241b',
            $links['side_panel']['selected']
        );
        $this->assertEquals(1, count($links['side_panel']['children']));
        $this->assertEquals(
            $links['side_panel']['children'][0]['fields']['contents'],
            "The Grand Approved Side Panel"
        );
        $this->assertEquals(
            'e9c5d7db93a1c17d45c5820daf458224bfa7a725',
            $links['contact']['selected']
        );
        $this->assertEquals(1, count($links['contact']['children']));
        $this->assertEquals(
            $links['contact']['children'][0]['fields']['contents'],
            "Mickey Mouse"
        );
    }

    /**
     * Test an element with one link set and another one defined in the
     * definition but not set in the element.
     *
     * @return void
     */
    public function testElementWithOneGoodAndOneBadLink()
    {
        $user = User::getAnonymousUser();
        $element = $this->loadTestElement();

        $linkDef = [
            'selectable' => true,
            'parent' => '0ad052dd9f32405521e43c6ebdc52f5a025493b2'
        ];
        $knownRefs = [
            'contact' => $linkDef,
            'unknown' => $linkDef
        ];
        $links = $this->runElementDataCollection($user, $element, $knownRefs);
        $this->assertEquals(2, count($links));
        $this->assertEquals(
            'e9c5d7db93a1c17d45c5820daf458224bfa7a725',
            $links['contact']['selected']
        );
        $this->assertEquals(1, count($links['contact']['children']));
        $this->assertEquals(
            $links['contact']['children'][0]['fields']['contents'],
            "Mickey Mouse"
        );
        $this->assertEquals([], $links['unknown']['children']);
    }

    /**
     * Test an element with a direct reference to another element.
     *
     * @return void
     */
    public function testElementWithDirectReference()
    {
        $user = User::getAnonymousUser();
        $element = $this->loadTestElement();

        $knownRefs = [
            'directReference' => [
                'direct' => 'c66be7210915f39e91456fc2eac9441012a0a3ea',
                'selectable' => false
            ]
        ];
        $links = $this->runElementDataCollection($user, $element, $knownRefs);
        $this->assertEquals(1, count($links));

        $this->assertEquals(
            'c66be7210915f39e91456fc2eac9441012a0a3ea',
            $links['directReference']['selected']
        );
        $this->assertEquals(1, count($links['directReference']['children']));
        $this->assertContains(
            "for testing",
            $links['directReference']['children'][0]['fields']['contents']
        );
    }

    /**
     * Tests assembly of menus.
     *
     * @return void
     */
    public function testRequiredMenus()
    {
        $user_alice = $this->adapter->loadUser('alice');
        $this->assertNotNull($user_alice);

        $element = $this->adapter->loadElement(
            '5a8ca84c7d4d9b055f05c55b1f707f223979d387'
        );
        $this->assertNotNull($element, "Element not found in test storage");

        $ancestors = $this->collector->loadAncestors($element);
        $ancestorIds = array_map(function (Element $e) {
            return $e->getId();
        }, $ancestors);
        $this->assertEquals([
            '0000000000000000000000000000000000000000',
            '471eb91bd28ec1ae26563952d6b5da42720a607b',
            '5a8ca84c7d4d9b055f05c55b1f707f223979d387'
        ], $ancestorIds);

        $reqMenus = [
            'mainmenu' => ['start_level' => 2, 'depth' => 1]
        ];
        $menus = $this->collector->loadRequiredMenus(
            $reqMenus,
            $ancestors,
            $user_alice,
            function () {
            }
        );

        $this->assertEquals(3, count($menus['mainmenu']));
        $this->assertEquals('new sub page', $menus['mainmenu'][0]['label']);
        $this->assertEquals(
            'test page for linked snippets',
            $menus['mainmenu'][1]['label']
        );
        $this->assertEquals(
            'parent element for all test snippets',
            $menus['mainmenu'][2]['label']
        );
    }
}
