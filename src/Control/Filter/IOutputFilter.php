<?php

namespace Datahouse\Elements\Control\Filter;

/**
 * Interface for output filters (i.e. rendering an element, from storage to
 * browser)
 *
 * @package Datahouse\Elements\Control\Session
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
interface IOutputFilter
{
    /**
     * @param array  $fieldDef definition of the field, including type
     * @param string $value    current value, to be filtered
     * @param string $language to use for displaying
     * @return mixed the filtered value
     */
    public function outFilter(
        array $fieldDef,
        string $value,
        string $language
    ) : string;
}
