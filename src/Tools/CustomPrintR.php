<?php

namespace Datahouse\Elements\Tools;

use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;

/**
 * var_dump and print_r suck - a saner replacement
 *
 * This one really avoids infitite recursion and their death by memory
 * exhaustion. Enjoy!
 *
 * @package Datahouse\Elements\Tools
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class CustomPrintR
{
    /**
     * @param array $arr        to dump
     * @param int   $depthLimit to avoid infitine recursion
     * @param int   $indent     amount of spaces to append after a newline
     * @param array $dumped     objects already dumped
     * @return string
     */
    private static function arrayDumper(
        array $arr,
        int $depthLimit,
        int $indent,
        array $dumped
    ) : string {
        if (empty($arr)) {
            return '[]';
        } elseif ($depthLimit == 0) {
            return '[...]';
        } else {
            $dumpedValues = array_map(
                function ($v) use ($depthLimit, $indent, $dumped) {
                    return static::varDumper(
                        $v,
                        $depthLimit - 1,
                        $indent + 2,
                        $dumped
                    );
                },
                $arr
            );
            $totalLength = array_sum(array_map(function ($dumped) {
                return strlen($dumped) + 2;
            }, $dumpedValues));
            if ($totalLength < 70) {
                return "[" . implode(", ", $dumpedValues) . "]";
            } else {
                $nextline = "\n" . str_repeat(' ', $indent + 2);
                return "[" . $nextline
                . implode("," . $nextline, $dumpedValues)
                . "\n" . str_repeat(' ', $indent) . "]";
            }
        }
    }

    /**
     * @param mixed                $obj        to dump properties from
     * @param ReflectionProperty[] $properties to dump
     * @param string               $visibility of the properties
     * @param int                  $depthLimit to avoid infitine recursion
     * @param int                  $indent     amount of spaces
     * @param array                $dumped     objects already dumped
     * @return array
     */
    private static function propertyDumper(
        $obj,
        $properties,
        $visibility,
        int $depthLimit,
        int $indent,
        array $dumped
    ) : array {
        $values = [];
        foreach ($properties as $prop) {
            $key = $prop->getName();
            $prop->setAccessible(true);
            $dumpedValue = static::varDumper(
                $prop->getValue($obj),
                $depthLimit,
                $indent,
                $dumped
            );
            $values[] = $visibility . ': ' . $key . ' => ' . $dumpedValue;
        }
        return $values;
    }

    /**
     * @param mixed $obj        to dump
     * @param int   $depthLimit to avoid infitine recursion
     * @param int   $indent     amount of spaces to append after a newline
     * @param array $dumped     objects already dumped
     * @return string
     */
    private static function objectDumper(
        $obj,
        int $depthLimit,
        int $indent,
        array $dumped
    ) : string {
        if (in_array($obj, $dumped)) {
            return 'RECURSION';
        } elseif (is_callable($obj)) {
            $rc = new ReflectionFunction($obj);
            if ($rc->isClosure()) {
                return "<closure: " . basename($rc->getFileName())
                . ':' . $rc->getStartLine() . '-' . $rc->getEndLine()
                . ">";
            } else {
                return "unknown function";
            }
        } else {
            $rc = new ReflectionClass($obj);
            $dumped[] = $obj;
            $values = self::propertyDumper(
                $obj,
                $rc->getProperties(ReflectionProperty::IS_PUBLIC),
                "pub",
                $depthLimit - 1,
                $indent + 2,
                $dumped
            );
            $values = array_merge($values, self::propertyDumper(
                $obj,
                $rc->getProperties(ReflectionProperty::IS_PROTECTED),
                "prot",
                $depthLimit - 1,
                $indent + 2,
                $dumped
            ));
            $values = array_merge($values, self::propertyDumper(
                $obj,
                $rc->getProperties(ReflectionProperty::IS_PRIVATE),
                "priv",
                $depthLimit - 1,
                $indent + 2,
                $dumped
            ));
            $nextline = "\n" . str_repeat(' ', $indent + 2);
            return $rc->getShortName() . " {" . $nextline
            . implode("," . $nextline, $values)
            . "\n" . str_repeat(' ', $indent) . "}";
        }
    }

    /**
     * @param mixed $var        to dump
     * @param int   $depthLimit limit of recursion
     * @param int   $indent     number of spaces to indent
     * @param array $dumped     to avoid dumping stuff twice
     * @return string
     */
    public static function varDumper(
        $var,
        int $depthLimit,
        $indent = 0,
        $dumped = []
    ) : string {
        $depthLimit -= 1;
        switch (gettype($var)) {
            case 'boolean':
                return $var ? 'true' : 'false';
            case 'integer':
            case 'double':
            case 'string':
                return '"' . addslashes($var) . '"';
            case 'NULL':
                return '<null>';
            case 'array':
                return self::arrayDumper($var, $depthLimit, $indent, $dumped);
            case 'object':
                return self::objectDumper($var, $depthLimit, $indent, $dumped);
            default:
                return "unknown type: " . gettype($var);
        }
    }
}
