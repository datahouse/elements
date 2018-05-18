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
 * Admin ajax controller for removing a sub element.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class ElementRemoveSub extends BaseElementAjaxController
{
    private $language;
    private $subName;
    private $subIndex;

    /**
     * @param BaseRouter         $router   invoking this controller
     * @param BaseRequestHandler $handler  in charge of the request
     * @param Element            $element  to load or change
     * @param int                $vno      to change
     * @param string             $language of the version affected
     * @param string             $subName  of the sub-element collection
     * @param int                $subIndex of the sub-element to remove
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler,
        Element $element,
        int $vno,
        string $language,
        string $subName,
        int $subIndex
    ) {
        parent::__construct($router, $handler, $element, $vno);
        $this->language = $language;
        $this->subName = $subName;
        $this->subIndex = $subIndex;
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
            return $process->planTxnForElementRemoveSub(
                $user,
                $this->element, //parentElement which wants to add a child
                $this->vno,
                $this->language,
                $editLanguage,
                $this->subName,
                $this->subIndex
            );
        };
    }
}
