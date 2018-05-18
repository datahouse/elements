<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\YamlAdapter;

/**
 * HttpRequestHandler tests using the Sqlite storage adapter.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class BaseRequestHandlerSqliteTest extends \PHPUnit_Framework_TestCase
{
    /** @var \Dice\Dice $dice */
    private $dice;
    /** @var YamlAdapter $adapter */
    private $adapter;

    /**
     * Clear caches and creates a test database we can modify and throw away
     * after the tests.
     *
     * @return void
     */
    public function setUp()
    {
        $this->dice = new \Dice\Dice;
        $this->adapter = $this->setUpTestDb(__DIR__ . '/data/1');
        $this->setUpTestObject();
    }

    use SqliteTestHelper;           // SQLite population hepler
    use BaseRequestHandlerCommon;     // actual tests
}
