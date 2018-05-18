<?php

namespace Datahouse\Elements\Control;

use Datahouse\Elements\Abstraction\FileMeta;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Presentation\StaticDataRenderer;
use Datahouse\Libraries\JSON\Converter\Config;

/**
 * @package Datahouse\Elements\Control
 * @author Markus Wanner <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class StaticBlobController extends BaseController
{
    protected $fileMeta;

    /**
     * BaseAdminController constructor.
     *
     * @param BaseRouter         $router   invoking this controller
     * @param BaseRequestHandler $handler  in charge of the request
     * @param FileMeta           $fileMeta to display
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler,
        FileMeta $fileMeta
    ) {
        parent::__construct($router, $handler);
        $this->fileMeta = $fileMeta;
    }

    /**
     * Supports only 'GET'
     *
     * @return string[]
     */
    public function enumAllowedMethods()
    {
        return ['GET'];
    }

    /**
     * @param HttpRequest $request to process
     * @param User        $user    for which to process things
     * @return HttpResponse
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user')
     */
    public function processRequest(
        HttpRequest $request,
        User $user
    ) : HttpResponse {
        assert($request->method === 'GET');

        $adapter = $this->handler->getAdapter();
        $content = $adapter->fetchBlobContents($this->fileMeta);

        $renderer = new StaticDataRenderer();
        $optFilename = '';
        if (!is_null($this->fileMeta->getOrigFileName())) {
            $optFilename = '; filename="' .
                $this->fileMeta->getOrigFileName() . '"';
        }
        $renderer->setTemplateData([
            'content' => $content,
            'content_type' => $this->fileMeta->getMimeType(),
            'ttl' => 9999999,
            'contentDisposition' => 'attachment' . $optFilename
        ]);

        $response = new HttpResponse();
        $response->setStatusCode(200);
        $response->setRenderer($renderer);
        return $response;
    }
}
