<?php

namespace Datahouse\Elements\Control;

use Exception;
use finfo;

use Datahouse\Elements\Control\Exceptions\FileUploadExceedsSizeLimit;
use Datahouse\Elements\Control\Exceptions\InvalidUploadParameters;
use Datahouse\Elements\Control\Exceptions\MissingFileUpload;
use Datahouse\Elements\Control\Exceptions\UnexpectedFileUpload;

/**
 * Encapsulates all of the necessary inputs from php globals such as
 * $_POST, $_SERVER and $_FILES.
 *
 * @package Datahouse\Elements\Control
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class HttpRequest
{
    public $method;
    public $referer;
    public $url;
    public $body;
    protected $files;
    public $contentType;
    public $contentEncoding;
    public $acceptLanguage;
    protected $parameters;

    /**
     * Creates an empty HttpRequest
     */
    public function __construct()
    {
        $this->parameters = [];
        $this->body = '';
        $this->files = [];
    }

    /**
     * @param array $parts the remainder after the content type
     * @return void
     */
    private function parseContentTypeArguments(array $parts)
    {
        $contentTypeArguments = [];
        foreach ($parts as $part) {
            list($key, $value) = explode('=', $part, 2);
            $key = strtolower(trim($key));
            $value = strtolower(trim($value));
            $contentTypeArguments[$key] = $value;
        }
        if (array_key_exists('encoding', $contentTypeArguments)) {
            $this->contentEncoding = $contentTypeArguments['encoding'];
        }
    }

    /**
     * @param array $post usually the PHP $_POST global
     * @return void
     */
    private function parsePostParameters(array $post)
    {
        $this->parameters = [];
        if ($this->contentType == 'application/x-www-form-urlencoded') {
            foreach ($post as $key => $value) {
                $key = strtolower($key);
                if (isset($value)
                    && !array_key_exists($key, $this->parameters)
                ) {
                    $this->parameters[$key] = $value;
                }
            }
        }
    }

    /**
     * @param array $get map of key value pairs
     * @return void
     */
    private function consumeGetParameters(array $get)
    {
        foreach ($get as $key => $value) {
            $this->parameters[$key] = $value;
        }
    }

    /**
     * @param string $paramName name of the parameter, possibly post-fixed with
     *                          an index
     * @param array  $fileInfo  standard php info for each file uploaded
     * @return void
     * @throws InvalidUploadParameters
     */
    private function processFileInfo(string $paramName, array $fileInfo)
    {
        if (!isset($fileInfo['error']) ||
            is_array($fileInfo['error']) ||
            !isset($fileInfo['size']) ||
            !isset($fileInfo['type']) ||       // php should set these, but
            !isset($fileInfo['name']) ||       // we're paranoid and cross-
            !isset($fileInfo['tmp_name'])      // check anyways.
        ) {
            throw new InvalidUploadParameters('possible upload attack');
        }

        // cross-check the passed mime type for successful uploads
        if ($fileInfo['error'] == UPLOAD_ERR_OK) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            try {
                $determinedMimeType = $finfo->file($fileInfo['tmp_name']);
            } catch (Exception $e) {
                throw new InvalidUploadParameters($e->getMessage());
            }
            if ($fileInfo['type'] != $determinedMimeType
                && $fileInfo['type'] != 'attachment/download'
            ) {
                error_log(
                    'mismatch betwen given and determined mime type: '
                    . $fileInfo['type'] . ' vs. ' . $determinedMimeType
                );
                $fileInfo['type'] = $determinedMimeType;
            }
        }

        $this->files[$paramName] = $fileInfo;
    }

    /**
     * Loads file uploads via POSTs of content type 'multipart/form-data' and
     * performs some initial validity checks.
     *
     * @param array $files same as the $_FILES global.
     * @return void
     * @throws InvalidUploadParameters
     */
    private function loadFiles(array $files)
    {
        foreach ($files as $postName => $postInfo) {
            if (is_array($postInfo['error'] ?? null)
                && is_array($postInfo['tmp_name'] ?? null)
            ) {
                foreach ($postInfo as $idx => $fileInfo) {
                    $this->processFileInfo($postName . '|' . $idx, $fileInfo);
                }
            } else {
                $this->processFileInfo($postName, $postInfo);
            }
        }
    }

    /**
     * Load post data from the request body.
     *
     * @param string|null $body optional override for tests
     * @return void
     */
    private function loadBody($body = null)
    {
        if (!is_null($body)) {
            $this->body = $body;
        } elseif ($this->method === 'POST' || $this->method === 'PUT') {
            if ($this->contentType == 'application/x-www-form-urlencoded') {
                if (isset($this->parameters['body'])) {
                    $this->body = $this->parameters['body'];
                    unset($this->parameters['body']);
                } else {
                    $this->body = '';
                }
            } elseif ($this->contentType == 'multipart/form-data') {
                $this->body = '';
            } else {
                $this->body = file_get_contents("php://input");
            }
        } else {
            $this->body = '';
        }
    }

    /**
     * Populates a HttpRequest object from the usual PHP globals.
     *
     * @param array       $server usually the PHP $_SERVER global
     * @param array       $post   usually the PHP $_POST global
     * @param array       $files  usually the PHP $_FILES global
     * @param array       $get    usually the PHP $_GET global
     * @param string|null $body   to override fetching from php:://input
     * @return void
     */
    public function populateFrom(
        array $server,
        array $post,
        array $files,
        array $get,
        $body = null
    ) {
        $this->url = $server['REQUEST_URI'] ?? null;
        $idx = strpos($this->url, '?');
        if ($idx !== false) {
            $this->url = substr($this->url, 0, $idx);
        }

        if (array_key_exists('CONTENT_TYPE', $server)) {
            $contentType = strtolower(trim($server['CONTENT_TYPE']));
            $idx = strpos($contentType, ';');
            if ($idx !== false) {
                $rest = trim(substr($contentType, $idx + 1));
                $contentType = strtolower(trim(substr($contentType, 0, $idx)));
                $this->parseContentTypeArguments(explode(';', $rest));
            }
            $this->contentType = $contentType;
        }

        if (array_key_exists('HTTP_ACCEPT_LANGUAGE', $server)) {
            $this->acceptLanguage = $server['HTTP_ACCEPT_LANGUAGE'];
        }

        if (array_key_exists('REQUEST_METHOD', $server)) {
            $this->method = strtoupper($server['REQUEST_METHOD']);
        }

        if (array_key_exists('REFERER', $server)) {
            $this->referer = $server['REFERER'];
        } elseif (array_key_exists('HTTP_REFERER', $server)) {
            $this->referer = $server['HTTP_REFERER'];
        }

        if ($this->method === 'POST') {
            $this->parsePostParameters($post);
        } elseif ($this->method === 'GET') {
            $this->consumeGetParameters($get);
        }
        $this->loadBody($body);

        if ($this->contentType == 'multipart/form-data') {
            $this->loadFiles($files);
        }
    }

    /**
     * @param string $paramName to query
     * @return bool
     */
    public function hasParameter(string $paramName) : bool
    {
        return array_key_exists(strtolower($paramName), $this->parameters);
    }

    /**
     * @param string $paramName of the parameter to fetch
     * @return string|null
     */
    public function getParameter(string $paramName)
    {
        if ($this->hasParameter($paramName)) {
            $paramName = strtolower($paramName);
            return $this->parameters[$paramName];
        } else {
            return null;
        }
    }

    /**
     * getReferer
     *
     * @return mixed
     */
    public function getReferer()
    {
        if (is_null($this->referer)) {
            return '';
        }
        return $this->referer;
    }

    /**
     * setParameter
     *
     * @param string $paramName  name of the parameter to set
     * @param string $paramValue its value
     *
     * @return void
     */
    public function setParameter(string $paramName, string $paramValue)
    {
        $paramName = strtolower($paramName);
        $this->parameters[$paramName] = $paramValue;
    }

    /**
     * @return string|null content type from request header (w/o encoding)
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @return string|null encoding given in the header's content type
     */
    public function getContentEncoding()
    {
        return $this->contentEncoding;
    }

    /**
     * @return string body of the POST or PUT request, may be an empty string
     */
    public function getBody() : string
    {
        return $this->body;
    }

    /**
     * Checks the list of uploaded files against an expection list.
     *
     * FIXME: doesn't currently offer the caller a good way to handle arrays
     * of uploaded files per postName.
     *
     * @param array $names for which the controller expects a file upload
     * @return void
     * @throws UnexpectedFileUpload
     * @throws MissingFileUpload
     */
    public function expectUploadsFor(array $names)
    {
        $missingFiles = array_diff($names, array_keys($this->files));
        $ignoredFiles = array_diff_key($this->files, array_flip($names));

        if (count($missingFiles) > 0) {
            throw new MissingFileUpload($missingFiles);
        }

        if (count($ignoredFiles) > 0) {
            throw new UnexpectedFileUpload(array_keys($ignoredFiles));
        }
    }

    /**
     * @param string $postName name of the file upload, possibly post-fixed
     *                         with an index.
     * @return array info about the uploaded file
     * @throws InvalidUploadParameters
     * @throws FileUploadExceedsSizeLimit
     * @throws MissingFileUpload
     */
    public function getUploadedFileInfo(string $postName) : array
    {
        assert(array_key_exists($postName, $this->files));
        $fileInfo = $this->files[$postName];
        switch ($fileInfo['error']) {
            case UPLOAD_ERR_OK:
                unset($fileInfo['error']);
                return $fileInfo;
            case UPLOAD_ERR_NO_FILE:
                throw new MissingFileUpload([$postName], 'no file sent');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new FileUploadExceedsSizeLimit($postName, $fileInfo['size']);
            default:
                throw new InvalidUploadParameters('Unknown errors.');
        }
    }
}
