<?php

namespace Datahouse\Elements\Control\Authentication;

use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\HttpRequest;

/**
 * An example authentication handler that allows the user to freely choose
 * what role he wants to incarnate. Only useful for debugging.
 *
 * @package Datahouse\Elements\Control\Authorization
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class NoOpAuthenticator implements IAuthenticator
{
    private $adapter;

    /**
     * NoOpAuthenticator constructor.
     *
     * @param IStorageAdapter $adapter to use
     */
    public function __construct(IStorageAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Being a no-op authenticator, we allow every username given.
     *
     * @param HttpRequest $request containing authentication data
     * @return User|null user that could be authenticated or null
     */
    public function authenticate(HttpRequest $request)
    {
        $user_id = $request->getParameter('username');
        return isset($user_id) ? $this->adapter->loadUser($user_id) : null;
    }
}
