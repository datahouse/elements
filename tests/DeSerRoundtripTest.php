<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\BaseStorageAdapter;
use Datahouse\Elements\Abstraction\SerializationHelper;
use stdClass;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementContents;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\ISerializable;

/**
 * Tests for serialization and deserialization.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class DeSerRoundtripTest extends \PHPUnit_Framework_TestCase
{
    public function testCamelCasify()
    {
        $testFn = function ($exp, $in) {
            $this->assertEquals($exp, SerializationHelper::toCamelCase($in));
        };

        $testFn('realBigSnake', 'real_big_snake');
        $testFn('languages', 'languages');
        $testFn('languages', 'languages_');
        $testFn('Subs', '__subs');
        $testFn('Subs', '__subs_');
        $testFn('serializeSubs', 'serialize___subs');
        $testFn('rockNRoll', 'rock_n_roll');
    }

    /**
     * Tests a deserialize/serialize roundtrip of an ISerializable object.
     *
     * @param ISerializable $obj       under test
     * @param stdClass      $input     data to load from
     * @param stdClass      $expResult expected serialized output
     * @return void
     */
    public function roundtrip(
        ISerializable $obj,
        stdClass $input,
        stdClass $expResult
    ) {
        $obj->deserialize($input);
        $output = $obj->serialize();
        $this->assertEquals($expResult, $output);
    }

    /**
     * Test a round-trip on ElementContents (without sub elements).
     * @return void
     */
    public function testElementContentsRoundtrip()
    {
        $data = new stdClass();
        $data->{'title'} = 'My title';
        $data->{'contents'} = 'Some content to store';
        $data->{'color'} = 'red';
        $data->{'random_attribute'} = 'thick';

        $ec = new ElementContents();
        $this->roundtrip($ec, $data, $data);
    }

    /**
     * Test a round-trip with a minimal ElementVersion.
     * @return void
     */
    public function testMinimalElementVersionRoundtrip()
    {
        $data = new stdClass();
        $data->{'state'} = 'published';
        $data->{'languages'} = new stdClass();
        $data->{'languages'}->{'en'} = new stdClass();

        $expResult = new stdClass();
        $expResult->{'state'} = 'published';

        $ev = new ElementVersion();
        $this->roundtrip($ev, $data, $data);
    }

    /**
     * Test a round-trip with a somewhat populated ElementVersion
     * @return void
     */
    public function testDeserializationOfEmptyFields()
    {
        $data = new stdClass();
        $data->{'state'} = 'published';
        $data->{'languages'} = new stdClass();
        $data->{'languages'}->{'en'} = new stdClass();
        $data->{'slugs'} = [new stdClass()];
        $data->{'slugs'}[0]->{'url'} = '/resource';
        $data->{'slugs'}[0]->{'language'} = 'en';

        $ev = new ElementVersion();
        $this->roundtrip($ev, $data, $data);
    }

    /**
     * Create a test ElementVersion object in stdClass representation
     * @return stdClass
     */
    private function createExtendedVersion() : stdClass
    {
        $sub1 = new stdClass();
        $sub1->{'anotherField'} = 'other text';

        $sub2 = new stdClass();
        $sub2->{'anotherField'} = 'other text';

        $subs = new stdClass();
        $subs->{'teasers'} = [$sub1, $sub2];

        $data = new stdClass();
        $data->{'state'} = 'published';
        $data->{'languages'} = new stdClass();
        $data->{'languages'}->{'en'} = new stdClass();
        $data->{'languages'}->{'en'}->{'someField'} = 'some text';
        $data->{'languages'}->{'en'}->{'__subs'} = $subs;
        $data->{'children'} = ['23452354'];

        $slug = new stdClass();
        $slug->{'url'} = '/test_url';
        $slug->{'default'} = true;
        $slug->{'language'} = 'en';
        $data->{'slugs'} = [$slug];

        return $data;
    }

    /**
     * Tests serialization of an ElementContent object with one sub element.
     * @return void
     */
    public function testElementContentsSerialization()
    {
        $ec = new ElementContents();
        $data = $ec->serialize();
        $this->assertObjectNotHasAttribute('__subs', $data);

        $sub = new ElementContents();
        $sub->{'field'} = 'some sub text';
        $ec->setSub('mysubs', -1, $sub);
        $data = $ec->serialize();
        $this->assertObjectHasAttribute('__subs', $data);
        $this->assertObjectHasAttribute('mysubs', $data->{'__subs'});
        $this->assertCount(1, $data->{'__subs'}->{'mysubs'});
    }

    /**
     * Test a roundtrip on an extended ElementVersion object.
     * @return void
     */
    public function testExtendedElementVersionRoundtrip()
    {
        $data = $this->createExtendedVersion();
        $ev = new ElementVersion();
        $this->roundtrip($ev, $data, $data);
    }

    /**
     * Test a roundtrip on a full element.
     * @return void
     */
    public function testFullElementRoundtrip()
    {
        $permissions = new stdClass();
        $permissions->{'allow'} = new stdClass();
        $permissions->{'allow'}->{'editors'} = ['edit'];
        $permissions->{'allow'}->{'publishers'} = ['edit', 'publish'];

        $fakeId = str_repeat('f', 40);
        $element = new stdClass();
        $element->{'id'} = $fakeId;
        $element->{'type'} = 'page';
        $element->{'permissions'} = $permissions;

        $version = $this->createExtendedVersion();
        $element->{'versions'} = new stdClass();
        $element->{'versions'}->{1} = $version;

        $ev = new Element(BaseStorageAdapter::genRandomId(), $fakeId);
        // poor man's clone
        $expResult =  unserialize(serialize($element));
        // id is implicit, therefore not in the expected result
        unset($expResult->{'id'});
        $this->roundtrip($ev, $element, $expResult);
    }
}
