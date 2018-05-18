<?php

namespace Datahouse\Elements\Tests;

use Symfony\Component\Filesystem\Filesystem;

use Datahouse\Elements\Abstraction\YamlAdapter;
use Datahouse\Elements\ReFactory;
use Datahouse\Elements\Factory;

/**
 * Provides a method for setting up a temporary SQLite database by copying
 * test data from a YAML storage adatper for tests to mess with.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
trait YamlTestHelper
{
    private $toplevel;

    /**
     * @param string $file   including this trait
     * @param string $srcdir path to yaml data
     * @return YamlAdapter ready for testing and messing with
     */
    public function setUpTestDb(string $file, string $srcdir) : YamlAdapter
    {
        $factory = $this->getMockBuilder(Factory::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Clear the entire APCU cache
        apcu_clear_cache();

        // copy files to a working directory we are free to modify
        $this->toplevel = __DIR__ . '/data/.tmp.yaml.' . random_int(0, 99999)
            . '.' . explode('.', basename($file))[0];
        // clear the directory, if it is not empty due to a previous test
        (new Filesystem())->remove($this->toplevel);
        // copy test data
        (new Filesystem())->mirror($srcdir, $this->toplevel);

        return new YamlAdapter(
            new ReFactory($factory),
            $this->toplevel . '/yaml',
            $this->toplevel . '/blobs'
        );
    }

    /**
     * Remove the temporary database directory.
     * @return void
     */
    public function tearDown()
    {
        (new Filesystem())->remove($this->toplevel);
    }
}
