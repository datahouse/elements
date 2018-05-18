<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\IChangeProcess;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\IJsonResponse;

/**
 * @package Datahouse\Elements\Control\Admin
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class ElementRename extends BaseElementAjaxController
{
    private $language;

    /**
     * @param BaseRouter         $router   invoking this controller
     * @param BaseRequestHandler $handler  in charge of the request
     * @param Element            $element  to load or change
     * @param int                $vno      version to change
     * @param string             $language of the version affected
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler,
        Element $element,
        int $vno,
        string $language
    ) {
        parent::__construct($router, $handler, $element, $vno);
        $this->language = $language;
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
        $body = $request->getBody();
        if (is_null($body)) {
            return new JsonAdminResponse(
                400,
                'missing contents for element to store'
            );
        }

        if (empty($this->language)) {
            return new JsonAdminResponse(
                400,
                'missing language indication for element name to store'
            );
        } else {
            return new JsonAdminResponse(200);
        }
    }

    /**
     * @param IChangeProcess $process to use for change generation
     * @return callable function for planning the transaction
     */
    public function getTxnPlanningFunc(IChangeProcess $process) : callable
    {
        $sessLanguage = $this->handler->getSessionHandler()->getLanguage();
        $editLanguage = empty($sessLanguage) ? $this->language : $sessLanguage;
        return function (
            User $user,
            HttpRequest $request
        ) use (
            $process,
            $editLanguage
        ) {
            $newContent = $request->getBody();
            // Inputs are valid and the element exists. Ask the IChangeProcess
            // to generate a transaction representing this change.
            return $process->planTxnForElementContentChange(
                $user,
                $this->element,
                $this->vno,
                $this->language,
                $editLanguage,
                ['name'],
                $newContent
            );
        };
    }
}
