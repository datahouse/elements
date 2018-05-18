<?php

namespace Datahouse\Elements\Presentation;

use Datahouse\Elements\Control\AssetHandler;
use Datahouse\Elements\Control\HttpRequestHandler;

/**
 * Renderer for static data responses - basically a data container that's
 * even more stupid than JsonDataRenderer.
 *
 * @package Datahouse\Elements\Presentation
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class StaticDataRenderer implements IRenderer
{
    /* @var string $contentType */
    private $contentType;
    /* @var string $content */
    private $content;
    /* @var string $etag */
    private $etag;
    /* @var int $ttl */
    private $ttl;
    /* @var string $contentDisposition */
    private $contentDisposition;

    /**
     * @param array $data for the template to render
     * @return void
     */
    public function setTemplateData(array $data)
    {
        assert(array_key_exists('content_type', $data));
        assert(array_key_exists('content', $data));
        $this->contentType = $data['content_type'];
        $this->content = $data['content'];
        $this->etag = $data['etag'] ?? null;
        $this->ttl = $data['ttl'] ?? null;
        $this->contentDisposition = $data['contentDisposition'] ?? null;
    }

    /**
     * @param AssetHandler $assetHandler to use for assets
     * @return string[] pair of string for type and content
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('assetHandler')
     */
    public function render(AssetHandler $assetHandler) : array
    {
        if (isset($this->etag)) {
            header('Etag: ' . $this->etag);
        }
        if (isset($this->ttl)) {
            HttpRequestHandler::sendCacheControlHeaders($this->ttl);
        }
        if (isset($this->contentDisposition)) {
            header('Content-Disposition: ' . $this->contentDisposition);
        }
        return [$this->contentType, $this->content];
    }
}
