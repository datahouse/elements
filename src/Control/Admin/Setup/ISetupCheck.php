<?php

namespace Datahouse\Elements\Control\Admin\Setup;

/**
 * Interface that checks during application setup need to implement.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
interface ISetupCheck
{
    /**
     * @return string user (admin) facing description of the check
     */
    public function getDescription() : string;

    /**
     * Returns true if the storage is up to date and doesn't need any change
     * by this ISetupCheck.
     *
     * @return bool
     */
    public function check() : bool;

    /**
     * Allows a check to skip all subsequent checks and mark them as failed.
     *
     * @return bool
     */
    public function abortOnFailure() : bool;

    /**
     * Perform the actual adaption of the storage to succeed in the future.
     *
     * @return void
     */
    public function adapt();
}
