<?php

namespace Datahouse\Elements\Tests;

use stdClass;

use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Authentication\PasswordAuthenticator;
use Datahouse\Elements\Control\HttpRequest;

/**
 *
 * @package Datahouse\Elements\Tests
 * @author  Dena Moshfegh (dmo) <dena.moshfegh@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class PasswordAuthenticatorTest extends \PHPUnit_Framework_TestCase
{
    /* @var HttpRequest $request */
    private $request;

    /* @var stdClass $secret */
    private $secret;

    /**
     * Create a common request object and a secret used in multiple tests.
     * @return void
     */
    public function setUp()
    {
        $this->request = new HttpRequest();
        $this->request->populateFrom([
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'REQUEST_METHOD' => 'POST'
        ], [
            'username' => 'alice',
            'password' => '1234'
        ], [], []);

        $this->secret = new stdClass();
        $this->secret->type = 'plain';
        $this->secret->password = '1234';
    }

    /**
     * Prepare a mock storage adapter.
     *
     * @param User|null $user the storage adapter should return from loadUser.
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function adapterHelper($user)
    {
        $adapter = $this->getMockBuilder(IStorageAdapter::class)
                        ->getMock();

        $adapter->method('loadUser')
            ->willReturn($user);

        return $adapter;
    }

    /**
     * Try a failing authentication with an empty request.
     * @return void
     */
    public function testAuthenticateEmptyRequest()
    {
        $adapter = $this->adapterHelper(null);

        $request = new HttpRequest();
        $request->populateFrom([], [], [], []);

        $auth = new PasswordAuthenticator($adapter);
        $this->assertNull($auth->authenticate($request));
    }

    /**
     * Try a proper authentication, but against an empty storage. Should fail
     * as well.
     * @return void
     */
    public function testAuthenticateNoUser()
    {
        $adapter = $this->adapterHelper(null);

        $auth = new PasswordAuthenticator($adapter);
        $this->assertNull($auth->authenticate($this->request));
    }

    /**
     * Try a proper authentication request with a wrong password. Note that
     * this changes the stored secret rather than the request, compared to the
     * successful test below.
     * @return void
     */
    public function testAuthenticateInvalidPlainPassword()
    {
        $this->secret->password = 'wrong-password';
        $user = new User('alice', $this->secret);

        $adapter = $this->adapterHelper($user);

        $auth = new PasswordAuthenticator($adapter);
        $this->assertNull($auth->authenticate($this->request));
    }

    /**
     * Try a proper authentication request against a plain text password for
     * user alice
     * @return void
     */
    public function testAuthenticateValidPlainPassword()
    {
        $user = new User('alice', $this->secret);
        $adapter = $this->adapterHelper($user);

        $auth = new PasswordAuthenticator($adapter);
        $result = $auth->authenticate($this->request);
        $this->assertNotNull($result);
        $this->assertEquals('alice', $result->getId());
    }

    /**
     * Try an invalid secret type in storage.
     * @return void
     */
    public function testAuthenticateUnknownPasswordType()
    {
        $this->secret->type = 'undefined-auth-type';
        $user = new User('alice', $this->secret);
        $adapter = $this->adapterHelper($user);

        $this->expectException('RuntimeException');
        $auth = new PasswordAuthenticator($adapter);
        $auth->authenticate($this->request);
    }

    /**
     * Try a proper authentication request for user alice with a properly
     * salted and hashed password (using PBKDF2-SHA512).
     * @return void
     */
    public function testAuthenticatePBKDF2Password()
    {
        $credentials = new stdClass();
        $credentials->type = 'pbkdf2-sha512';
        $credentials->salt = 'SALT.';
        $credentials->hash = '8396e5a010cfd71920ccafbb98a190cbcdf4305b3ebe68ada11a60710d2f0cfc';
        $user = new User('alice', $credentials);

        $adapter = $this->adapterHelper($user);

        $auth = new PasswordAuthenticator($adapter);
        $result = $auth->authenticate($this->request);
        $this->assertNotNull($result);
        $this->assertEquals('alice', $result->getId());
    }
}
