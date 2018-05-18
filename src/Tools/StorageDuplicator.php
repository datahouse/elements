<?php

namespace Datahouse\Elements\Tools;

use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\YamlAdapter;

/**
 * Copies data from one storage adapter to another, using the methods of the
 * storage adapters themselves, except for copying from YAML to YAML.
 *
 * @package Datahouse\Elements\Tools
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class StorageDuplicator
{
    private $src;
    private $tgt;

    /**
     * @param IStorageAdapter $src source to read from
     * @param IStorageAdapter $tgt target to write to
     */
    public function __construct(IStorageAdapter $src, IStorageAdapter $tgt)
    {
        $this->src = $src;
        $this->tgt = $tgt;
    }

    /**
     * @return void
     */
    protected function copyUsers()
    {
        foreach ($this->src->enumAllUserIds() as $id) {
            $src_user = $this->src->loadUser($id);
            $this->tgt->storeUser($src_user);
        }
    }

    /**
     * @return void
     */
    protected function copyElements()
    {
        foreach ($this->src->enumAllElementIds() as $id) {
            $element = $this->src->loadElement($id);
            $this->tgt->storeElement($element);
        }
    }

    /**
     * @return void
     */
    protected function copyFileMetas()
    {
        foreach ($this->src->enumAllFileMetas() as $id) {
            $fileMeta = $this->src->loadFileMeta($id);
            $this->tgt->storeFileMeta($fileMeta);
        }
    }

    /**
     * @return void
     */
    public function copyData()
    {
        if ($this->src instanceof YamlAdapter
            && $this->tgt instanceof YamlAdapter
        ) {
            $src_dir = $this->src->getDir();
            if ($src_dir[strlen($src_dir) - 1] === '/') {
                $src_dir = substr($src_dir, 0, strlen($src_dir) - 1);
            }

            $tgt_dir = $this->tgt->getDir();
            if ($tgt_dir[strlen($tgt_dir) - 1] === '/') {
                $tgt_dir = substr($tgt_dir, 0, strlen($tgt_dir) - 1);
            }

            $cmd = "cp -r $src_dir/* $tgt_dir";
            error_log("running " . $cmd);
            system($cmd);
        } else {
            $this->copyUsers();
            $this->copyElements();
            $this->copyFileMetas();
        }

        // Regenerate metadata.
        $this->tgt->recreateCacheData();
    }
}
