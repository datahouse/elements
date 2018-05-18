<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\YamlAdapter;
use Datahouse\Elements\Control\Authorization\IAuthorizationHandler;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\BaseUrlResolver;
use Datahouse\Elements\Control\ContentSelection\IContentSelector;

/**
 * UrlResolver tests using the Sqlite storage adapter.
 *
 * @package Datahouse\Elements\Tests
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class UrlResolverSqliteTest extends \PHPUnit_Framework_TestCase
{
    /* @var \Dice\Dice $dice */
    protected $dice;
    /* @var YamlAdapter $adapter */
    protected $adapter;
    /* @var IContentSelector $csel */
    protected $csel;
    /* @var BaseUrlResolver $resolver */
    protected $resolver;
    /* @var IAuthorizationHandler $authorizationHandler */
    protected $authorizationHandler;

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
        $this->handler = $this->getMockBuilder(BaseRequestHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->csel = $this->getMockBuilder(IContentSelector::class)
            ->getMock();
        $this->authorizationHandler = new NullAuthorizationHandler();
        $this->resolver = new BaseUrlResolver(
            $this->handler,
            $this->adapter,
            $this->csel,
            $this->authorizationHandler
        );
    }

    use SqliteTestHelper;    // SQLite population hepler
    use UrlResolverCommon;     // actual tests
}
