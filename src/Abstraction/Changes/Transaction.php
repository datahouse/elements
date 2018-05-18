<?php

namespace Datahouse\Elements\Abstraction\Changes;

use Datahouse\Elements\Abstraction\User;

/**
 * A transaction is a collection of one or more IChange objects to be applied
 * or revoked together.
 *
 * @package Datahouse\Elements\Abstraction\Changes
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class Transaction
{
    /** @var User $author */
    protected $author;
    /** @var array changes */
    protected $changes;

    /**
     * Transaction constructor.
     *
     * @param array $changes the actual set of changes to apply
     */
    public function __construct(array $changes)
    {
        if (!isset($changes) || empty($changes)) {
            throw new \RuntimeException('Transaction could not be constructed, no Changes available!');
        }
        $this->changes = $changes;
    }

    /**
     * @param User $author for whom to run this transaction
     * @return void
     */
    public function setAuthor(User $author)
    {
        $this->author = $author;
    }

    /**
     * @return User author of this transaction
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @return IChange[] changes contained
     */
    public function getChanges()
    {
        return $this->changes;
    }
}
