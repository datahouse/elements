<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\Exceptions\NoOpException;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\IJsonResponse;

/**
 * BaseElementAjaxController - a simplifying base class for all PUT or POST
 * requests that act on an element, trigger a storage transaction, and respond
 * with JSON.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
abstract class BaseElementAjaxController extends BaseAdminTransactionController
{
    protected $element;
    protected $vno;

    /**
     * @param BaseRouter         $router  invoking this controller
     * @param BaseRequestHandler $handler in charge of the request
     * @param Element            $element to load or change
     * @param int                $vno     version to change
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler,
        Element $element,
        int $vno
    ) {
        parent::__construct($router, $handler);
        $this->element = $element;
        $this->vno = $vno;
    }

    /**
     * Allow only POST
     *
     * @return string[] of allowed methods
     */
    public function enumAllowedMethods()
    {
        return ['POST'];
    }

    /**
     * @return string[] of allowed content types for this request
     */
    protected function enumAcceptedContentTypes() : array
    {
        return [
            'text/plain',
            'application/x-www-form-urlencoded',
            'multipart/form-data'
        ];
    }

    /**
     * Default implementation that just returns 200 without any validation.
     *
     * @param HttpRequest $request to validate
     * @param User        $user    for which to process the request
     * @return IJsonResponse
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user')
     */
    public function validateRequestData(
        HttpRequest $request,
        User $user
    ) : IJsonResponse {
        return new JsonAdminResponse(200);
    }

    /**
     * @param HttpRequest $request to process
     * @param User        $user    for which to process this request
     * @return IJsonResponse
     */
    protected function processJsonRequest(
        HttpRequest $request,
        User $user
    ) : IJsonResponse {
        $contentType = $request->getContentType() ?? '';
        $allowedContentTypes = array_flip($this->enumAcceptedContentTypes());
        if (!array_key_exists($contentType, $allowedContentTypes)) {
            return new JsonAdminResponse(
                400,
                'unknown content type: ' . $contentType
            );
        }

        try {
            $result = $this->processTransaction($request, $user);
        } catch (NoOpException $e) {
            return new JsonAdminResponse(200);
        }

        return JsonAdminResponse::fromTransactionResult($result);
    }
}
