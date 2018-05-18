<?php

namespace Datahouse\Elements\Abstraction;

use RuntimeException;
use stdClass;

use Datahouse\Elements\Configuration;
use Datahouse\Elements\Abstraction\Changes\Transaction;

/**
 * Defines how the different storage adapters interact with the outside world.
 *
 * @package Datahouse\Elements\Abstraction
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
interface IStorageAdapter
{
    /**
     * @return bool if the storage is properly initialized and ready to
     * store data.
     */
    public function isInitialized() : bool;

    /**
     * @return int version the storage got initialized with
     */
    public function getStorageVersion() : int;

    /**
     * Initializes the storage to an empty state.
     * @return void
     */
    public function initialize();

    /**
     * @param string $id of the user to load
     * @return User|null
     */
    public function loadUser(string $id);

    /**
     * @param User $user to store
     * @return void
     */
    public function storeUser(User $user);

    /**
     * @return string[] ids of all users known to the storage
     */
    public function enumAllUserIds();

    /**
     * @param string $elementId of the element to load
     * @return Element
     */
    public function loadElement(string $elementId);

    /**
     * @param Element $element to store
     * @return void
     */
    public function storeElement(Element $element);

    /**
     * @param string $stampId create or touch a stamp
     * @return void
     */
    public function touchStamp(string $stampId);

    /**
     * @param string $stampId stamp to check
     * @return bool
     */
    public function hasStamp(string $stampId) : bool;

    /**
     * @return string[] ids of all blob's metadata objects
     */
    public function enumAllFileMetas();

    /**
     * @param string $fileMetaId of the file meta data to load
     * @return FileMeta
     */
    public function loadFileMeta(string $fileMetaId);

    /**
     * @param FileMeta $file to store
     * @return void
     */
    public function storeFileMeta(FileMeta $file);

    /**
     * Move an uploaded file to the blob storage and store meta data.
     *
     * @param string $collection blob collection to add to
     * @param array  $fileInfo   info about the uploaded blob
     * @return FileMeta
     */
    public function internalizeUploadedFile(
        string $collection,
        array $fileInfo
    ) : FileMeta;

    /**
     * @param FileMeta $fileMeta to retrieve
     * @return string
     */
    public function fetchBlobContents(FileMeta $fileMeta) : string;

    /**
     * @return string[] ids of all elements known to the storage
     */
    public function enumAllElementIds() : array;

    /**
     * @param string $url used to lookup a UrlPointer
     * @return UrlPointer|null
     */
    public function loadUrlPointerByUrl(string $url);

    /**
     * @param string $elementId used to lookup a UrlPointer
     * @return UrlPointer[]
     */
    public function loadUrlPointersByElement(string $elementId);

    /**
     * @param string $xid      hex-encoded transaction id
     * @param array  $undoInfo undo information from changes applied
     * @return void
     */
    public function storeUndoLog(string $xid, array $undoInfo);

    /**
     * Given a Transaction with one or more IChanges, this method checks the
     * transaction prior to its application by calling the validate methods
     * of the underlying changes.
     *
     * @param Transaction $txn to validate
     * @return TransactionResult
     */
    public function validateTransaction(Transaction $txn) : TransactionResult;

    /**
     * @param Transaction $txn         to apply to the storage
     * @param callable    $visitorFunc per IStorable prior to storing
     * @return TransactionResult including a transaction id (for rollbacks)
     */
    public function applyTransaction(
        Transaction $txn,
        callable $visitorFunc
    ) : TransactionResult;

    /**
     * @param string $xid of the transaction to roll back.
     * @return void
     */
    public function rollbackTransaction(string $xid);

    /**
     * Checks if a given element_id is a potentially valid element_id (which
     * is safe to be passed to the database, for example).
     *
     * @param string $elementId to check
     * @return bool
     */
    public static function isValidElementId(string $elementId);

    /**
     * Checks if a given $fileId is a potentially valid (which
     * is safe to be passed to the database, for example).
     *
     * @param string $fileMetaId to check
     * @return bool
     */
    public static function isValidFileId(string $fileMetaId) : bool;

    /**
     * Checks if a given language is a potentially valid language (which is
     * safe to be passed to the database, for example).
     *
     * @param string $language to check
     * @return bool
     */
    public static function isValidLanguage(string $language);

    /**
     * Checks if a given field_name is a potentially valid one (which is safe
     * to be passed to the database, for example).
     *
     * @param string $fieldName to check
     * @return bool
     */
    public static function isValidFieldName(string $fieldName);

    /**
     * Trigger recreation of all metadata.
     *
     * @return void
     */
    public function recreateCacheData();

    /**
     * @param [string] $elementIds id of the element to update the mapping for
     * @return void
     */
    public function updateUrlMappingFor(array $elementIds);

    /**
     * @return void
     */
    public function invalidateUrlMapping();

    /**
     * @param stdClass $serData serialized url mapping data to cache
     * @return void
     */
    public function storeUrlMapping(stdClass $serData);

    /**
     * Loads url mapping cache data, if available.
     * @return stdClass|null the loaded map or null
     */
    public function loadUrlMapping();

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
    ) : array;

    /**
     * @param Configuration $config to use
     * @return void
     * @throws RuntimeException
     */
    public function tryMigration(Configuration $config);
}
