<?php

namespace Datahouse\Elements\Control\Admin\Setup;

use Datahouse\Elements\Abstraction\IStorageAdapter;

/**
 * Common base class for checks during application setup.
 *
 * @package Datahouse\Elements\Control\Admin\Setup
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
abstract class BaseSetupCheck implements ISetupCheck
{
    protected $adapter;

    /**
     * @param IStorageAdapter $adapter to use
     */
    public function __construct(IStorageAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Default to continue with other checks, even if the current check failed.
     * @return bool
     */
    public function abortOnFailure() : bool
    {
        return false;
    }
}
