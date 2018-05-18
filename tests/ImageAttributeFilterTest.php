<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\FileMeta;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Configuration;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\Filter\ImageAttributeFilter;
use Datahouse\Elements\Factory;
use Datahouse\Elements\ReFactory;

/**
 * Tests the image attribute filter.
 *
 * @package Datahouse\Elements\Tests
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2017 by Datahouse AG
 */
class ImageAttributeFilterTest extends \PHPUnit_Framework_TestCase
{
    /* @var Configuration $configuration */
    private $configuration;
    const FAKE_META_ID = '1111111111111111111111111111111111111111';
    const FAKE_FILE_HASH = '2222222222222222222222222222222222222222';
    const FAKE_ORIG_FILE_NAME = 'test.png';

    protected function setUp()
    {
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
     * @param string $input input value to test
     * @param string $exp   expected result
     * @return void
     */
    private function checkImageFilter(string $input, string $exp)
    {
        $fakeFileMeta = new FileMeta(static::FAKE_META_ID);
        $fakeFileMeta->populate(
            static::FAKE_FILE_HASH,
            'images',        // never mind the collection
            static::FAKE_ORIG_FILE_NAME,
            'image/jpeg',    // mime type
            42               // fake objects tend to have weird sizes
        );

        $adapter = $this->getMockBuilder(IStorageAdapter::class)
            ->getMock();
        $adapter->method('loadFileMeta')->willReturn($fakeFileMeta);

        $factory = $this->getMockBuilder(Factory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $factory->method('getConfiguration')->willReturn($this->configuration);
        $factory->method('getStorageAdapter')->willReturn($adapter);
        $refactory = new ReFactory($factory);

        $element = $this->getMockBuilder(Element::class)->getMock();
        $fieldDef = ['type' => 'image'];

        $router = $this->getMockBuilder(BaseRouter::class)
            ->disableOriginalConstructor()
            ->getMock();
        $router->method('getLinkedFileMetaId')
            ->will($this->returnCallback(function (string $url) {
                switch (explode('/', $url)[1]) {
                    case 'static':
                    case 'notexample.com':
                    case 'image.jpg':
                        return null;
                    case 'blobs':
                    case 'blob':
                    case 'image':
                    case 'document':
                    case 'example.com':
                        return self::FAKE_META_ID;
                    default:
                        $this->fail("called mocked getLinkedFileMetaId with unknown path: $url");
                }
            }));

        $filter = new ImageAttributeFilter($refactory, $router, $adapter);
        $result = $filter->inFilter($element, '/', $fieldDef, $input);
        $this->assertEquals($exp, $result);
    }

    /**
     * Test a few strings the filter should not touch.
     * @return void
     */
    public function testNoOpVariants()
    {
        // Test a no-op variant.
        $this->checkImageFilter(
            '<img src="/static/some.jpg"/>',
            '<img src="/static/some.jpg"/>'
        );

        // Ensure other tags remain intact.
        $this->checkImageFilter(
            '<other xid="83" src="/static/some.jpg"></other>',
            '<other xid="83" src="/static/some.jpg"></other>'
        );
    }

    /**
     * Check elimination of the separate closing tag.
     * @return void
     */
    public function testClosingTagElimination()
    {
        $this->checkImageFilter(
            '<img src="/static/some.jpg"></img>',
            '<img src="/static/some.jpg"/>'
        );
    }

    /**
     * Test stripping of attributes.
     * @return void
     */
    public function testAttributeStripping()
    {
        $this->checkImageFilter(
            '<img xid="83" src="/static/some.jpg"></img>',
            '<img src="/static/some.jpg"/>'
        );
    }

    /**
     * @return void
     */
    public function testEmptySrcAttribute()
    {
        $this->checkImageFilter('<img src=""/>', '<img src=""/>');
    }

    /**
     * @return void
     */
    public function testImageTagAttributesWithDashes()
    {
        $this->checkImageFilter(
            '<img data-message="added file metadata" src="/image.jpg"/>',
            '<img src="/image.jpg"/>'
        );
    }

    /**
     * @return void
     */
    public function testUnclosedImageTag()
    {
        $this->checkImageFilter(
            '<img data-message="added file metadata" src="/static">',
            '<img src="/static"/>'
        );
    }

    /**
     * @return void
     */
    public function testSpacesBetweenTags()
    {
        $this->checkImageFilter(
            '<img data-message="added file metadata" src="/static">  </img>',
            '<img src="/static"/>'
        );
    }

    /**
     * @return void
     */
    public function testSpaceAtEndExternalLink()
    {
        $this->checkImageFilter(
            '<img src="http://notexample.com/image" />',
            '<img src="http://notexample.com/image"/>'
        );
    }

    /**
     * @return void
     */
    public function testInternalAbsoluteLink()
    {
        $this->checkImageFilter(
            '<img src="http://example.com/blobs/images/' . static::FAKE_META_ID
            . '/' . static::FAKE_ORIG_FILE_NAME . '" />',
            '<img src="filemeta:' . static::FAKE_META_ID . '"/>'
        );
    }
}
