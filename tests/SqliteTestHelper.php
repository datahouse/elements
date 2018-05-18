<?php

namespace Datahouse\Elements\Tests;

use Symfony\Component\Filesystem\Filesystem;
use Datahouse\Libraries\Database\ConnectionInfo;
use Datahouse\Libraries\Database\DbFactory;

use Datahouse\Elements\Abstraction\YamlAdapter;
use Datahouse\Elements\Factory;
use Datahouse\Elements\Tools\StorageDuplicator;
use Datahouse\Elements\ReFactory;

/**
 * Provides a method for setting up a temporary SQLite database by copying
 * test data from a YAML storage adatper for tests to mess with.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
trait SqliteTestHelper
{
    private $blobDir;
    private $dbPath;

    /**
     * Copyies data from a yaml test directory into an sqlite database using
     * the StorageMigrator.
     *
     * @param string $srcdir to copy from
     * @return \Datahouse\Elements\Abstraction\SqliteAdapter
     */
    public function setUpTestDb($srcdir)
    {
        $eleFactory = $this->getMockBuilder(Factory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $refactory = new ReFactory($eleFactory);

        // Clear the entire APCU cache
        apcu_clear_cache();

        // Add rules for the database library
        DbFactory::addDiceRules($this->dice);

        // Add a global JSON parser config
        $rule = new \Dice\Rule;
        $rule->shared = true;
        $rule->constructParams = [true, 512];
        $this->dice->addRule(
            'Datahouse\\Libraries\\JSON\\Converter\\Config',
            $rule
        );

        // Create the source adapter to read data from.
        $src_adapter = new YamlAdapter(
            $refactory,
            $srcdir . '/yaml',
            $srcdir . '/blobs'
        );

        // Create the target database
        $this->dbPath = __DIR__ . '/data/.tmp.sqlite.' . random_int(0, 99999)
            . '.' . explode('.', basename(__FILE__))[0] . '.db';
        @unlink($this->dbPath);   // just to make sure
        $conn_info = new ConnectionInfo\Sqlite($this->dbPath);
        $dbFactory = $this->dice->create(
            '\\Datahouse\\Libraries\\Database\\DbFactory'
        );
        $dbFactory->setupNewDatabase('custom', $conn_info, 'default');

        // copy blob files to a working directory we are free to modify
        $this->blobDir = __DIR__ . '/data/.test.blobs.' . random_int(0, 99999);
        (new Filesystem())->mirror($srcdir . '/blobs', $this->blobDir);

        // Setup an adapter for the newly created target database
        $drv = DbFactory::createDriverFor($conn_info);
        $tgt_adapter = $this->dice->create(
            '\\Datahouse\\Elements\\Abstraction\\SqliteAdapter',
            [$refactory, $drv, $this->blobDir]
        );

        // Copy data from the source to the target database
        $migrator = new StorageDuplicator($src_adapter, $tgt_adapter);
        $migrator->copyData();
        return $tgt_adapter;
    }

    /**
     * Remove the temporary database directory.
     * @return void
     */
    public function tearDown()
    {
        @unlink($this->dbPath);
        (new Filesystem())->remove($this->blobDir);
    }
}
