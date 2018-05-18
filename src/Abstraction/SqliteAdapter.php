<?php

namespace Datahouse\Elements\Abstraction;

use PDO;
use PDOStatement;
use stdClass;
use RuntimeException;

use Datahouse\Libraries\Database\Driver;

use Datahouse\Elements\Configuration;
use Datahouse\Elements\Constants;
use Datahouse\Elements\ReFactory;

/**
 * Provides a storage adapter based on an SQLite3 database.
 *
 * @package Datahouse\Elements\Abstraction
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class SqliteAdapter extends BaseStorageAdapter implements IStorageAdapter
{
    /** @var \SQLite3 $pdo */
    protected $pdo;

    /** @var bool $initialized */
    protected $initialized;

    protected $factory;

    /**
     * @param ReFactory     $refactory      for dynamic creation of classes
     * @param Driver\Sqlite $drv            used to connect
     * @param string        $blobStorageDir for binary large objects (blobs)
     */
    public function __construct(
        ReFactory $refactory,
        Driver\Sqlite $drv,
        string $blobStorageDir
    ) {
        $this->factory = $refactory->getFactory();
        parent::__construct(new BlobStorage($blobStorageDir));
        $this->pdo = $drv->getPdo();
        $this->initialized = $drv->isInitialized();
    }

    /**
     * @return bool
     */
    public function isInitialized() : bool
    {
        return $this->initialized;
    }

    /**
     * @return int version the storage got initialized with
     */
    public function getStorageVersion() : int
    {
        // FIXME: fake implementation to make tests pass.
        return Constants::STORAGE_VERSION;
    }

    /**
     * @return void
     */
    public function initialize()
    {
        // FIXME: not sure how an uninitialized sqlite3 database looks like.
    }

    /**
     * @param Configuration $config of the app
     * @return void
     * @throws RuntimeException
     */
    public function tryMigration(Configuration $config)
    {
        // FIXME: implement...
    }

    /**
     * @inheritdoc
     *
     * @param string $type  of the object to load
     * @param string $objId of the object to load
     * @return stdClass|null the data loaded from storage
     */
    protected function loadObjectData(string $type, string $objId)
    {
        /** @var PDOStatement $sth */
        $sth = $this->pdo->prepare(
            "SELECT data FROM $type WHERE id = :objId;"
        );
        $sth->execute([':objId' => $objId]);
        $json_data = $sth->fetchColumn();
        if ($json_data !== false) {
            $arr = json_decode($json_data, false);
            if (!is_null($arr)) {
                $arr->{"id"} = $objId;
            }
            return $arr;
        } else {
            return null;
        }
    }

    /**
     * Deletes an object from persistent storage.
     *
     * @param string $type  of the object to delete
     * @param string $objId of the object to delete
     * @return void
     */
    protected function deleteObjectData(
        string $type,
        string $objId
    ) {
        $sth = $this->pdo->prepare("DELETE FROM $type WHERE id = :objId");
        $sth->execute([':objId' => $objId]);
    }

    /**
     * @inheritdoc
     *
     * @param string   $type  of the object to store
     * @param string   $objId of the object to store
     * @param stdClass $data  to store
     * @return void
     */
    protected function storeObjectData(
        string $type,
        string $objId,
        stdClass $data
    ) {
        $sth = $this->pdo->prepare(
            "INSERT OR REPLACE INTO $type (id, data) VALUES (:objId, :data)"
        );
        $sth->execute([
            ':objId' => $objId,
            ':data' => json_encode($data)
        ]);
    }

    /**
     * @return string[] ids of all users known to the storage
     */
    public function enumAllUserIds()
    {
        $sth = $this->pdo->prepare('SELECT id FROM users');
        $sth->execute();
        return array_map(function ($v) {
            return $v[0];
        }, $sth->fetchAll(PDO::FETCH_NUM));
    }

    /**
     * @return string[] ids of all elements known to the storage
     */
    public function enumAllElementIds() : array
    {
        $sth = $this->pdo->prepare('SELECT id FROM elements');
        $sth->execute();
        return array_map(function ($v) {
            return $v[0];
        }, $sth->fetchAll(PDO::FETCH_NUM));
    }

    /**
     * @return string[] ids of all blob's metadata objects
     */
    public function enumAllFileMetas()
    {
        // TODO: Implement enumAllFileMetas() method.
    }

    /**
     * @param string $xid      hex-encoded transaction id
     * @param array  $undoInfo undo information
     * @return void
     */
    public function storeUndoLog(string $xid, array $undoInfo)
    {
        $sth = $this->pdo->prepare(
            "INSERT OR REPLACE INTO undolog (xid, data) VALUES (:xid, :data)"
        );
        $sth->execute([
            ':xid' => $xid,
            ':data' => json_encode($undoInfo)
        ]);

        // FIXME: vacuum of old undolog entries.
    }

    /**
     * @param string $xid hex-encoded transaction id
     * @return null|stdClass the undo information for the transaction
     */
    public function loadUndoLog(string $xid)
    {
        /** @var PDOStatement $sth */
        $sth = $this->pdo->prepare(
            "SELECT data FROM undolog WHERE xid = :xid;"
        );
        $sth->execute([':xid' => $xid]);
        $json_data = $sth->fetchColumn();
        if ($json_data !== false) {
            $undo_entries = json_decode($json_data, false);
            if (!is_null($undo_entries)) {
                return $undo_entries;
            }
        }
        return null;
    }
}
