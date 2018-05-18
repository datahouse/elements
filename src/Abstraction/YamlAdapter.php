<?php

namespace Datahouse\Elements\Abstraction;

use Datahouse\Elements\Configuration;
use stdClass;
use RuntimeException;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

use Datahouse\Elements\Constants;
use Datahouse\Elements\ReFactory;
use Datahouse\Elements\Tools\CustomYamlFileDumper;
use Datahouse\Elements\Tools\YamlStorageMigrator;

/**
 * Provides a storage adapter that simply uses YAML files on disk.
 *
 * Note that this class should always be limited to the code that the current
 * version needs. All migration code or accessor methods for old versions can
 * be found in the YamlStorageMigrator class.
 *
 * @package Datahouse\Elements\Abstraction
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class YamlAdapter extends BaseStorageAdapter implements IStorageAdapter
{
    private $factory;
    private $dir;
    private $initialized;

    /**
     * @param ReFactory $refactory      used to instantiate the migrator, if
     *                                  needed
     * @param string    $yamlDir        path to the storage to use
     * @param string    $blobStorageDir for large binary objects
     */
    public function __construct(
        ReFactory $refactory,
        string $yamlDir,
        string $blobStorageDir
    ) {
        $this->factory = $refactory->getFactory();
        $this->dir = $yamlDir;
        assert(!empty($blobStorageDir));
        $blobStorage = new BlobStorage($blobStorageDir);
        parent::__construct($blobStorage);
        $this->initialized = is_dir($yamlDir . '/users') &&
            is_dir($yamlDir . '/elements') &&
            is_dir($yamlDir . '/meta');
    }

    /**
     * FIXME: this is part of a hack to facilitate the job of the
     * StorageDuplicator. Remove!
     *
     * @return string
     */
    public function getDir()
    {
        return $this->dir;
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
        $path = $this->dir . '/version';
        if (file_exists($path)) {
            return intval(trim(file_get_contents($path), " \t\n\r\0\x0B"));
        } else {
            return 1;
        }
    }

    /**
     * @return void
     */
    public function initialize()
    {
        $fs = new Filesystem();
        $fs->mkdir($this->dir);
        $fs->mkdir($this->dir . '/users');
        $fs->mkdir($this->dir . '/meta');
        $fs->mkdir($this->dir . '/elements');
        $fs->mkdir($this->dir . '/filemeta');
        $fs->mkdir($this->dir . '/attic');
        $fs->mkdir($this->dir . '/attic/elements');
        $fs->mkdir($this->dir . '/tmp');
        $fs->mkdir($this->dir . '/stamps');
        $this->initialized = true;

        $fh = fopen($this->dir . '/version', 'w');
        fwrite($fh, Constants::STORAGE_VERSION . "\n");
        fclose($fh);
    }

    /**
     * @param Configuration $config to use
     * @return void
     * @throws RuntimeException
     */
    public function tryMigration(Configuration $config)
    {
        $migrator = new YamlStorageMigrator(
            $config,
            $this->dir,
            $this->blobStorage->getBaseDir(),
            function () : int {
                return $this->getStorageVersion();
            }
        );
        $migrator->migrate();
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
        $path = $this->dir . '/' . $type . '/' . $objId;
        if (file_exists($path)) {
            $yaml = new Parser();
            $contents = file_get_contents($path);
            if ($contents !== false) {
                $flags = Yaml::PARSE_OBJECT_FOR_MAP;
                $obj = $yaml->parse($contents, $flags);
                if (!is_null($obj)) {
                    $obj->{"id"} = $objId;
                }
                return $obj;
            } else {
                throw new \RuntimeException("unable to read '$path'");
            }
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
        $path = $this->dir . '/' . $type . '/' . $objId;
        if (file_exists($path)) {
            unlink($path);
        }
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
        if (!is_dir($this->dir . '/tmp')) {
            $fs = new Filesystem();
            $fs->mkdir($this->dir . '/tmp');
        }
        if ($type === 'stamps' && !is_dir($this->dir . '/stamps')) {
            $fs = new Filesystem();
            $fs->mkdir($this->dir . '/stamps');
        }
        $randomFileName = bin2hex(openssl_random_pseudo_bytes(10));
        $tmpPath = $this->dir . '/tmp/' . $randomFileName;
        $writer = new CustomYamlFileDumper($tmpPath, 2);
        $flags = Yaml::DUMP_OBJECT_AS_MAP
            | Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE
            | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK;
        $writer->dump($data, 999, 0, $flags);

        // atomic override
        $finalPath = $this->dir . '/' . $type . '/' . $objId;
        rename($tmpPath, $finalPath);
    }

    /**
     * @return string[] ids of all users in storage
     */
    public function enumAllUserIds()
    {
        return array_filter(
            scandir($this->dir . '/users/'),
            function ($name) {
                return $name != '.' && $name != '..';
            }
        );
    }

    /**
     * @return string[] ids of all elements in storage
     */
    public function enumAllElementIds() : array
    {
        return array_filter(
            scandir($this->dir . '/elements/'),
            function ($name) {
                $valid = BaseStorageAdapter::isValidElementId($name);
                if (!$valid && $name != '.' && $name != '..') {
                    error_log(
                        "WARNING: invalid filename in elements dir: "
                        . $name
                    );
                }
                return $valid;
            }
        );
    }

    /**
     * @param Element $element to store
     * @return void
     */
    public function storeElement(Element $element)
    {
        // Count unreachable versions, first.
        $countUnreachable = 0;
        /** @var ElementVersion $ev */
        foreach ($element->getVersions() as $vno => $ev) {
            if (!$ev->isReachable()) {
                $countUnreachable += 1;
            }
        }

        // If above a certain threshold, move to an attic element.
        if ($countUnreachable > Constants::YAML_MIN_VERSION_ENTRIES) {
            error_log(
                "Element " . $element->getId() . " has " . $countUnreachable
                . " unreachable versions, pruning."
            );
            $atticElement = new Element(
                $element->getId(),
                $element->getParentId()
            );
            $atticElement->setType($element->getType());
            $movedVersions = [];
            foreach ($element->getVersions() as $vno => $ev) {
                if (!$ev->isReachable()) {
                    $atticElement->addVersion($vno, $ev);
                    $movedVersions[] = $vno;
                }
            }

            foreach ($movedVersions as $vno) {
                $element->removeVersion($vno);
            }

            $objId = $atticElement->getId() . '-' . gmdate('Ymd\THis');
            $serObj = $atticElement->serialize();
            $this->storeObjectData('attic/elements', $objId, $serObj);
        }

        $this->storeObjectDataInt($element);
    }

    /**
     * @return string[] ids of all blob's metadata objects
     */
    public function enumAllFileMetas()
    {
        return array_filter(
            scandir($this->dir . '/filemeta/'),
            function ($name) {
                return $name != '.' && $name != '..';
            }
        );
    }

    /**
     * @param string $xid      hex-encoded transaction id
     * @param array  $undoInfo undo information
     * @return void
     */
    public function storeUndoLog(string $xid, array $undoInfo)
    {
        // hopefully doesn't need caching
        $undoLog = $this->loadObjectData('meta', 'undo-log');
        if (is_null($undoLog)) {
            $undoLog = new stdClass();
        }

        // For performance reasons, start a new undo log file every N entries.
        // Note that the counterpart in loadUndoEntries doesn't really support
        // reading these, yet.
        if (count(get_object_vars($undoLog))
            >= Constants::YAML_UNDO_ENTRIES_PER_FILE
        ) {
            $backupFileName = 'undo-log-' . gmdate('Ymd\THis');
            $this->storeObjectData('meta', $backupFileName, $undoLog);
            $undoLog = new stdClass();
        }

        $undoLog->{$xid} = $undoInfo;
        $this->storeObjectData('meta', 'undo-log', $undoLog);
    }

    /**
     * @param string $xid hex-encoded transaction id
     * @return null|stdClass
     */
    public function loadUndoLog(string $xid)
    {
        // Hopefully doesn't need caching...
        $undo_log = $this->loadObjectData('meta', 'undo-log');
        return $undo_log->{$xid} ?? null;
    }
}
