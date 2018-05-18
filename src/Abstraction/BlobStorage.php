<?php

namespace Datahouse\Elements\Abstraction;

use RuntimeException;

/**
 * A pretty simple blob storage implementation.
 *
 * @package Datahouse\Elements\Abstraction
 * @author Markus Wanner <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class BlobStorage
{
    /**
     * @param string $baseDir of the blob storage to use, w/o trailing slash
     */
    public function __construct(string $baseDir)
    {
        $this->baseDir = $baseDir;
        if (!is_dir($baseDir)) {
            throw new RuntimeException("blob dir $baseDir does not exist");
        }
    }

    /**
     * @return string base directory of the blob storage
     */
    public function getBaseDir() : string
    {
        return $this->baseDir;
    }

    /**
     * @param string $path of the file to hash
     * @return string hex encoded sha1 hash
     */
    private function calcHash(string $path)
    {
        $hash = sha1_file($path);
        assert(BaseStorageAdapter::isValidFileId($hash));
        return $hash;
    }

    /**
     * @param string $collection name of the collection (subdir) to use
     * @param array  $fileInfo   the source file to move into the blob storage
     * @return string file hash used to store the blob
     * @throws RuntimeException
     */
    public function eatBlob(string $collection, array $fileInfo) : string
    {
        $fileHash = $this->calcHash($fileInfo['tmp_name']);

        // Assemble the target path for the blob.
        $targetPath = $this->baseDir . '/' . $collection;
        if (!is_dir($targetPath)) {
            mkdir($targetPath);
        }

        // FIXME: we could add the extension from the original filename, so
        // apache could serve the files statically with proper mime types.
        // However, that would needlessly complicate the BlobStorage layer, so
        // we rely on apache caching these blobs, instead.
        $targetPath = $targetPath . '/' . $fileHash;

        if (file_exists($targetPath)) {
            // blob contents match with a very, very high probability.
            unlink($fileInfo['tmp_name']);
        } else {
            $res = rename($fileInfo['tmp_name'], $targetPath);
            if ($res !== true) {
                // Try copying, instead.
                $res = copy($fileInfo['tmp_name'], $targetPath);
                if ($res !== true) {
                    throw new RuntimeException(
                        'unable to move temporary file'
                    );
                }
                unlink($fileInfo['tmp_name']);
            }
        }
        return $fileHash;
    }

    /**
     * @param FileMeta $fileMeta meta data of the blob to retrieve
     * @return string contents of the blob
     */
    public function pukeBlob(FileMeta $fileMeta) : string
    {
        $path = $this->baseDir . '/' . $fileMeta->getCollection() . '/' .
            $fileMeta->getFileHash();
        return file_get_contents($path);
    }
}
