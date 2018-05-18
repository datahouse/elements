<?php

namespace Datahouse\Elements\Control\Admin\Setup;

use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Configuration;
use Datahouse\Elements\Constants;

/**
 * Usually the second setup check: ensure storage versions match
 *
 * @package Datahouse\Elements\Control\Admin\Setup
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class SetupCheckStorageVersion extends BaseSetupCheck
{
    /**
     * SetupCheckStorageVersion constructor.
     *
     * @param IStorageAdapter $adapter to use
     * @param Configuration   $config  of the app
     */
    public function __construct(
        IStorageAdapter $adapter,
        Configuration $config
    ) {
        parent::__construct($adapter);
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function getDescription() : string
    {
        return "storage version matches";
    }

    /**
     * Check if the version of our storage matches the version in the code.
     *
     * @return bool
     */
    public function check() : bool
    {
        $expVersion = Constants::STORAGE_VERSION;
        $effVersion = $this->adapter->getStorageVersion();
        return $effVersion == $expVersion;
    }

    /**
     * If this check is not successful, all further checks will be skipped.
     *
     * @return bool
     */
    public function abortOnFailure() : bool
    {
        return true;
    }

    /**
     * @return void
     */
    public function adapt()
    {
        $this->adapter->tryMigration($this->config);
    }
}
