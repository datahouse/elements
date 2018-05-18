<?php

namespace Datahouse\Elements\Control\Exceptions;

/**
 * NoOpException that planTxn methods can throw to indicate that there's no
 * transaction necessary to be executed.
 *
 * @package Datahouse\Elements\Control\Exceptions
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class NoOpException extends \RuntimeException
{
}
