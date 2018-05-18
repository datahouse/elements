<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\FileMeta;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\UrlPointer;
use Datahouse\Elements\Configuration;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\BaseUrlResolver;
use Datahouse\Elements\Control\Filter\InternalLinkFilter;
use Datahouse\Elements\Factory;
use Datahouse\Elements\ReFactory;

/**
 * Tests internal link filters.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2017 by Datahouse AG
 */
class InternalLinkFilterTest extends \PHPUnit_Framework_TestCase
{
    /* @var Configuration $configuration */
    private $configuration;
    const FAKE_ELEMENT_ID = '1111111111111111111111111111111111111111';
    const FAKE_PARENT_ID = '2222222222222222222222222222222222222222';
    const FAKE_META_ID = '3333333333333333333333333333333333333333';
    const FAKE_FILE_HASH = '4444444444444444444444444444444444444444';
    const FAKE_ORIG_FILE_NAME = 'test.pdf';

    /**
     * setUp
     *
     * @return void
     */
    protected function setUp()
    {
        parent::setUp();
        $this->configuration = new Configuration(
            'noDefaultElementDefinition',
            [],  // element definitions
            'noChangeProcessClass',
            'noStorageAdapterClass',
            [] // adapter constructor args
        );
        $this->configuration->rootUrl = 'http://example.com';
    }

    /**
     * @param string $input to test
     * @param string $exp   expected result
     * @param string $out   expected result after outputfilter
     * @return void
     */
    private function checkFilterRoundtrip(
        string $input,
        string $exp,
        string $out
    ) {
        $testElement = $this->getMockBuilder(Element::class)->getMock();
        $testElement->method('getId')->willReturn(static::FAKE_ELEMENT_ID);
        $testElement->method('getParentId')->willReturn(static::FAKE_PARENT_ID);
        $fieldDef = ['type' => 'text'];

        $adapter = $this->getMockBuilder(IStorageAdapter::class)
            ->disableOriginalConstructor()
            ->getMock();
        $adapter->method('loadElement')
            ->willReturn($testElement);

        $fakeFileMeta = new FileMeta(static::FAKE_META_ID);
        $fakeFileMeta->populate(
            static::FAKE_FILE_HASH,
            'images',        // never mind the collection
            static::FAKE_ORIG_FILE_NAME,
            'image/jpeg',    // mime type
            42               // fake objects tend to have weird sizes
        );

        $adapter->method('loadFileMeta')->willReturn($fakeFileMeta);

        $factory = $this->getMockBuilder(Factory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $factory->method('getConfiguration')->willReturn($this->configuration);
        $factory->method('getStorageAdapter')
            ->willReturn($adapter);
        $refactory = new ReFactory($factory);

        $resolver = $this->getMockBuilder(BaseUrlResolver::class)
            ->disableOriginalConstructor()
            ->setMethods(['getLinkForElement', 'lookupUrl'])
            ->getMock();

        $resolver->method('getLinkForElement')
            ->will($this->returnCallback(function (Element $element) {
                switch ($element->getId()) {
                    case static::FAKE_PARENT_ID:
                        return new UrlPointer('/', ['en']);
                    case static::FAKE_ELEMENT_ID:
                        return new UrlPointer('/somelink', ['en']);
                    default:
                        $this->fail(
                            "called mocked getLinkForElement on unknown " .
                            "element " . $element->getId()
                        );
                }
            }));

        $resolver->method('lookupUrl')
            ->will($this->returnCallback(function (string $url) {
                switch ($url) {
                    case '/':
                        return [static::FAKE_PARENT_ID, null];
                    case '/somelink':
                        return [static::FAKE_ELEMENT_ID, null];
                    default:
                        $this->fail(
                            "called mocked lookupUrl on unknown " .
                            "url $url"
                        );
                }
            }));

        $router = $this->getMockBuilder(BaseRouter::class)
            ->disableOriginalConstructor()
            ->getMock();
        $router->method('getLinkedFileMetaId')
            ->will($this->returnCallback(function (string $url) {
                switch (explode('/', $url)[1]) {
                    case 'blobs':
                    case 'blob':
                    case 'example.com':
                        return self::FAKE_META_ID;
                    default:
                        return null;
                }
            }));

        $filter = new InternalLinkFilter($refactory, $router, $resolver);
        $result = $filter->inFilter($testElement, '/', $fieldDef, $input);
        $this->assertEquals($exp, $result);

        $result2 = $filter->outFilter($fieldDef, $result, 'en');
        $this->assertEquals($out, $result2);
    }

    /**
     * @return void
     */
    public function testAnchorNoOp()
    {
        $this->checkFilterRoundtrip(
            '<a href="#anchor">some link</a>',
            '<a href="#anchor">some link</a>',
            '<a href="#anchor">some link</a>'
        );
    }

    /**
     * @return void
     */
    public function testSimpleLink()
    {
        $this->checkFilterRoundtrip(
            '<a href="/somelink">some link</a>',
            '<a href="element:' . static::FAKE_ELEMENT_ID . '">some link</a>',
            '<a href="' . $this->configuration->rootUrl .'/somelink">some link</a>'
        );
    }

    /**
     * @return void
     */
    public function testLinkWithAnchor()
    {
        $this->checkFilterRoundtrip(
            '<a href="/somelink#anchor">some link</a>',
            '<a href="element:' . static::FAKE_ELEMENT_ID . '#anchor">some link</a>',
            '<a href="' . $this->configuration->rootUrl .'/somelink#anchor">some link</a>'
        );
    }

    /**
     * @return void
     */
    public function testLinkWithAdditionalAttributes()
    {
        $this->checkFilterRoundtrip(
            '<a id="mylink" href="/somelink" class="mystyle">some link</a>',
            '<a id="mylink" href="element:' . static::FAKE_ELEMENT_ID . '" class="mystyle">some link</a>',
            '<a id="mylink" href="' . $this->configuration->rootUrl .'/somelink" class="mystyle">some link</a>'
        );
    }

    /**
     * @return void
     */
    public function testLinkWithDocument()
    {
        $this->checkFilterRoundtrip(
            '<a href="/blobs/document/someId">some link</a>',
            '<a href="filemeta:' . static::FAKE_META_ID . '">some link</a>',
            '<a href="' . $this->configuration->rootUrl .'/blobs/document/' . static::FAKE_META_ID
                . '/' . static::FAKE_ORIG_FILE_NAME . '">some link</a>'
        );
    }
}
