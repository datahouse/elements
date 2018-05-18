<?php

namespace Datahouse\Elements\Control\Exceptions;

/**
 * @package Datahouse\Elements\Control\Exceptions
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class UnexpectedFileUpload extends FileUploadError
{
    private $names;

    /**
     * @param array $names parameter names of expected but missing file uploads
     */
    public function __construct(array $names)
    {
        $this->names = $names;
        assert(count($names) > 0);
        parent::__construct('unexpected uploads for: ' . implode(', ', $names));
    }

    /**
     * @return array of missing upload parameter names
     */
    public function getUnexpectedNames() : array
    {
        return $this->names;
    }
}
