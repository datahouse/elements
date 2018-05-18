<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\FileMeta;

/**
 * @package Datahouse\Elements\Tests
 * @author Markus Wanner <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class FileMetaTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test a couple of common extensions.
     * @return void
     */
    public function testExtensions()
    {
        $fakeId = '1111111111111111111111111111111111111111';
        $fakeHash = '2222222222222222222222222222222222222222';
        $fm = new FileMeta($fakeId);
        $fm->populate($fakeHash, 'images', 'test.jpg', 'image/jpeg', 38672);
        $this->assertEquals('.jpg', $fm->getExtension());

        $fm->populate($fakeHash, 'images', 'test.jpeg', 'image/jpeg', 38672);
        $this->assertEquals('.jpeg', $fm->getExtension());

        $fm->populate($fakeHash, 'images', 'test.png', 'image/png', 38672);
        $this->assertEquals('.png', $fm->getExtension());
    }
}
