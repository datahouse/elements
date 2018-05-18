<?php

namespace Datahouse\Elements\Control;

use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Exceptions\AccessDenied;

/**
 * Abstract controller class - used for admin frontend and element displaying.
 *
 * @package Datahouse\Elements\Control
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
abstract class BaseController implements IController
{
    /** @var BaseRouter $router invoking this controller */
    protected $router;

    /** @var BaseRequestHandler $handler invoking this controller */
    protected $handler;

    /**
     * BaseAdminController constructor.
     *
     * @param BaseRouter         $router  invoking this controller
     * @param BaseRequestHandler $handler in charge of the request
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler
    ) {
        $this->router = $router;
        $this->handler = $handler;
    }

    /**
     * A helper routine that throws an exception if the user isn't logged in.
     *
     * @param User $user to check
     * @return void
     * @throws AccessDenied
     */
    protected function requireAuthenticated(User $user)
    {
        if ($user->isAnonymousUser()) {
            throw new AccessDenied('Permission denied');
        }
    }
}
