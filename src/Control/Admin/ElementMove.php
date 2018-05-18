<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\BaseStorageAdapter;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\IChangeProcess;
use Datahouse\Elements\Control\IJsonResponse;

/**
 * Admin ajax controller for moving an element within the tree.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementMove extends BaseElementAjaxController
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
     * @param HttpRequest $request with input parameters
     * @return array
     */
    private function parseParameters(HttpRequest $request)
    {
        $newParentId = $request->getParameter('newParentId')
            ?? $this->element->getParentId();
        $insertBefore = $request->getParameter('insertBefore') ?? null;
        return [$newParentId, $insertBefore];
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
        list($newParentId, $insertBefore) = $this->parseParameters($request);

        if (!BaseStorageAdapter::isValidElementId($newParentId)) {
            return new JsonAdminResponse(400, 'invalid parent element id');
        }

        if ($newParentId != $this->element->getParentId()) {
            // FIXME: move between parents is not implemented, yet.
            return new JsonAdminResponse(400, 'cannot relocate to new parent');
        }

        $adapter = $this->handler->getAdapter();
        /* @var Element $parent */
        $parent = $adapter->loadElement($newParentId);
        assert(isset($parent));

        if ($insertBefore) {
            $prevElement = $adapter->loadElement($insertBefore);
            if (is_null($prevElement)) {
                return new JsonAdminResponse(400, 'insertion point not found');
            }
        }

        return new JsonAdminResponse(200);
    }

    /**
     * @param IChangeProcess $process to use for change generation
     * @return callable planned
     */
    public function getTxnPlanningFunc(IChangeProcess $process) : callable
    {
        return function (User $user, HttpRequest $request) use ($process) {
            list($newParentId, $insertBefore)
                = $this->parseParameters($request);
            $adapter = $this->handler->getAdapter();
            /* @var Element $oldParent, $newParent */
            $oldParent = $adapter->loadElement($this->element->getParentId());
            assert(isset($oldParent));
            $newParent = $adapter->loadElement($newParentId);
            assert(isset($newParent));
            return $process->planTxnForElementMove(
                $user,
                $this->element,
                $this->vno,
                $oldParent,
                $newParent,
                $insertBefore
            );
        };
    }
}
