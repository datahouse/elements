<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\EleDefRegistry;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\IChangeProcess;
use Datahouse\Elements\Control\IJsonResponse;

/**
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementTemplate extends BaseElementAjaxController
{
    /* @var EleDefRegistry $eleDefRegistry */
    protected $eleDefRegistry;

    /**
     * @param BaseRouter         $router  invoking this controller
     * @param BaseRequestHandler $handler in charge of the request
     * @param Element            $element to load or change
     * @param int                $vno     version to change
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler,
        Element $element,
        $vno
    ) {
        parent::__construct($router, $handler, $element, $vno);
        $factory = $this->handler->getReFactory()->getFactory();
        $this->eleDefRegistry = $factory->createClass(EleDefRegistry::class);
    }

    /**
     * @param HttpRequest $request to validate
     * @param User        $user    for which to process the request
     * @return IJsonResponse
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user')
     */
    public function validateRequestData(
        HttpRequest $request,
        User $user
    ) : IJsonResponse {
        $name = trim($request->getBody() ?? '');
        $pageDefinitions = $this->eleDefRegistry->enumPageDefIds();
        if (array_key_exists($name, $pageDefinitions)) {
            return new JsonAdminResponse(200);
        } else {
            return new JsonAdminResponse(400, 'invalid template');
        }
    }

    /**
     * @param IChangeProcess $process to use for change generation
     * @return callable function for planning the transaction
     */
    public function getTxnPlanningFunc(IChangeProcess $process) : callable
    {
        return function (User $user, HttpRequest $request) use ($process) {
            return $process->planTxnForElementTemplateChange(
                $user,
                $this->element,
                $this->vno,
                trim($request->getBody())
            );
        };
    }
}
