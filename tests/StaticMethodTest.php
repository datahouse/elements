<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Control\StaticHelper;

/**
 * StaticMethodTest - tests a helper method from Constants.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class StaticMethodTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test return_bytes on simple numbers.
     *
     * @return void
     */
    public function testSimpleNumbers()
    {
        $this->assertEquals(5, StaticHelper::returnBytes('5'));
        $this->assertEquals(5000, StaticHelper::returnBytes('5000'));
    }

    /**
     * Test return_bytes on simple numbers.
     *
     * @return void
     */
    public function testSupportedUnitPrefixes()
    {
        $this->assertEquals(7 * 1024, StaticHelper::returnBytes('7k'));
        $this->assertEquals(12 * 1048576, StaticHelper::returnBytes('12m'));
    }

    /**
     * Test return_bytes on simple numbers.
     *
     * @return void
     */
    public function testSpacedUnits()
    {
        $this->assertEquals(5 * 1024, StaticHelper::returnBytes('5 k'));
        $this->assertEquals(5 * 1024, StaticHelper::returnBytes('5 kb'));
        $this->assertEquals(5 * 1048576, StaticHelper::returnBytes('5 m'));
    }

    /**
     * Test return_bytes on empty strings and spaces
     *
     * @return void
     */
    public function testEmptyString()
    {
        $this->assertEquals(0, StaticHelper::returnBytes(''));
        $this->assertEquals(0, StaticHelper::returnBytes('   '));
    }
}
