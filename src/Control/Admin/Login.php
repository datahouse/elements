<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Exceptions\Redirection;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\HttpResponse;

/**
 * Login controller
 *
 * @package Datahouse\Elements\Control\Admin
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class Login extends AdminPageController
{
    /**
     * @return string[]
     */
    public function enumAllowedMethods()
    {
        return ['GET', 'POST'];
    }

    /**
     * @param HttpRequest $request to process
     * @param User        $user    who triggered this request
     * @return HttpResponse
     * @throws Redirection
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("user"))
     */
    public function processRequest(
        HttpRequest $request,
        User $user
    ) : HttpResponse {
        $response = [];

        if ($request->method === 'POST') {
            $authenticator = $this->handler->getAuthenticator();
            $sessionHandler = $this->handler->getSessionHandler();
            $user = $authenticator->authenticate($request);
            if (isset($user)) {
                $sessionHandler->setUser(
                    $user->getId(),
                    // for now, all non-anonymous users are admins.
                    !$user->isAnonymousUser()
                );
                throw new Redirection(303, 'tree');
            } else {
                $response['error'] = 'Username or password is invalid';
            }

            $response['permisions'] = ['admin' => true];
        }

        $renderer = $this->getAdminRenderer();
        $renderer->setTemplateData($response);

        $response = new HttpResponse();
        $response->setStatusCode(200);
        $response->setRenderer($renderer);
        return $response;
    }
}
