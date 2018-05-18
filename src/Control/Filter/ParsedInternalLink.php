<?php

namespace Datahouse\Elements\Control\Filter;

/**
 * Basically just a struct used as a typed return value for
 * InternalLinkMangling.
 *
 * @package Datahouse\Elements\Control\Filter
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2017 by Datahouse AG
 */
class ParsedInternalLink
{
    /* @var string $scheme */
    public $scheme;
    /* @var bool $isInternal */
    public $isInternal;
    /* @var string|null $path */
    public $path;
    /* @var string $anchor */
    public $anchor;
}
