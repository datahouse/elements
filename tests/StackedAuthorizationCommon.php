<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\BaseStorageAdapter;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Constants;
use Datahouse\Elements\Control\Authorization\StackedAllowDenyAuthorizationHandler;

/**
 * Trait StackedAuthCommon featuring test cases for the
 * StackedAllowDenyAuthHandler that are storage adapter agnostic.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
trait StackedAuthorizationCommon
{
    /**
     * Load user 'alice' from test data.
     * @return void
     */
    public function testLoadUser()
    {
        $user_alice = $this->adapter->loadUser('alice');
        $this->assertNotNull(
            $user_alice,
            "User alice not found in test storage"
        );
        $this->assertEquals($user_alice->getId(), 'alice');
    }

    /**
     * Test loading the only child element of the root.
     * @return void
     */
    public function testLoadElement()
    {
        $element = $this->adapter->loadElement(
            '471eb91bd28ec1ae26563952d6b5da42720a607b'
        );
        $this->assertNotNull($element, "Element not found in test storage");
        $this->assertEquals(
            Constants::ROOT_ELEMENT_ID,
            $element->getParentId()
        );
    }

    /**
     * Test loading children of a test element.
     * @return void
     */
    public function testElementChildren()
    {
        $test_element_id = '471eb91bd28ec1ae26563952d6b5da42720a607b';
        /** @var Element $element */
        $element = $this->adapter->loadElement($test_element_id);
        $this->assertNotNull($element, "Element not found in test storage");

        /** @var ElementVersion $version */
        $version = $element->getVersion($element->getNewestVersionNumber());
        $children = $version->getChildren();
        $this->assertEquals($children, [
            'ae51ff67d4e62ae99cce9f4710e5b55c703625ad',
            '5a8ca84c7d4d9b055f05c55b1f707f223979d387',
            '0ad052dd9f32405521e43c6ebdc52f5a025493b2'
        ]);

        $child_one = $this->adapter->loadElement($children[0]);
        $this->assertEquals($test_element_id, $child_one->getParentId());

        $child_two = $this->adapter->loadElement($children[1]);
        $this->assertEquals($test_element_id, $child_two->getParentId());
    }

    /**
     * Test loading a leaf element and its parent.
     * @return void
     */
    public function testLoadChildElement()
    {
        $element = $this->adapter->loadElement(
            'ae51ff67d4e62ae99cce9f4710e5b55c703625ad'
        );
        $this->assertNotNull($element);
        $this->assertNotNull($element->getParentId());
        $parent = $this->adapter->loadElement($element->getParentId());
        $this->assertNotNull($parent);
    }

    /**
     * Test the default authorization handler.
     * @return void
     */
    public function testStackedAuthViewPermissions()
    {
        $av_test_map_fn = function ($v) {
            return $v[0] . "|" . implode(",", $v[1]);
        };

        $element = $this->adapter->loadElement(
            '471eb91bd28ec1ae26563952d6b5da42720a607b'
        );
        $this->assertNotNull($element);
        $this->assertEquals(
            Constants::ROOT_ELEMENT_ID,
            $element->getParentId()
        );

        $user_alice = $this->adapter->loadUser('alice');
        $this->assertNotNull($user_alice);

        $user_anonymous = User::getAnonymousUser();
        $auth = new StackedAllowDenyAuthorizationHandler($this->adapter);

        // Being part of the 'editors' group, Alice sees even the new
        // version 1 in language english.
        $av = $auth->getAuthorizedVersions('view', $user_alice, $element);
        $this->assertEquals(["1|de,en", "2|en"], array_map($av_test_map_fn, $av));

        // User anonymous should only see version 1.
        $av = $auth->getAuthorizedVersions('view', $user_anonymous, $element);
        $this->assertEquals(["1|de,en"], array_map($av_test_map_fn, $av));

        // check a child element
        $element = $this->adapter->loadElement(
            'ae51ff67d4e62ae99cce9f4710e5b55c703625ad'
        );
        $this->assertNotNull($element);
        $av = $auth->getAuthorizedVersions('view', $user_alice, $element);
        $this->assertEquals(["1|en,de"], array_map($av_test_map_fn, $av));
    }

    /**
     * Check edit permissions via the default authorization handler.
     * @return void
     */
    public function testStackedAuthEditPermissions()
    {
        $element = $this->adapter->loadElement(
            '471eb91bd28ec1ae26563952d6b5da42720a607b'
        );
        $this->assertNotNull($element);
        $this->assertEquals(
            Constants::ROOT_ELEMENT_ID,
            $element->getParentId()
        );

        $user_alice = $this->adapter->loadUser('alice');
        $this->assertNotNull($user_alice);
        $user_bob = $this->adapter->loadUser('bob');
        $this->assertNotNull($user_bob);
        $user_carol = $this->adapter->loadUser('carol');
        $this->assertNotNull($user_carol);

        $auth = new StackedAllowDenyAuthorizationHandler($this->adapter);
        $av = $auth->getAuthorizedVersions('edit', $user_alice, $element);
        $this->assertTrue(count($av) > 0);
        $av = $auth->getAuthorizedVersions('edit', $user_bob, $element);
        $this->assertTrue(count($av) > 0);
        $av = $auth->getAuthorizedVersions('edit', $user_carol, $element);
        $this->assertEquals(count($av), 0);

        // same should apply for a child page
        $element = $this->adapter->loadElement(
            'ae51ff67d4e62ae99cce9f4710e5b55c703625ad'
        );
        $this->assertNotNull($element);
        $av = $auth->getAuthorizedVersions('edit', $user_alice, $element);
        $this->assertTrue(count($av) > 0);
        $av = $auth->getAuthorizedVersions('edit', $user_bob, $element);
        $this->assertTrue(count($av) > 0);
        $av = $auth->getAuthorizedVersions('edit', $user_carol, $element);
        $this->assertEquals(count($av), 0);
    }
}
