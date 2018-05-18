<?php

namespace Datahouse\Elements\Tools;

use Throwable;

use Assetic\Exception\FilterException;

/**
 * An exception logger - emitting a readable stack trace to the error log.
 *
 * This includes special treatment for certain Elements objects, which would
 * cripple the log.
 *
 * @package Datahouse\Elements\Tools
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ExceptionLogger
{
    /**
     * @param array $backTrace to log
     * @return void
     */
    public static function logStackTrace(array $backTrace)
    {
        foreach ($backTrace as $idx => $point) {
            $line = str_repeat(' ', 2 - strlen($idx))
                . '#' . $idx . ' '
                . (isset($point['class'])
                    ? $point['class'] . '::'
                    : '')
                . $point['function'];
            $line = str_replace('Datahouse\\Elements\\', 'ELE#', $line);
            error_log($line);
            if (isset($point['file']) && isset($point['line'])) {
                error_log(
                    '    in ' . $point['file'] . ':' . $point['line']
                );
            }
            if (isset($point['args']) && count($point['args']) > 0) {
                self::logTracePointArguments($point['args']);
            }
        }
    }

    /**
     * Logs an unexpected exception to the error log of the webserver.
     *
     * @param Throwable $e the exception to dump
     * @return void
     */
    public static function logException(Throwable $e)
    {
        error_log(str_repeat('-', 78));
        error_log('Internal Server Error due to ' . get_class($e));
        error_log('    in ' . $e->getFile() . ':' . $e->getLine() . ':');
        static::logExceptionMessage($e);
        error_log('');
        error_log('Stack trace:');
        static::logStackTrace($e->getTrace());
    }

    /**
     * Logs the exception's main message.
     *
     * @param Throwable $e the exception to dump
     * @return void
     */
    public static function logExceptionMessage(Throwable $e)
    {
        foreach (explode("\n", $e->getMessage()) as $line) {
            $line = str_replace("\t", "    ", $line);
            // Assetic appends the entire input file to the error message.
            // That's way too verbose for an apache error log, so we abort
            // dumping there in this case.
            if ($e instanceof FilterException && $line === 'Input:') {
                break;
            }
            error_log('    ' . $line);
        }
    }

    /**
     * @param array $arguments         to log
     * @param int   $max_lines_per_arg an upper limit
     * @return void
     */
    public static function logTracePointArguments(
        array $arguments,
        int $max_lines_per_arg = 12
    ) {
        if (count($arguments) > 1) {
            error_log('    called with ' . count($arguments) . ' arguments:');
        } else {
            error_log('    called with 1 argument:');
        }
        foreach ($arguments as $arg_idx => $arg) {
            $argString = CustomPrintR::varDumper($arg, 4, 4);
            $limit = $max_lines_per_arg;
            $arg_parts = explode("\n", $argString);
            foreach ($arg_parts as $line_idx => $line) {
                if (strlen($line) == 0) {
                    continue;
                }
                if (strlen($line) > 60) {
                    $line = substr($line, 0, 58) . '..';
                }
                $arg_no = $arg_idx + 1;
                if ($line_idx === 0) {
                    error_log(
                        str_repeat(' ', 8 - strlen($arg_no))
                        . $arg_no . ': ' . $line
                    );
                } else {
                    error_log(str_repeat(' ', 6) . $line);
                }
                $limit--;
                if ($limit <= 0) {
                    error_log(str_repeat(' ', 6) . '...');
                    break;
                }
            }
        }
    }
}
