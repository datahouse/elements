<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\Slug;

/**
 * @package Datahouse\Elements\Tests
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2017 by Datahouse AG
 */
class SlugTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests a valid slug.
     * @return void
     */
    public function testValidSlugs()
    {
        $slug = new Slug();
        $slug->language = 'en';

        $slug->url = 'test';
        $this->assertTrue($slug->isValid());

        $slug->url = '/test';
        $this->assertTrue($slug->isValid());

        // The plain slash is a valid delimiter in slugs, only disallowed in
        // its encoded form.
        $slug->url = '/';
        $this->assertTrue($slug->isValid());
    }

    /**
     * Tests various invalid slugs.
     * @return void
     */
    public function testInvalidSlugs()
    {
        $slug = new Slug();
        $slug->language = 'en';

        $slug->url = '%7';   // incomplete code
        $this->assertFalse($slug->isValid());

        $slug->url = '%0d';  // carriage return
        $this->assertFalse($slug->isValid());
        $slug->url = '%23';  // pound
        $this->assertFalse($slug->isValid());
        $slug->url = '%25';  // percent
        $this->assertFalse($slug->isValid());
        $slug->url = '%2f';  // slash (encoded)
        $this->assertFalse($slug->isValid());
        $slug->url = '%3f';  // question mark
        $this->assertFalse($slug->isValid());

        $slug = new Slug();
        $slug->language = '';
        $slug->url = '/test';
        $this->assertFalse($slug->isValid());
    }

    public function testPostfixMutation()
    {
        $f = function ($v) { return Slug::incrementSlugPostfixNumber($v); };
        $this->assertEquals('test_2', $f('test'));
        $this->assertEquals('test_3', $f('test_2'));
        $this->assertEquals('test_2', $f('test_1'));
        $this->assertEquals('test1_2', $f('test1'));
        $this->assertEquals('_2', $f(''));
        $this->assertEquals('2017_2', $f('2017'));
        $this->assertEquals('_2018', $f('_2017'));
    }
}
