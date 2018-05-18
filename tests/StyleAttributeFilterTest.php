<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Control\Filter\EmptyStyleAttributeFilter;
use Datahouse\Elements\Factory;
use Datahouse\Elements\ReFactory;

/**
 * Tests the empty style attribute removal filter
 *
 * @package Datahouse\Elements\Tests
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2017 by Datahouse AG
 */
class StyleAttributeFilterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param string $input input value to test
     * @param string $exp   expected result
     * @return void
     */
    private function checkImageFilter(string $input, string $exp)
    {
        $configuration = new \Datahouse\Elements\Configuration(
            'noDefaultElementDefinition',
            [],  // element definitions
            'noChangeProcessClass',
            'noStorageAdapterClass',
            [] // adapter constructor args
        );
        $configuration->rootUrl = 'http://example.com';

        $factory = $this->getMockBuilder(Factory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $refactory = new ReFactory($factory);

        $element = $this->getMockBuilder(Element::class)->getMock();
        $fieldDef = ['type' => 'text'];

        $filter = new EmptyStyleAttributeFilter($refactory);

        $result = $filter->inFilter($element, '/myplace', $fieldDef, $input);
        $this->assertEquals($exp, $result);
    }

    /**
     * Test the actual function of removing an empty style tag.
     * @return void
     */
    public function testRemoval()
    {
        $this->checkImageFilter('<p style="">Text</p>', '<p>Text</p>');
    }

    /**
     * Test a few strings the filter should not touch.
     * @return void
     */
    public function testNoOpVariants()
    {
        $this->checkImageFilter('<p>Text</p>', '<p>Text</p>');
    }
}
