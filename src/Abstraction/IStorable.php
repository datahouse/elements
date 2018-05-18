<?php

namespace Datahouse\Elements\Abstraction;

/**
 * All objects that implement IStorable can be persisted via a
 * IStorageAdapter. Usually, such objects implement ISerializable as well,
 * however, not all ISerializable objects necessarily need to be IStorable,
 * directly.
 *
 * @package Datahouse\Elements\Abstraction
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
interface IStorable extends ISerializable
{
    /**
     * Returns the storage scope of the object.
     *
     * @return string
     */
    public static function getStorageScope() : string;

    /**
     * Get an id for this object that's unique within the type.
     *
     * @return string
     */
    public function getId() : string;

    /**
     * Store the object via the given storage adapter.
     *
     * @param IStorageAdapter $adapter used to store the element
     * @return void
     */
    public function storeVia(IStorageAdapter $adapter);
}
