<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Control\Filter\ClassAttributeFilter;

/**
 * Tests basic Twig capabilities as well as our custom extensions.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2017 by Datahouse AG
 */
class ClassAttributeFilterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Check some trivia against the ClassAttributeFilter
     *
     * @return void
     */
    public function testTrivia()
    {
        $f = new ClassAttributeFilter();
        $this->assertEquals("", $f->stripElementsCssClasses(""));
        $this->assertEquals("foo", $f->stripElementsCssClasses("foo"));
    }

    /**
     * Ensure the filter keeps some non-css class strings untouched.
     *
     * @return void
     */
    public function testNonCssClassStrings()
    {
        $f = new ClassAttributeFilter();
        $this->assertEquals(
            "__ele_field-some",
            $f->stripElementsCssClasses("__ele_field-some")
        );
        $this->assertEquals(
            '"__ele_field-some"',
            $f->stripElementsCssClasses('"__ele_field-some"')
        );

        $funnyVal = "<p\nclass=\"\nbefore    x\n after\n  \"\n style=\"\"\n>";
        $this->assertEquals($funnyVal, $f->stripElementsCssClasses($funnyVal));
    }

    /**
     * Test a few simple replacement variants.
     *
     * @return void
     */
    public function testSimpleReplace()
    {
        $f = new ClassAttributeFilter();
        $this->assertEquals(
            '<p class="">',
            $f->stripElementsCssClasses('<p class="__ele_field-some">')
        );

        $this->assertEquals(
            '<p class="">',
            $f->stripElementsCssClasses('<p class="  __ele_field-some ">')
        );

        $this->assertEquals(
            "<p\nclass=\"\"\n>",
            $f->stripElementsCssClasses("<p\nclass=\"  __ele_field-some\n \"\n>")
        );
    }

    /**
     * Test a few simple replacement variants.
     *
     * @return void
     */
    public function testMultipleClassReplaces()
    {
        $f = new ClassAttributeFilter();
        $this->assertEquals(
            '<p class="before">',
            $f->stripElementsCssClasses('<p class="before __ele_field-some">')
        );

        $this->assertEquals(
            '<p class="after">',
            $f->stripElementsCssClasses('<p class=" __ele_field-some after">')
        );

        $this->assertEquals(
            "<p class=\"before and after\"\n>",
            $f->stripElementsCssClasses(
                "<p class=\"\nbefore and\n__ele_field-some after\"\n>"
            )
        );
    }
}