<?php

namespace Datahouse\Elements\Control\Filter;

use RuntimeException;

use Datahouse\Elements\Abstraction\Element;

/**
 * Filters away style="" attributes sometimes inserted by Froala. Prevents
 * creation of new element versions if the user didn't really change anything.
 *
 * @package Datahouse\Elements\Control\Filter
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2017 by Datahouse AG
 */
class EmptyStyleAttributeFilter implements IInputFilter
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
    ) : string {
        $pattern = '/\s+style=\"\"/i';
        if (preg_match_all(
            $pattern,
            $value,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        ) === false) {
            throw new RuntimeException("preg_match failed");
        }

        foreach (array_reverse($matches) as $match) {
            $startIdx = $match[0][1];
            $matchLen = strlen($match[0][0]);
            $value = substr($value, 0, $startIdx)
                . substr($value, $startIdx + $matchLen);
        }

        return $value;
    }
}
