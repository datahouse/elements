<?php

namespace Datahouse\Elements\Tools;

use Datahouse\Elements\Abstraction\ISerializable;

/**
 * Generic interface of a job for the background worker.
 *
 * @package Datahouse\Elements\Tools
 * @author Markus Wanner <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
interface BgJob extends ISerializable
{
    /**
     * Performs the actual job, called from the background worker.
     *
     * @return void
     */
    public function execute();
}
