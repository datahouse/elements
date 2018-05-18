<?php

namespace Datahouse\Elements\Tools;

use stdClass;

use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Inline;
use Symfony\Component\Yaml\Yaml;

/**
 * A customized YAML dumper class, based on the original Symfony Dumper as of
 * version 3.1.4.
 *
 * The original Symfony implementation doesn't properly format stdClass
 * objects, even if it supports them. This variant provides a YAML dumper with
 * proper indentation for stdClass objects.
 *
 * Other changes compared to the original:
 *  - For performance reasons, this dumper also writes to a file directly,
 *    rather than emitting a PHP string.
 *
 * @package Datahouse\Elements\Tools
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class CustomYamlFileDumper extends Dumper
{
    protected $outFp;

    /**
     * @param string $outPath     resulting output file
     * @param int    $indentation default indentation
     */
    public function __construct(string $outPath, int $indentation = 4)
    {
        $this->outFp = fopen($outPath, 'w');
        fwrite($this->outFp, "---\n");
        parent::__construct($indentation);
    }

    /**
     * Closes the file handle.
     */
    public function __destruct()
    {
        fclose($this->outFp);
    }

    /**
     * @param int    $indent  indentation string
     * @param int    $flags   original flags (to pass on to recursion)
     * @param bool   $isAHash whether or not the pair is from a hash (map in
     *                        YAMLese)
     * @param string $key     the key
     * @param mixed  $value   some value
     * @return void
     */
    protected function dumpMultiLineString(
        int $indent,
        int $flags,
        bool $isAHash,
        string $key,
        $value
    ) {
        $prefix = str_repeat(' ', $indent ?? 0);
        fprintf(
            $this->outFp,
            "%s%s%s |\n",
            $prefix,
            $isAHash ? Inline::dump($key, $flags) . ':' : '-',
            ''
        );
        foreach (preg_split('/\n|\r\n/', $value) as $row) {
            fprintf(
                $this->outFp,
                "%s%s%s\n",
                $prefix,
                str_repeat(' ', $this->indentation),
                $row
            );
        }
    }

    /**
     * @param mixed $input to check
     * @return bool whether or not the given value should be inlined
     */
    private function canInline($input) : bool
    {
        if ($input instanceof stdClass) {
            return empty(get_object_vars($input));
        } else {
            return empty($input) || !is_array($input);
        }
    }

    /**
     * @param int   $inline  whether or not to inline
     * @param int   $indent  indentation string
     * @param int   $flags   original flags (to pass on to recursion)
     * @param bool  $isAHash whether or not the pair is from a hash (map in
     *                       YAMLese)
     * @param mixed $key     the key
     * @param mixed $value   some value
     * @return void
     */
    protected function dumpKeyValuePair(
        int $inline,
        int $indent,
        int $flags,
        bool $isAHash,
        $key,
        $value
    ) {
        $prefix = str_repeat(' ', $indent ?? 0);
        $willBeInlined = $inline <= 0 || $this->canInline($value);

        fprintf(
            $this->outFp,
            '%s%s%s',
            $prefix,
            $isAHash ? Inline::dump($key, $flags) . ':' : '-',
            $willBeInlined ? ' ' : "\n"
        );

        // recurse
        $this->dump(
            $value,
            $inline,
            $willBeInlined ? 0 : $indent + $this->indentation
        );

        if ($willBeInlined) {
            fprintf($this->outFp, "\n");
        }
    }

    /**
     * @param int   $inline The level where inlining starts
     * @param int   $flags  A bit field of Yaml::DUMP_* constants
     * @param mixed $value  The value to be dumped
     * @return bool
     */
    protected function isMultiLineString(int $inline, int $flags, $value) : bool
    {
        return $inline > 1 &&
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK & $flags &&
            is_string($value) &&
            false !== strpos($value, "\n");
    }

    /**
     * Dumps a PHP value to YAML.
     *
     * @param mixed $input  The PHP value
     * @param int   $inline The level where you switch to inline YAML
     * @param int   $indent The level of indentation (used internally)
     * @param int   $flags  A bit field of Yaml::DUMP_* constants to customize
     *                      the dumped YAML string
     * @return void
     */
    public function dump($input, $inline = 0, $indent = 0, $flags = 0)
    {
        $prefix = str_repeat(' ', $indent ?? 0);
        if ($input instanceof stdClass && empty(get_object_vars($input))) {
            fwrite($this->outFp, $prefix . "{ }");
        } elseif ($inline <= 0 || $this->canInline($input)) {
            fwrite($this->outFp, $prefix . Inline::dump($input, $flags));
        } else {
            if ($input instanceof stdClass) {
                $input = get_object_vars($input);
                $isAHash = true;
            } else {
                $isAHash = Inline::isHash($input);
            }
            assert(is_array($input));
            foreach ($input as $key => $value) {
                if ($this->isMultiLineString($inline, $flags, $value)) {
                    $this->dumpMultiLineString(
                        $indent,
                        $flags,
                        $isAHash,
                        $key,
                        $value
                    );
                } else {
                    $this->dumpKeyValuePair(
                        $inline - 1,
                        $indent,
                        $flags,
                        $isAHash,
                        $key,
                        $value
                    );
                }
            }
        }
    }
}
