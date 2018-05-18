<?php

namespace Datahouse\Elements\Presentation;

use Datahouse\Elements\Control\AssetHandler;

/**
 * Renderer for JSON responses - basically a data container that's capable
 * of returning its data in JSON format.
 *
 * @package Datahouse\Elements\Presentation
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class JsonDataRenderer implements IRenderer
{
    /** @var array $data to emit in json form */
    private $data;

    /**
     * @param array $data for the template to render, should be an array
     * @return void
     */
    public function setTemplateData(array $data)
    {
        // this template wants to serialize an array to json
        assert(is_array($data));
        $this->data = $data;
    }

    /**
     * @return array template data set - only used for testing
     */
    public function getTemplateData() : array
    {
        return $this->data;
    }

    /**
     * Returns the given data array in json encoded form.
     *
     * @param AssetHandler $assetHandler to use for assets
     * @return string[] pair of string for type and content
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('assetHandler')
     */
    public function render(AssetHandler $assetHandler) : array
    {
        return ['application/json', json_encode($this->data)];
    }
}
