<?php

namespace Datahouse\Elements\Control\Exceptions;

/**
 * @package Datahouse\Elements\Control\Exceptions
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class MissingFileUpload extends FileUploadError
{
    private $names;

    /**
     * @param array  $names   parameter names of expected but missing file
     *                        uploads
     * @param string $addInfo optional additional information
     */
    public function __construct(array $names, string $addInfo = '')
    {
        $this->names = $names;
        assert(count($names) > 0);
        parent::__construct(
            'missing uploads for: ' . implode(', ', $names)
            . (empty($addInfo) ? '' : ': ' . $addInfo)
        );
    }

    /**
     * @return array of missing upload parameter names
     */
    public function getMissingNames() : array
    {
        return $this->names;
    }
}
