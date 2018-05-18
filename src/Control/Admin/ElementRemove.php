<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\Changes\Transaction;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\IChangeProcess;
use Datahouse\Elements\Control\IJsonResponse;

/**
 * Admin ajax controller for removing an element from the tree.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class ElementRemove extends BaseElementAjaxController
{
    /**
     * @param BaseRouter         $router  invoking this controller
     * @param BaseRequestHandler $handler in charge of the request
     * @param Element            $element to remove
     * @param int                $vno     for cross checking
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler,
        Element $element,
        int $vno
    ) {
        parent::__construct($router, $handler, $element, $vno);
    }

    /**
     * @param IChangeProcess $process to use for change generation
     * @return callable planned
     */
    public function getTxnPlanningFunc(IChangeProcess $process) : callable
    {
        return function (User $user, HttpRequest $request) use ($process) {
            return $process->planTxnForElementRemove(
                $user,
                $this->element,
                $this->vno
            );
        };
    }
}
