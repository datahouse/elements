<?php

namespace Datahouse\Elements\Control\Authentication;

use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\HttpRequest;

/**
 * An example authentication handler that allows the user to freely choose
 * what role he wants to incarnate. Only useful for debugging.
 *
 * @package Datahouse\Elements\Control\Authentication
 * @author  Dena Moshfegh (dmo) <dena.moshfegh@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class PasswordAuthenticator implements IAuthenticator
{
    private $adapter;

    /**
     * PasswordAuthentication constructor.
     *
     * @param IStorageAdapter $adapter to use
     */
    public function __construct(IStorageAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @param HttpRequest $request containing authentication data
     * @return User|null user that could be authenticated or null
     */
    public function authenticate(HttpRequest $request)
    {
        // FIXME return specific errors?
        //var_dump($this->validateRequest($request));
        if (!$this->validateRequest($request)) {
            return null;
        }

        $user = $this->adapter->loadUser($request->getParameter('username'));
        if (isset($user) && $this->authenticateUser($user, $request)) {
            return $user;
        } else {
            return null;
        }
    }

    /**
     * authenticate user
     *
     * @param  User        $user    to authenticate
     * @param  HttpRequest $request providing credentials
     * @return bool is valid
     * @throws \RuntimeException
     */
    private function authenticateUser(User $user, HttpRequest $request)
    {
        $secret = $user->getSecret();
        $providedPassword = $request->getParameter('password') ?? '';
        switch ($secret->type ?? '') {
            case 'plain':
                return $secret->password == $providedPassword;
            case 'pbkdf2-sha512':
                $iterations = 10000;
                $length = 64;  // will result in 32 bytes of the resulting hash
                // generated salt with base64_encode(openssl_random_pseudo_bytes(rand(16, 24)))
                $salt = $secret->salt;
                $pw_hash = hash('sha512', $providedPassword);
                $hash = hash_pbkdf2('sha512', $pw_hash, $salt, $iterations, $length);
                return $secret->hash === $hash;
            default:
                throw new \RuntimeException(
                    "Unknown password type: '" . $secret->type . "'"
                );
        }
    }

    /**
     * For a successful authentication, a username and a password is required.
     *
     * @param HttpRequest $request to validate
     * @return bool valid request
     */
    private function validateRequest(HttpRequest $request)
    {
        return $request->hasParameter('username')
            && $request->hasParameter('password');
    }
}
