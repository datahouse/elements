<?php

namespace Datahouse\Elements\Control\Admin\Setup;

use Datahouse\Elements\Abstraction\YamlAdapter;
use Datahouse\Elements\Tools\StorageDuplicator;

/**
 * A default and very first setup check that copies the example data, if
 * the storage hasn't been initialized, yet.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class SetupCheckStorageInitialized extends BaseSetupCheck
{
    /**
     * @return string
     */
    public function getDescription() : string
    {
        return "storage initialized";
    }

    /**
     * Check if the version of our storage matches the version in the code.
     *
     * @return bool
     */
    public function check() : bool
    {
        return $this->adapter->isInitialized();
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
        $this->adapter->initialize();

        // pass a dummy blob storage directory, as long as the
        // StorageMigrator doesn't copy it...
        $other_adapter = new YamlAdapter(null, '/srv/example-data', '/tmp');
        $mig = new StorageDuplicator($other_adapter, $this->adapter);
        $mig->copyData();
    }
}
