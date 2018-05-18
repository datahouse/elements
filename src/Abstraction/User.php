<?php

namespace Datahouse\Elements\Abstraction;

use stdClass;
use RuntimeException;

use Datahouse\Elements\Abstraction\Exceptions\SerDesException;

/**
 * Represents a user, including the anonymous user.
 *
 * @package Datahouse\Elements\Abstraction
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class User implements ISerializable, IStorable
{
    const FIELDS = [
        'id' => ['type' => 'value', 'required' => true, 'implicit' => true],
        'groups' => ['type' => 'list'],
        'secret' => ['type' => 'map', 'required' => true]
    ];

    const ALLOW_ARBITRARY_FIELDS = false;
    use SerializationHelper;

    /** @var string $id unique id for this user, may be a username or an
     * email, for example, depending on the application or customer
     * requirements */
    private $id;

    /** @var array $groups this user is part of, an array of strings */
    private $groups;

    /** @var stdClass $secret for authentication */
    private $secret;

    /**
     * User constructor.
     *
     * @param string|null   $id          id of the user object to be created
     * @param stdClass|null $credentials credentials
     */
    public function __construct(
        string $id = null,
        stdClass $credentials = null
    ) {
        $this->id = $id;
        $this->secret = $credentials;
        $this->groups = [];
    }

    /**
     * @return stdClass serialized or at least a serializable representation
     * of the object
     * @throws SerDesException
     */
    public function serialize() : stdClass
    {
        try {
            return $this->genericSerialize();
        } catch (SerDesException $e) {
            $e->addContext("User " . $this->getId());
            throw $e;
        }
    }

    /**
     * @param stdClass $data to deserialize from
     * @return void
     * @throws SerDesException
     */
    public function deserialize(stdClass $data)
    {
        try {
            $this->genericDeserialize($data);
        } catch (SerDesException $e) {
            $e->addContext("User " . $this->getId());
            throw $e;
        }
    }

    /**
     * @return User object for the anonymous user
     */
    public static function getAnonymousUser()
    {
        return new User('');
    }

    /**
     * @return bool true if this is an anonymous user
     */
    public function isAnonymousUser() : bool
    {
        return empty($this->id);
    }

    /**
     * @return string unique id of this user
     */
    public function getId() : string
    {
        if (is_null($this->id)) {
            throw new RuntimeException(
                "Must set an id via constructor or populate method, first."
            );
        }
        return $this->id;
    }

    /**
     * @return stdClass authentication information
     */
    public function getSecret() : stdClass
    {
        return $this->secret;
    }

    /**
     * @param stdClass $secret authentication information
     * @return void
     */
    public function setSecret(stdClass $secret)
    {
        $this->secret = $secret;
    }

    /**
     * @inheritdoc
     * @return string
     */
    public static function getStorageScope() : string
    {
        return "users";
    }

    /**
     * Stores this user via a given IStorageAdapter.
     *
     * @param IStorageAdapter $adapter used to store the element
     * @return void
     */
    public function storeVia(IStorageAdapter $adapter)
    {
        $adapter->storeUser($this);
    }

    /**
     * @param string $groupname to which to add the user
     * @return void
     */
    public function addGroup(string $groupname)
    {
        $this->groups[] = $groupname;
    }

    /**
     * Get a list of names of groups this user is part of.
     *
     * @return array of strings
     */
    public function getGroups()
    {
        return $this->groups;
    }
}
