<?php

namespace Datahouse\Elements\Control\Authentication;

use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\HttpRequest;

/**
 * Interface IAuthenticator
 *
 * All authentication tasks should be performed by an authentication
 * that implements this interface.
 *
 * @package Datahouse\Elements\Control\Authentication
 * @author  Dena Moshfegh (dmo) <dena.moshfegh@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
interface IAuthenticator
{
    /**
     * @param HttpRequest $request request containing authentication data
     * @return User|null user that could be authenticated or null
     */
    public function authenticate(HttpRequest $request);
}
