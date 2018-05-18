<?php

namespace Datahouse\Elements\Control;

/**
 * Globally useful static helper functions.
 *
 * @package Datahouse\Elements\Control
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class StaticHelper
{
    /**
     * Helper function for parsing php ini file values. Loosely based on a
     * method from:
     *
     * http://php.net/manual/en/function.ini-get.php
     *
     * @param string $value as retrieved from get_ini
     * @return int
     */
    public static function returnBytes(string $value)
    {
        preg_match('#([0-9]+)[\s]*([a-z]+)#i', trim($value), $matches);

        $last = '';
        if (isset($matches[2])) {
            $last = strtolower($matches[2]);
        }

        if (isset($matches[1])) {
            $value = intval($matches[1]);
        } else {
            $value = intval($value ?? 0);
        }

        if ($last === 'g' || $last == 'gb') {
            return $value * 1024 * 1024 * 1024;
        } elseif ($last === 'm' || $last === 'mb') {
            return $value * 1024 * 1024;
        } elseif ($last === 'k' || $last === 'kb') {
            return $value * 1024;
        } else {
            return $value;
        }
    }
}
