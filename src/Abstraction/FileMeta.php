<?php

namespace Datahouse\Elements\Abstraction;

use stdClass;

use Datahouse\Elements\Abstraction\Exceptions\SerDesException;

/**
 * @package Datahouse\Elements\Abstraction
 * @author      Helmar Tröller (htr) <helmar.troeller@datahouse.ch>
 * @license (c) 2014 - 2016 by Datahouse AG
 */
class FileMeta implements ISerializable, IStorable
{
    const FIELDS = [
        'id' => ['type' => 'value', 'required' => true, 'implicit' => true],
        'fileHash' => ['type' => 'value', 'required' => true],
        'collection' => ['type' => 'value', 'required' => true],
        'size' => ['type' => 'value', 'required' => true],
        'origFileName' => ['type' => 'value'],
        'mimeType' => ['type' => 'value']
    ];
    const ALLOW_ARBITRARY_FIELDS = false;
    use SerializationHelper;

    protected $id;
    protected $fileHash;
    protected $collection;
    protected $size;
    protected $origFileName;
    protected $mimeType;

    /**
     * @param string $id for this piece of meta data
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * @param string $fileHash     sha1 of the actual blob
     * @param string $collection   blob collection name
     * @param string $origFileName original name of the file
     * @param string $mimeType     of the uploaded file
     * @param int    $size         of the file
     * @return void
     */
    public function populate(
        string $fileHash,
        string $collection,
        string $origFileName,
        string $mimeType,
        int $size
    ) {
        $this->fileHash = $fileHash;
        $this->collection = $collection;
        $this->origFileName = $origFileName;
        $this->mimeType = $mimeType;
        $this->size = $size;
    }

    /**
     * @return stdClass
     * @throws SerDesException
     */
    public function serialize() : stdClass
    {
        try {
            return $this->genericSerialize();
        } catch (SerDesException $e) {
            $e->addContext("FileMeta " . $this->getId());
            throw $e;
        }
    }

    /**
     * @param stdClass $data to deserialize from
     * @return void
     * @throws SerDesException
     */
    public function deserialize(stdClass $data)
    {
        try {
            $this->genericDeserialize($data);
        } catch (SerDesException $e) {
            $e->addContext("FileMeta " . $this->getId());
            throw $e;
        }
    }

    /**
     * @inheritdoc
     * @param IStorageAdapter $adapter used to store the element
     * @return void
     */
    public function storeVia(IStorageAdapter $adapter)
    {
        $adapter->storeFileMeta($this);
    }

    /**
     * Returns the unique identifier of this element.
     *
     * @return string identifier
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * @return string hash of the underlying data
     */
    public function getFileHash() : string
    {
        return $this->fileHash;
    }

    /**
     * Deterministically determines a valid file extension according to the
     * original file name, possibly validated against the mime type. May return
     * an empty string.
     *
     * @return string file extension including the separating dot or an
     *         empty string
     */
    public function getExtension() : string
    {
        $pattern = '/\.[a-zA-Z]{3,4}$/';
        if (preg_match($pattern, $this->origFileName, $matches) == 1) {
            $ext = $matches[0];
            return $ext;
        } else {
             return '';
        }
    }

    /**
     * @return string collection where the blob resides
     */
    public function getCollection() : string
    {
        return $this->collection;
    }

    /**
     * @return string|null original file name as uploaded
     */
    public function getOrigFileName()
    {
        return $this->origFileName;
    }

    /**
     * @inheritdoc
     * @return string storage scope of the FileMeta structure in the
     *         database (got nothing to do with the blob itself).
     */
    public static function getStorageScope() : string
    {
        return "filemeta";
    }

    /**
     * @return string the mime type of the original upload
     */
    public function getMimeType() : string
    {
        return $this->mimeType;
    }
}
