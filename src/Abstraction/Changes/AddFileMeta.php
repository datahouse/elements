<?php

namespace Datahouse\Elements\Abstraction\Changes;

use stdClass;
use RuntimeException;

use Datahouse\Elements\Abstraction\FileMeta;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\TransactionResult;

/**
 * @package Datahouse\Abstraction/Changes
 * @author Helmar Tröller (htr) <helmar.troeller@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class AddFileMeta extends BaseChange implements IChange
{
    protected $fileMeta;

    /**
     * @param FileMeta $fileMeta about the uploaded file
     */
    public function __construct(FileMeta $fileMeta)
    {
        $this->fileMeta = $fileMeta;
    }

    /**
     * @param IChange[] $precedingChanges previous changes in transaction
     * @return TransactionResult
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("precedingChanges"))
     */
    public function validate(array $precedingChanges) : TransactionResult
    {
        return new TransactionResult();
    }

    /**
     * collect rollback information
     *
     * @return array
     */
    public function collectRollbackInfo()
    {
        return [
            'fileMetaId' => $this->fileMeta->getId(),
        ];
    }

    /**
     * @inheritdoc
     * @return TransactionResult result of application
     */
    public function apply()
    {
        $result = new TransactionResult();
        $result->addTouchedStorable($this->fileMeta);
        return $result;
    }

    /**
     * @param IStorageAdapter $adapter storage adapter that is being used
     * @param stdClass        $rbi     the rollback information
     * @return array of changed IStorable objects
     */
    public static function revert(
        IStorageAdapter $adapter,
        stdClass $rbi
    ) : array {
        $fileMeta = $adapter->loadFileMeta($rbi->{'fileMetaId'});

        // TODO: remove the file meta data from the database, or at least
        // mark it deleted.
        throw new RuntimeException("not implemented, yet");

        // return [$fileMeta];
    }
}
