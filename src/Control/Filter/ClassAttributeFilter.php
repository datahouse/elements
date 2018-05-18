<?php

namespace Datahouse\Elements\Control\Filter;

use RuntimeException;

use Datahouse\Elements\Abstraction\Element;

/**
 * @package Datahouse\Elements\Control\Filter
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2017 by Datahouse AG
 */
class ClassAttributeFilter implements IInputFilter, IOutputFilter
{
    const PATTERN = '/\s+class\s*=\s*\"([^\"\>]*__ele_[^\s]+[^\"\>]*)\"/im';
    const FLAGS = PREG_SET_ORDER | PREG_OFFSET_CAPTURE;

    public function stripElementsCssClasses(string $value) : string
    {
        if (preg_match_all(
            static::PATTERN,
            $value,
            $matches,
            static::FLAGS
        ) === false) {
            throw new RuntimeException("preg_match failed");
        }

        foreach (array_reverse($matches) as $match) {
            $classNamesStr = str_replace("\n", " ", $match[1][0]);
            $classNames = preg_split("/[\t\n\r\\s]+/", $classNamesStr);
            $filtered = array_filter($classNames, function ($v) {
                $trimmedValue = trim($v);
                return strlen($trimmedValue) > 0
                    && substr($trimmedValue, 0, 6) !== "__ele_";
            });

            $startIdx = $match[1][1];
            $matchLen = strlen($match[1][0]);
            $value = substr($value, 0, $startIdx)
                . implode(' ', $filtered)
                . substr($value, $startIdx + $matchLen);
        }

        return $value;
    }

    public function inFilter(
        Element $element,
        string $relativeTo,
        array $fieldDef,
        string $value
    ) : string {
        return static::stripElementsCssClasses($value);
    }

    public function outFilter(
        array $fieldDef,
        string $value,
        string $language
    ) : string {
        return static::stripElementsCssClasses($value);
    }
}
