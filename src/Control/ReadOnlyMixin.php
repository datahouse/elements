<?php

namespace Datahouse\Elements\Control;

use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Presentation\IRenderer;

/**
 * A helper for read-only resources (supporting only HTTP GET).
 *
 * @package Datahouse\Elements\Control
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
trait ReadOnlyMixin
{
    /**
     * Allow only get requests.
     *
     * @return array
     */
    public function enumAllowedMethods()
    {
        return ['GET'];
    }

    /**
     * @param User $user who triggered this request
     * @return IRenderer with all data required to render the result
     */
    abstract public function processGet(User $user) : IRenderer;

    /**
     * @param HttpRequest $request from the HTTP request header, uppercase
     * @param User        $user    who triggered this request
     * @return HttpResponse
     */
    public function processRequest(
        HttpRequest $request,
        User $user
    ) : HttpResponse {
        if ($request->method === 'GET') {
            $response = new HttpResponse();
            $response->setStatusCode(200);
            $response->setRenderer($this->processGet($user));
            return $response;
        } else {
            throw new \RuntimeException('Routing error');
        }
    }
}
