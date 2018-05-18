<?php

namespace Datahouse\Elements\Control\Filter;

use Datahouse\Elements\Abstraction\Element;

/**
 * Interface for input filters (i.e. saving an element, from browser to
 * storage)
 *
 * @package Datahouse\Elements\Control\Session
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
interface IInputFilter
{
    /**
     * @param Element $element    affected
     * @param string  $relativeTo reference for relative links
     * @param array   $fieldDef   definition of the field to edit
     * @param string  $value      received from the browser, to be filtered
     * @return string the filtered value to be stored
     */
    public function inFilter(
        Element $element,
        string $relativeTo,
        array $fieldDef,
        string $value
    ) : string;
}
