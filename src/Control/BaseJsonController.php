<?php

namespace Datahouse\Elements\Control;

use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Presentation\JsonDataRenderer;

/**
 * Abstract controller class for all kinds of requests that respond with
 * JSON data.
 *
 * @package Datahouse\Elements\Control
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
abstract class BaseJsonController extends BaseController
{
    /**
     * @param HttpRequest $request to validate
     * @param User        $user    for which to process the request
     * @return IJsonResponse
     */
    abstract public function validateRequestData(
        HttpRequest $request,
        User $user
    ) : IJsonResponse;

    /**
     * @param HttpRequest $request to process
     * @param User        $user    for which to process things
     * @return IJsonResponse
     */
    abstract protected function processJsonRequest(
        HttpRequest $request,
        User $user
    ) : IJsonResponse;

    /**
     * @param HttpRequest $request to process
     * @param User        $user    for which to process things
     * @return HttpResponse
     */
    public function processRequest(
        HttpRequest $request,
        User $user
    ) : HttpResponse {
        $result = $this->validateRequestData($request, $user);
        if ($result->isSuccess()) {
            $result = $this->processJsonRequest($request, $user);
        }

        $renderer = new JsonDataRenderer();
        $renderer->setTemplateData($result->asArray());

        $response = new HttpResponse();
        $response->setStatusCode($result->getCode());
        $response->setRenderer($renderer);
        return $response;
    }
}
