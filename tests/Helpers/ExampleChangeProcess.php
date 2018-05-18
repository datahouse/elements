<?php

namespace Datahouse\Elements\Tests\Helpers;

use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Control\BasePublishChangeProcess;

/**
 * A simple example implementation of a change process we can run tests on.
 *
 * @package Datahouse\Elements\Tests\Helpers
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class ExampleChangeProcess extends BasePublishChangeProcess
{
    /**
     * @return string
     */
    public function getInitialState() : string
    {
        return 'editing';
    }

    /**
     * @return string
     */
    public function getFinalState() : string
    {
        return 'deleted';
    }

    /**
     * @return array
     */
    public function enumAllowedStates() : array
    {
        return [
            'editing' => [],
            'published' => [],
            'deleted' => []
        ];
    }

    /**
     * @param string $state to check
     * @return bool
     */
    public function isPublishState(string $state) : bool
    {
        return $state == 'published';
    }

    /**
     * @param string $state to check
     * @return bool
     */
    public function isDeletedState(string $state) : bool
    {
        return $state == 'deleted';
    }

    /**
     * @param ElementVersion $ev to check
     * @return bool
     */
    public function allowVersionContentChange(ElementVersion $ev) : bool
    {
        return !in_array($ev->getState(), ['published', 'deleted']);
    }
}
