<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\IChangeProcess;
use Datahouse\Elements\Control\BaseRouter;

/**
 * Admin ajax controller for adding a sub element.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementAddSub extends BaseElementAjaxController
{
    private $language;
    private $subName;

    /**
     * @param BaseRouter         $router   invoking this controller
     * @param BaseRequestHandler $handler  in charge of the request
     * @param Element            $element  to load or change
     * @param int                $vno      to change
     * @param string             $language of the version affected
     * @param string             $subName  of the sub-element collection
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler,
        Element $element,
        int $vno,
        string $language,
        string $subName
    ) {
        parent::__construct($router, $handler, $element, $vno);
        $this->language = $language;
        $this->subName = $subName;
    }

    /**
     * @param IChangeProcess $process to use for change generation
     * @return callable function for planning the transaction
     */
    public function getTxnPlanningFunc(IChangeProcess $process) : callable
    {
        $sh = $this->handler->getSessionHandler();
        $editLanguage = !empty($sh->getLanguage())
            ? $sh->getLanguage() : $this->language;
        return function (
            User $user,
            HttpRequest $request
        ) use (
            $process,
            $editLanguage
        ) {
            return $process->planTxnForElementAddSubChange(
                $user,
                $this->element, //parentElement which wants to add a child
                $this->vno,
                $this->language,
                $editLanguage,
                $this->subName
            );
        };
    }
}
