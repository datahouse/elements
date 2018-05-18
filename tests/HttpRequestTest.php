<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Control\Exceptions\InvalidUploadParameters;
use Datahouse\Elements\Control\HttpRequest;

/**
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class HttpRequestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test parsing of a content type without encoding.
     * @return void
     */
    public function testContentTypeOnly()
    {
        $request = new HttpRequest();
        $request->populateFrom(['CONTENT_TYPE' => 'text/plain'], [], [], []);
        $this->assertEquals('text/plain', $request->getContentType());
        $this->assertNull($request->getContentEncoding());
    }

    /**
     * Test parsing of a content type and encoding.
     * @return void
     */
    public function testContentTypeAndEncoding()
    {
        $request = new HttpRequest();
        $request->populateFrom([
            'CONTENT_TYPE' => 'text/plain; encoding=utf-8'
        ], [], [], []);
        $this->assertEquals('text/plain', $request->getContentType());
        $this->assertEquals('utf-8', $request->getContentEncoding());
    }

    /**
     * Test with a request without content type
     * @return void
     */
    public function testMissingContentType()
    {
        $request = new HttpRequest();
        $request->populateFrom([], [], [], []);
        $this->assertNull($request->getContentType());
    }

    /**
     * Check invalid parameters for uploads.
     * @return void
     */
    public function testUploadError()
    {
        $this->expectException(InvalidUploadParameters::class);
        $request = new HttpRequest();
        $server = [
            'CONTENT_TYPE' => 'multipart/form-data'
        ];
        $files = [
            'myfile' => [ /* missing info here */ ]
        ];
        $request->populateFrom($server, [], $files, []);
        $request->expectUploadsFor(['myfile']);
    }

    /**
     * Check a valid upload.
     * @return void
     */
    public function testSuccessfulFileUpload()
    {
        // Write a temp file simulating a file upload
        $tmpPath = tempnam(sys_get_temp_dir(), 'ele-tests');
        $fh = fopen($tmpPath, 'w');
        fwrite($fh, "some random text");
        fclose($fh);

        $request = new HttpRequest();
        $server = [
            'CONTENT_TYPE' => 'multipart/form-data'
        ];
        $files = [
            'myfile' => [
                'error' => UPLOAD_ERR_OK,
                'type' => 'text/plain',
                'size' => 0,
                'tmp_name' => $tmpPath,
                'name' => 'randomUploadedFile.txt'
            ]
        ];
        $request->populateFrom($server, [], $files, []);

        $request->expectUploadsFor(['myfile']);
        $fileInfo = $request->getUploadedFileInfo('myfile');
        $this->assertEquals([
            'type' => 'text/plain',
            'size' => 0,
            'tmp_name' => $tmpPath,
            'name' => 'randomUploadedFile.txt'
        ], $fileInfo);

        // cleaup the mess
        unlink($tmpPath);
    }
}
