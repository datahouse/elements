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
 * Controller for setting references.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementSetReference extends BaseElementAjaxController
{
    protected $refDef;
    protected $refName;

    /**
     * @param BaseRouter         $router  invoking this controller
     * @param BaseRequestHandler $handler in charge of the request
     * @param Element            $element to load or change
     * @param int                $vno     version to change
     * @param array              $refDef  definition of the reference
     * @param string             $refName name of the reference to set
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler,
        Element $element,
        int $vno,
        array $refDef,
        string $refName
    ) {
        parent::__construct($router, $handler, $element, $vno);
        $this->refDef = $refDef;
        $this->refName = $refName;
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
        $newTarget = trim($request->getBody());

        if (!BaseStorageAdapter::isValidElementId($newTarget)) {
            return new JsonAdminResponse(400, 'invalid element id');
        }

        $refElement = $this->handler->getAdapter()->loadElement($newTarget);
        if (is_null($refElement)) {
            return new JsonAdminResponse(400, 'not a valid selection');
        } else {
            $collector = $this->handler->getContentCollector();
            $ancestors = array_map(function (Element $element) {
                return $element->getId();
            }, $collector->loadAncestors($refElement));
            if (!in_array($this->refDef['parent'], $ancestors)) {
                return new JsonAdminResponse(
                    400,
                    'booh'
                );
            } else {
                return new JsonAdminResponse(200);
            }
        }
    }

    /**
     * @param IChangeProcess $process to use
     * @return callable function for planning the transaction
     */
    public function getTxnPlanningFunc(IChangeProcess $process) : callable
    {
        return function (User $user, HttpRequest $request) use ($process) {
            return $process->planTxnForElementSetReference(
                $user,
                $this->element,
                $this->vno,
                $this->refName,
                trim($request->getBody())
            );
        };
    }
}
