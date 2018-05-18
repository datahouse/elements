<?php

namespace Datahouse\Elements\Tests\Helpers;

use Datahouse\Elements\Presentation\BaseElementDefinition;
use Datahouse\Elements\Presentation\IRenderer;

class ExampleElementDefinition extends BaseElementDefinition
{
    public function getKnownContentFields() : array
    {
        return [
            'title' => ['type' => 'text'],
            'contents' => ['type' => 'text']
        ];
    }

    public function getDisplayName() : string
    {
        return 'Example';
    }

    public function getRenderer() : IRenderer
    {
        return null;
    }
}
