<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\Exceptions\MissingFileUpload;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\IChangeProcess;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\IJsonResponse;

/**
 * @package Datahouse\Elements\Control\Admin
 * @author Helmar Tröller (htr) <helmar.troeller@datahouse.ch>
 * @license (c) 2014-2017 by Datahouse AG
 */
class FileUpload extends BaseAdminTransactionController
{
    private $collection;
    private $fileMeta;
    private $config;

    /**
     * @param BaseRouter         $router     invoking this controller
     * @param BaseRequestHandler $handler    in charge of the request
     * @param string             $collection of the upload
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler,
        string $collection
    ) {
        parent::__construct($router, $handler);
        $this->collection = $collection;
        $this->fileMeta = null;

        $refactory = $this->handler->getReFactory();
        $this->config = $refactory->getFactory()->getConfiguration();
    }

    /**
     * Allow only POST
     *
     * @return string[] of allowed methods
     */
    public function enumAllowedMethods()
    {
        return ['PUT', 'POST'];
    }

    /**
     * @param HttpRequest $request to validate
     * @param User        $user    for which to process the request
     * @return IJsonResponse
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user')
     */
    public function validateRequestData(
        HttpRequest $request,
        User $user
    ) : IJsonResponse {
        // We currently require a multipart upload and cannot handle direct
        // PUTs with the payload in the body, directly. From a RESTful
        // perspective, that's a pitty. But Froala does it that way...
        if ($request->getContentType() != 'multipart/form-data') {
            return new JsonAdminResponse(400, 'bad mime type');
        }

        try {
            $request->expectUploadsFor(['file']);
            $fileInfo = $request->getUploadedFileInfo('file');

            // An image check is being done in the editor but it is best to
            // check that again on the server side. Note that the mime type
            // in $fileInfo has already been cross-checked and cannot be
            // forged by the client.
            if ($this->collection == 'images') {
                $allowedMimeTypes = [
                    'image/gif',
                    'image/jpeg',
                    'image/pjpeg',
                    'image/png',
                    'image/x-png'
                ];
                if (!in_array($fileInfo['type'], $allowedMimeTypes)) {
                    return new JsonAdminResponse(
                        400,
                        "bad mime type for image upload: '"
                        . $fileInfo['type'] . "'"
                    );
                }
            }
        } catch (MissingFileUpload $e) {
            return new JsonAdminResponse(400, 'missing file');
        }

        return new JsonAdminResponse(200);
    }

    /**
     * @param IChangeProcess $process to use for change generation
     * @return callable function for planning the transaction
     */
    public function getTxnPlanningFunc(IChangeProcess $process) : callable
    {
        /* @SuppressWarnings(PHPMD.UnusedLocalVariable('request') */
        return function (User $user, HttpRequest $request) use ($process) {
            assert(!is_null($this->fileMeta));
            return $process->planTxnForFileUpload(
                $user,
                $this->fileMeta,
                $this->collection
            );
        };
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
        $fileInfo = $request->getUploadedFileInfo('file');
        $adapter = $this->handler->getAdapter();
        $this->fileMeta = $adapter->internalizeUploadedFile(
            $this->collection,
            $fileInfo
        );

        $result = $this->processTransaction($request, $user);
        if ($result->isSuccess()) {
            // Note that we ignore any kind of infoMessages possibly contained
            // in the transaction's result, here. Froala would ignore those,
            // anyways.
            $link = $this->config->rootUrl . '/blobs/'
                . $this->fileMeta->getCollection() . '/'
                . $this->fileMeta->getId();
            return new FileUploadResponse(200, $link);
        } else {
            // FIXME: not sure how Froala handles errors, but this should
            // match what we did so far.
            return JsonAdminResponse::fromTransactionResult($result);
        }
    }
}
