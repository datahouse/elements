<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\BaseStorageAdapter;
use Datahouse\Elements\Control\AssetHandler;
use Datahouse\Elements\Presentation\TwigRenderer;

/**
 * Base class for twig template tests.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
abstract class TwigRendererCommon extends \PHPUnit_Framework_TestCase
{
    private $assetHandler;
    private $fakeIdCounter;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->fakeIdCounter = 0;
    }

    /**
     * @return void
     */
    public function setUp()
    {
        putenv("ROOT_URL=http://www.example.com");
        $this->assetHandler = new AssetHandler(false);
    }

    /**
     * @param string $filename     of the HTML template to use
     * @param array  $templateData to pass to the renderer
     * @return TwigRenderer
     */
    public function triggerRenderer(
        string $filename,
        array $templateData
    ) {
        $renderer = new TwigRenderer($filename);
        $renderer->addTemplateDirectory(__DIR__ . '/data/templates');
        $renderer->setTemplateData($templateData);

        list ($contentType, $content) = $renderer->render($this->assetHandler);
        $this->assertEquals('text/html', $contentType);
        return $content;
    }

    /**
     * @return string a valid but fake (element) id
     */
    public function genFakeId() : string
    {
        $this->fakeIdCounter += 1;
        $fakeId = str_repeat(chr(ord('0') + $this->fakeIdCounter), 40);
        assert(BaseStorageAdapter::isValidElementId($fakeId));
        return $fakeId;
    }
}
