<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Constants;
use Datahouse\Elements\Control\BaseController;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\ReadOnlyMixin;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Presentation\IRenderer;
use Datahouse\Elements\Presentation\JsonDataRenderer;

/**
 * Tree data controller, serving a REST request.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class TreeData extends BaseController
{
    use ReadOnlyMixin;

    /**
     * @param BaseRouter         $router  invoking this controller
     * @param BaseRequestHandler $handler in charge of the request
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler
    ) {
        parent::__construct($router, $handler);
    }

    /**
     * @param User $user who triggered this request
     * @return IRenderer with all data required to render the result
     */
    public function processGet(User $user) : IRenderer
    {
        $this->requireAuthenticated($user);

        $adapter = $this->handler->getAdapter();
        $rootElement = $adapter->loadElement(Constants::ROOT_ELEMENT_ID);

        assert(!is_null($rootElement));
        $collector = $this->handler->getContentCollector();
        $tree = $collector->assembleMenuFrom(
            'edit',
            $rootElement,
            [],     // no active children
            null,   // no depth limit
            $user,
            function ($element, $lang, &$entry) {
                $this->handler->refineMenuEntry(
                    $element,
                    $lang,
                    $entry
                );
            }
        );

        $template = new JsonDataRenderer();
        $template->setTemplateData($tree);
        return $template;
    }
}
