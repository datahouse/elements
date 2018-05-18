<?php

namespace Datahouse\Elements;

/**
 * A simplistic wrapper around the Factory object with the sole purpose of
 * returning that factory object.
 *
 * This is a hack around limitations of Dice with constructor parameters.
 * Every object that needs the Factory (for dynamic creation of objects or
 * rather acquiring pointers to existing, global instances) or a
 * Configuration object, should use this ReFactory object in its constructor,
 * instead, because Dice can pass on this one, but not the Factory directly.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class ReFactory
{
    private $factory;

    /**
     * @param Factory $factory to wrap
     */
    public function __construct(Factory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * @return Factory
     */
    public function getFactory() : Factory
    {
        return $this->factory;
    }
}
