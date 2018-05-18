<?php

namespace Datahouse\Elements\Abstraction\Changes;

use stdClass;

use Datahouse\Elements\Abstraction\TransactionResult;
use Datahouse\Elements\Abstraction\IStorageAdapter;

/**
 * An interface defining changes to persistent data.
 *
 * @package Datahouse\Elements\Abstraction\Changes
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
interface IChange
{
    /**
     * Check whether or not all preconditions for applying this change are
     * met.
     *
     * @param IChange[] $precedingChanges previous changes in transaction
     * @return TransactionResult
     */
    public function validate(array $precedingChanges) : TransactionResult;

    /**
     * After validating and just before applying a change, this method is
     * called by the storage adapter to collect all information necessary to
     * undo the change.
     *
     * @return array associative, containing all data necessary to undo
     * this change.
     */
    public function collectRollbackInfo();

    /**
     * Apply the change to objects loaded from the storage adapter, returning
     * a list of changed objects (which may be of different type).
     *
     * @return TransactionResult result of application
     */
    public function apply();

    /**
     * @param IStorageAdapter $adapter used to persist this change
     * @param stdClass        $rbi     rollback information originally
     * gathered via the @see collectRollbackInfo method.
     * @return array of changed IStorable objects
     */
    public static function revert(
        IStorageAdapter $adapter,
        stdClass $rbi
    ) : array;

    /**
     * checkAddsElementVersion
     *
     * @param int $versionNumber the number of the version to be checked
     * @return boolean flag whether this adds an ElementVersion
     */
    public function checkAddsElementVersion(int $versionNumber) : bool;

    /**
     * check if a change adds a language to an existing version
     *
     * @param string $lang language to be checked for
     * @return boolean
     */
    public function checkAddsLanguage(string $lang) : bool;

    /**
     * check if the change would set a new parent for the given element.
     *
     * @param string $elementId to check
     * @return array of tuple bool, string of the new parent id
     */
    public function checkSetsParentId(string $elementId);
}
