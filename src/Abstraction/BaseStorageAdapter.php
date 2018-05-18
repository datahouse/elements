<?php

namespace Datahouse\Elements\Abstraction;

use stdClass;
use RuntimeException;

use Datahouse\Elements\Abstraction\Cache;
use Datahouse\Elements\Abstraction\Changes\IChange;
use Datahouse\Elements\Abstraction\Changes\Transaction;
use Datahouse\Elements\Presentation\Exceptions\ConfigurationError;

/**
 * A common base providing helper routines and functionality common to  all
 * storage adapters.
 *
 * @package Datahouse\Elements\Abstraction
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
abstract class BaseStorageAdapter implements IStorageAdapter
{
    /* @var BlobStorage|null $blobStorage, note that this is optional */
    protected $blobStorage;

    /* @var Cache\ElementUrlCache|null $urlMapper lazy loading... */
    private $urlMapper;

    /**
     * @param BlobStorage|null $blobStorage to use for large binary objects
     */
    public function __construct(BlobStorage $blobStorage)
    {
        $this->blobStorage = $blobStorage;
        $this->urlMapper = null;
    }

    /**
     * @return string a random hex id of 40 hex characters in length
     */
    public static function genRandomId() : string
    {
        return bin2hex(openssl_random_pseudo_bytes(20));
    }

    /**
     * Read interface to a simple key-value storage that knows different
     * types key-value pairs.
     *
     * @param string $type  of the object to load
     * @param string $objId of the object to load
     * @return stdClass|null the data loaded from storage
     */
    abstract protected function loadObjectData(string $type, string $objId);

    /**
     * Reads an object from the storage, or loads it from the (per-request)
     * cache.
     *
     * @param string $class defining IStorable-derived class to load to
     * @param string $objId of the object to load
     * @return IStorable|null the data loaded from storage
     */
    protected function loadCachedObjectData(string $class, string $objId)
    {
        $scope = $class::getStorageScope();
        $key = $objId . $scope;
        $storable = apcu_fetch($key);
        if ($storable === false) {
            $serObj = $this->loadObjectData($scope, $objId);
            if (isset($serObj)) {
                /* @var IStorable $storable */
                $storable = new $class($objId);
                assert($storable instanceof IStorable);
                $storable->deserialize($serObj);
                apcu_store($key, $storable);
            } else {
                $storable = null;
            }
        }
        return $storable;
    }

    /**
     * Deletes an object from persistent storage.
     *
     * @param string $type  of the object to delete
     * @param string $objId of the object to delete
     * @return void
     */
    abstract protected function deleteObjectData(
        string $type,
        string $objId
    );

    /**
     * Write interface to a simple key-value storage that knows different
     * types of key-value pairs.
     *
     * @param string   $type  of the object to store
     * @param string   $objId of the object to store
     * @param stdClass $data  to store
     * @return void
     */
    abstract protected function storeObjectData(
        string $type,
        string $objId,
        stdClass $data
    );

    /**
     * Internal helper routine to store IChangeable objects.
     *
     * @param IStorable $storable to store
     * @return void
     */
    protected function storeObjectDataInt(IStorable $storable)
    {
        $objId = $storable->getId();
        $scope = $storable->getStorageScope();
        $cacheKey = $objId . $scope;
        $serObj = $storable->serialize();
        apcu_store($cacheKey, $storable);
        $this->storeObjectData($scope, $objId, $serObj);
    }

    /**
     * @param string $id of the user to load
     * @return User|null
     */
    public function loadUser(string $id)
    {
        $storable = $this->loadCachedObjectData(User::class, $id);
        assert(is_null($storable) || $storable instanceof User);
        return $storable;
    }

    /**
     * @param User $user to store
     * @return void
     */
    public function storeUser(User $user)
    {
        $this->storeObjectDataInt($user);
    }

    /**
     * @param string $elementId of the element to load
     * @return Element|null
     */
    public function loadElement(string $elementId)
    {
        $storable = $this->loadCachedObjectData(Element::class, $elementId);
        assert(is_null($storable) || $storable instanceof Element);
        return $storable;
    }

    /**
     * @param string $fileMetaId of the file meta data to load
     * @return FileMeta
     */
    public function loadFileMeta(string $fileMetaId)
    {
        $storable = $this->loadCachedObjectData(FileMeta::class, $fileMetaId);
        assert(is_null($storable) || $storable instanceof FileMeta);
        return $storable;
    }

    /**
     * @param Element $element to store
     * @return void
     */
    public function storeElement(Element $element)
    {
        $this->storeObjectDataInt($element);
    }

    /**
     * @param FileMeta $file to store
     * @return void
     */
    public function storeFileMeta(FileMeta $file)
    {
        $this->storeObjectDataInt($file);
    }

    /**
     * @param string $stampId create or touch a stamp
     * @return void
     */
    public function touchStamp(string $stampId)
    {
        $this->storeObjectData('stamps', $stampId, new stdClass());
    }

    /**
     * @param string $stampId stamp to check
     * @return bool
     */
    public function hasStamp(string $stampId) : bool
    {
        $serObj = $this->loadObjectData('stamps', $stampId);
        return isset($serObj);
    }

    /**
     * Move an uploaded file to the blob storage and store meta data.
     *
     * @param string $collection blob collection to add to
     * @param array  $fileInfo   info about the uploaded file
     * @return FileMeta
     * @throws ConfigurationError
     */
    public function internalizeUploadedFile(
        string $collection,
        array $fileInfo
    ) : FileMeta {
        // Add the blob to the blob storage
        $fileMeta = new FileMeta(BaseStorageAdapter::genRandomId());
        $fileHash = $this->blobStorage->eatBlob($collection, $fileInfo);
        $fileMeta->populate(
            $fileHash,
            $collection,
            basename($fileInfo['name']),
            $fileInfo['type'],
            $fileInfo['size']
        );

        // Add meta data to the (database) storage.
        $this->storeObjectDataInt($fileMeta);
        return $fileMeta;
    }

    /**
     * @param FileMeta $fileMeta to retrieve
     * @return string
     * @throws ConfigurationError
     */
    public function fetchBlobContents(FileMeta $fileMeta) : string
    {
        if (is_null($this->blobStorage)) {
            throw new ConfigurationError("blob storage not initialized");
        }

        return $this->blobStorage->pukeBlob($fileMeta);
    }

    /**
     * @param string $xid      hex-encoded transaction id
     * @param array  $undoInfo undo information
     * @return void
     */
    abstract public function storeUndoLog(string $xid, array $undoInfo);

    /**
     * @param string $xid hex-encoded transaction id
     * @return stdClass
     */
    abstract public function loadUndoLog(string $xid);

    /**
     * In this trivial instance of an IProcess, this method restricts edits to
     * non-published versions, only.
     *
     * @param Transaction $txn to validate
     * @return TransactionResult
     */
    public function validateTransaction(Transaction $txn) : TransactionResult
    {
        $result = new TransactionResult();

        if (!$txn->getAuthor()) {
            $result->addErrorMessage("user unknown");
            return $result;
        }

        // array consisting of IChange Objects and their validation result
        $precedingChanges = [];

        /** @var IChange $change */
        foreach ($txn->getChanges() as $change) {
            $result->merge($change->validate($precedingChanges));
            $precedingChanges[] = $change;
        }

        return $result;
    }

    /**
     * Apply transactional changes to the internal storage and commit its
     * results.
     *
     * @param Transaction   $txn         to apply to the storage
     * @param callable|null $visitorFunc per IStorable prior to storing
     * @return TransactionResult
     */
    public function applyTransaction(
        Transaction $txn,
        callable $visitorFunc = null
    ) : TransactionResult {
        /** @var IChange $change to apply */
        $undoEntries = [];
        $results = new TransactionResult();
        foreach ($txn->getChanges() as $change) {
            $class_name = get_class($change);
            $rbi = $change->collectRollbackInfo();
            $undoEntries[] = [$class_name, $rbi];
            $results->merge($change->apply());
        }

        foreach ($results->getTouchedStorables() as $obj) {
            if (isset($visitorFunc)) {
                $visitorFunc($obj);
            }
            $obj->storeVia($this);
        }

        // Update the url to element mapping
        $this->updateUrlMappingFor($results->getTouchedUrls());

        $xid = BaseStorageAdapter::genRandomId();
        $results->addTransactionId($xid);
        $this->storeUndoLog($xid, [
            'user' => $txn->getAuthor()->getId(),
            'timestamp' => date("c"),
            'actions' => $undoEntries
        ]);
        return $results;
    }

    /**
     * Reverts a transaction by applying its undo entries.
     *
     * @param array $undoEntries as collected by internalCommitTransaction
     * @return void
     */
    public function applyUndoEntries(array $undoEntries)
    {
        // Reverse-apply changes logged.
        foreach (array_reverse($undoEntries) as list($class_name, $rbi)) {
            $touched_objects = [];

            $changed_objects = $class_name::revert($this, $rbi);
            /** @var IStorable $obj */
            foreach ($changed_objects as $obj) {
                assert($obj instanceof IStorable);
                $key = get_class($obj) . '-' . $obj->getId();
                if (!array_key_exists($key, $touched_objects)) {
                    $touched_objects[$key] = $obj;
                }
            }

            foreach ($touched_objects as $key => $obj) {
                assert($obj instanceof IStorable);
                $obj->storeVia($this);
            }
        }
    }

    /**
     * @param string $xid of the transaction to roll back.
     * @return void
     */
    public function rollbackTransaction(string $xid)
    {
        $undoInfo = $this->loadUndoLog($xid);
        if (isset($undoInfo)) {
            if (property_exists($undoInfo, 'actions')) {
                $undoActions = $undoInfo->{'actions'};
            } else {
                // In earlier versions of Elements, we didn't store the
                // user and timestamp of the transaction, but just the
                // actions directly as an array.
                $undoActions = $undoInfo;
            }
            $this->applyUndoEntries($undoActions);
        } else {
            throw new RuntimeException("Unknown transaction id.");
        }
    }

    /**
     * Checks if a given element_id is a potentially valid element_id (which
     * is safe to be passed to the database, for example).
     *
     * This base implementation accepts alphanumeric chars in the hex range
     * only, no underscores or any other punctuation, but it well accepts
     * uppercase letters. Only element_ids of exactly 40 chars in length are
     * considered valid (20 bytes, hex encoded).
     *
     * @param string $elementId to check
     * @return bool
     */
    public static function isValidElementId(string $elementId) : bool
    {
        $filtered = preg_replace('/[^0-9a-fA-F]+/i', '', $elementId);
        return strlen($elementId) == 40 && $filtered == $elementId;
    }

    /**
     * Checks if a given $fileId is a potentially valid (which
     * is safe to be passed to the database, for example). We're currently
     * using the same checks as for element ids, see above.
     *
     * @param string $fileMetaId to check
     * @return bool
     */
    public static function isValidFileId(string $fileMetaId) : bool
    {
        return static::isValidElementId($fileMetaId);
    }

    /**
     * Checks if a given language is a potentially valid language (which is
     * safe to be passed to the database, for example).
     *
     * Accepts alphabetic chars, upper and lower case, and expects at least
     * two characters. Anything beyond 5 characters is not considered valid.
     *
     * @param string $language to check
     * @return bool
     */
    public static function isValidLanguage(string $language)
    {
        $filtered = preg_replace('/[^a-zA-Z\\_]+/i', '', $language);
        $size = strlen($language);
        return $size >= 2 && $size <= 5 && $filtered == $language;
    }

    /**
     * Checks if a given field_name is a potentially valid one (which is safe
     * to be passed to the database, for example).
     *
     * Accepts alphanumeric chars, upper and lower case plus dashes. Note that
     * field names may be used as (part of) css classes, where the use of
     * underscores is discouraged. Use CamelCase for field names, instead.
     *
     * @param string $fieldName to check
     * @return bool
     */
    public static function isValidFieldName(string $fieldName)
    {
        return preg_match('/[a-z][a-zA-Z0-9]{2,}/', $fieldName) === 1;
    }

    /**
     * @return void
     */
    public function invalidateUrlMapping()
    {
        $this->deleteObjectData('meta', 'url_element_mapping');
    }

    /**
     * @param stdClass $serData serialized url mapping data to cache
     * @return void
     */
    public function storeUrlMapping(stdClass $serData)
    {
        $this->storeObjectData('meta', 'url_element_mapping', $serData);
    }

    /**
     * Loads url mapping cache data, if available.
     * @return void
     */
    private function lazyLoadUrlMapping()
    {
        if (is_null($this->urlMapper)) {
            $this->urlMapper = new Cache\ElementUrlCache($this);
        } else {
            assert($this->urlMapper instanceof Cache\ElementUrlCache);
        }
    }

    /**
     * Loads url mapping cache data, if available.
     * @return stdClass|null the loaded map or null
     */
    public function loadUrlMapping()
    {
        return $this->loadObjectData('meta', 'url_element_mapping');
    }

    /**
     * Updates the URL mapping for just a few changed elements.
     *
     * @param [string] $elementIds id of the element to update the mapping for
     * @return void
     */
    public function updateUrlMappingFor(array $elementIds)
    {
        $this->lazyLoadUrlMapping();
        $this->urlMapper->updateUrlMappingFor($elementIds);
    }

    /**
     * @inheritdoc
     * @return void
     */
    public function recreateCacheData()
    {
        $this->urlMapper = null;
        $this->invalidateUrlMapping();

        $this->lazyLoadUrlMapping();
        $this->urlMapper->createUrlMapping();
    }

    /**
     * @inheritdoc
     * @param string $url to lookup
     * @return UrlPointer|null
     */
    public function loadUrlPointerByUrl(string $url)
    {
        $this->lazyLoadUrlMapping();
        return $this->urlMapper->getUrlMapping()->{$url} ?? null;
    }

    /**
     * @param string $elementId to lookup
     * @return UrlPointer[]
     */
    public function loadUrlPointersByElement(string $elementId) : array
    {
        $this->lazyLoadUrlMapping();
        return $this->urlMapper->getUrlPointersByElement($elementId);
    }

    /**
     * @param string  $parentId          of the element (may be different when
     *                                   changed within the same transaction)
     * @param [Slug]  $slugs             new set of slugs for the given element
     * @param string  $existingElementId for which to check and change slugs
     * @return array
     */
    public function checkSlugs(
        string $parentId,
        array $slugs,
        string $existingElementId = ''
    ) : array {
        $this->lazyLoadUrlMapping();
        return $this->urlMapper->checkSlugs(
            $parentId,
            $slugs,
            $existingElementId
        );
    }
}
