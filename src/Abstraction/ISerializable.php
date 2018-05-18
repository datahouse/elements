<?php

namespace Datahouse\Elements\Abstraction;

use stdClass;

use Datahouse\Elements\Abstraction\Exceptions\SerDesException;

/**
 * Simple interface to be implemented by objects intended to be serializable.
 * Note that contained objects may implement this, but not IStorable. Also
 * note that serialization as defined by this interface is only to or from
 * stdClass. We leave the actual serialization of the \stdClass to the
 * storage adapter.
 *
 * @package Datahouse\Elements\Abstraction
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
interface ISerializable
{
    /**
     * @return stdClass serialized or at least a serializable representation
     * of the object
     * @throws SerDesException
     */
    public function serialize() : stdClass;

    /**
     * @param stdClass $data to deserialize from
     * @return void
     * @throws SerDesException
     */
    public function deserialize(stdClass $data);
}
