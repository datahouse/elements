<?php

namespace Datahouse\Elements\Abstraction\Exceptions;

/**
 * Class SerDesException, which may contain additional context
 *
 * @package Datahouse\Elements\Abstraction\Exceptions
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class SerDesException extends \RuntimeException
{
    /* @var string $operation usually serialize or deserialize */
    private $operation;

    /* @var string $originalMessage */
    private $originalMessage;

    /* @var string[] $contexts */
    private $contexts;

    /**
     * ResourceNotFoundError constructor.
     *
     * @param string $operation throwing the exception
     * @param string $msg       describing the error
     */
    public function __construct(string $operation, string $msg)
    {
        parent::__construct($msg);
        $this->originalMessage = $msg;
        $this->operation = $operation;
        $this->contexts = [];
    }

    /**
     * @param string $desc giving some context to the error
     * @return void
     */
    public function addContext(string $desc)
    {
        $this->contexts[] = $desc;
        $this->message = $this->originalMessage . ' when trying to '
            . $this->operation . ' object: ' . implode(', ', $this->contexts);
    }
}
