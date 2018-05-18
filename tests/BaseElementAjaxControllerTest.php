<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\BaseStorageAdapter;
use Datahouse\Elements\Abstraction\BlobStorage;
use Datahouse\Elements\Abstraction\Changes\IChange;
use Datahouse\Elements\Abstraction\Changes\Transaction;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\TransactionResult;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Admin\BaseElementAjaxController;
use Datahouse\Elements\Control\Admin\JsonAdminResponse;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\HttpResponse;
use Datahouse\Elements\Control\IChangeProcess;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\Session\ISessionHandler;
use Datahouse\Elements\Presentation\JsonDataRenderer;

/**
* @package Datahouse\Elements\Tests
* @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
* @license (c) 2016 by Datahouse AG
*/
class BaseElementAjaxControllerTest extends \PHPUnit_Framework_TestCase
{
    /* @var BaseElementAjaxController $ctrl the controller under test */
    private $ctrl;

    /**
     * Setup the controller to test with enough mocked objects for the
     * abstract base class to work.
     *
     * @return void
     */
    public function setUp()
    {
        $sh = $this->getMockBuilder(ISessionHandler::class)->getMock();
        $sh->method('getLanguage')->willReturn('de');

        $router = $this->getMockBuilder(BaseRouter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $process = $this->getMockBuilder(IChangeProcess::class)->getMock();

        $blobStorage = $this->getMockBuilder(BlobStorage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $adapter = $this->getMockForAbstractClass(
            BaseStorageAdapter::class,
            [$blobStorage]
        );

        $handler = $this->getMockBuilder(BaseRequestHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $handler->method('getChangeProcess')->willReturn($process);
        $handler->method('getAdapter')->willReturn($adapter);
        $handler->method('getSessionHandler')->willReturn($sh);

        // Not used by the abstract base class
        $element = new Element();
        $vno = 1;

        $this->ctrl = $this->getMockBuilder(BaseElementAjaxController::class)
            ->setConstructorArgs([$router, $handler, $element, $vno])
            ->getMockForAbstractClass();
    }

    /**
     * Test a mocked POST request of type text/plain.
     *
     * @return void
     */
    public function testPostPlainText()
    {
        $user = new User('alice');

        $change = $this->getMockBuilder(IChange::class)->getMock();
        $change->method('validate')->willReturn(new TransactionResult());
        $change->method('apply')->willReturn(new TransactionResult());

        $txn = new Transaction([$change]);
        $txn->setAuthor($user);
        /* @SuppressWarnings(PHPMD.UnusedFormalParameter("user", "request") */
        $this->ctrl->expects($this->any())
            ->method('getTxnPlanningFunc')->willReturn(
                function ($user, $request) use ($txn) {
                    return $txn;
                }
            );

        $request = new HttpRequest();
        $request->populateFrom([
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'text/plain; encoding=utf-8'
        ], [], [], [], '');

        /* @var HttpResponse $httpResponse */
        $httpResponse = $this->ctrl->processRequest($request, $user);
        $this->assertEquals(200, $httpResponse->getStatusCode());
        /* @var JsonDataRenderer $renderer */
        $renderer = $httpResponse->getRenderer();
        $this->assertInstanceOf(JsonDataRenderer::class, $renderer);
        $data = $renderer->getTemplateData();
        $this->assertTrue($data['success']);
        $this->assertArrayNotHasKey('message', $data);
    }

    /**
     * Test a mocked AJAX/POST request that misses a content type.
     *
     * @return void
     */
    public function testRequestMissingContentType()
    {
        $user = new User('alice');

        $request = new HttpRequest();
        $request->populateFrom([
            'REQUEST_METHOD' => 'POST',
        ], [], [], []);

        /* @var HttpResponse $httpResponse */
        $httpResponse = $this->ctrl->processRequest($request, $user);
        $this->assertEquals(400, $httpResponse->getStatusCode());
        $renderer = $httpResponse->getRenderer();
        $this->assertInstanceOf(JsonDataRenderer::class, $renderer);
    }
}
