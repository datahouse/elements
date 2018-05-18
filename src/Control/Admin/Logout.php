<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\BaseController;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\HttpResponse;
use Datahouse\Elements\Presentation\NoDataRenderer;

/**
 * Logout controller (ajax!)
 *
 * @package Datahouse\Elements\Control\Admin
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class Logout extends BaseController
{
    /**
     * Supports only DELETE, preferably on session/<session_id> for caching
     * to work.
     *
     * @return string[]
     */
    public function enumAllowedMethods()
    {
        return ['DELETE'];
    }

    /**
     * @param HttpRequest $request to process
     * @param User        $user    who triggered this request
     * @return HttpResponse with all data required to render the result
     */
    public function processRequest(
        HttpRequest $request,
        User $user
    ) : HttpResponse {
        assert($request->method === 'DELETE');

        $this->requireAuthenticated($user);

        $sh = $this->handler->getSessionHandler();
        $sh->unsetUser();

        $response = new HttpResponse();
        $response->setStatusCode(200);
        $response->setRenderer(new NoDataRenderer());
        return $response;
    }
}
