<?php

namespace Datahouse\Elements\Control\Exceptions;

/**
 * @package Datahouse\Elements\Control\Exceptions
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class FileUploadExceedsSizeLimit extends FileUploadError
{
    private $paramName;
    private $size;

    /**
     * @param string $paramName of the required upload
     * @param int    $size      actual size tried to upload
     */
    public function __construct(string $paramName, int $size)
    {
        parent::__construct(
            'upload for $paramName exceeds limit with $size bytes'
        );
        $this->paramName = $paramName;
        $this->size = $size;
    }
}
