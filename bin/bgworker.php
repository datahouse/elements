<?php

// If elements is included via composer, try the top-level project's
// autoloader configuration, first.
$parentAutoloader = __DIR__ . '/../../../../vendor/autoload.php';
if (file_exists($parentAutoloader)) {
    require_once($parentAutoloader);
} else {
    require_once(__DIR__ . '/../vendor/autoload.php');
    error_log("WARNING: using elements autoloader");
}

use Datahouse\Elements\Tools\BgWorkerServer;

/* Turn on error reporting. */
error_reporting(E_ALL);

/* Allow the script to hang around waiting for connections. */
set_time_limit(0);

/* Turn on implicit output flushing so we see what we're getting
 * as it comes in. */
ob_implicit_flush();

$bgWorker = new BgWorkerServer();
exit($bgWorker->main());
