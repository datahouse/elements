<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\IChangeProcess;
use Datahouse\Elements\Control\IJsonResponse;

/**
 * @package Datahouse\Elements\Control\Admin
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class ElementState extends BaseElementAjaxController
{
    /**
     * @param HttpRequest $request to process
     * @return array a tuple with the new state and a map of referenced
     *               elements to change as well.
     */
    private function parseRequest(HttpRequest $request)
    {
        $data = json_decode($request->getBody(), true);
        $newState = $data['new_state'] ?? '';

        $references = $data['references'] ?? [];
        return [$newState, $references];
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
        list ($newState,) = $this->parseRequest($request);

        $process = $this->handler->getChangeProcess();
        $knownStates = $process->enumAllowedStates();
        if (array_key_exists($newState, $knownStates)) {
            return new JsonAdminResponse(200);
        } else {
            return new JsonAdminResponse(400, 'invalid state');
        }
    }

    /**
     * @param IChangeProcess $process to use for change generation
     * @return callable function for planning the transaction
     */
    public function getTxnPlanningFunc(IChangeProcess $process) : callable
    {
        $adapter = $this->handler->getAdapter();
        return function (
            User $user,
            HttpRequest $request
        ) use (
            $process,
            $adapter
        ) {
            list ($newState, $references) = $this->parseRequest($request);
            foreach (array_keys($references) as $elementId) {
                $refEle = $adapter->loadElement($elementId);
                $references[$elementId]['element'] = $refEle;
            }
            return $process->planTxnForElementStateChange(
                $user,
                $this->element,
                $this->vno,
                $newState,
                $references
            );
        };
    }
}
