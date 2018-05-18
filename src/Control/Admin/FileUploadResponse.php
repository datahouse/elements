<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Control\BaseJsonResponse;

/**
 * Represents a JSON response for file uploads via Froala.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2017 by Datahouse AG
 */
class FileUploadResponse extends BaseJsonResponse
{
    /* @var string $link */
    private $link;

    /**
     * @param int    $code http response code
     * @param string $link link generated for the uploaded file
     */
    public function __construct(int $code, string $link)
    {
        parent::__construct($code);
        $this->link = $link;
    }

    /**
     * @return array entire response as a serializable array
     */
    public function asArray() : array
    {
        return ['link' => $this->link];
    }
}
