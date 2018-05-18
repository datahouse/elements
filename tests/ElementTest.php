<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\ElementContents;
use Datahouse\Elements\Abstraction\ElementVersion;

/**
 * Tests on basic element functions
 *
 * @package     Datahouse\Elements\Tests
 * @author      Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class ElementTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test default page title and default menu label for elements.
     *
     * @return void
     */
    public function testElementContentsRoundtrip()
    {
        $ev = new ElementVersion();
        $ec = new ElementContents();
        $ev->setContentsFor($ec, "de");

        // Just a 'name', no specific title or label
        $ec->{'name'} = "test";
        $this->assertEquals("test", $ev->getMenuLabel('de'));
        $this->assertEquals("test", $ev->getPageTitle('de'));

        // Add very sepcific overrides
        $ec->{'menuLabel'} = 'test menu label';
        $ec->{'pageTitle'} = 'header test title';
        $this->assertEquals("test menu label", $ev->getMenuLabel('de'));
        $this->assertEquals("header test title", $ev->getPageTitle('de'));

        // Add generic title
        $ec->{'title'} = "test title";
        $this->assertEquals("test menu label", $ev->getMenuLabel('de'));
        $this->assertEquals("header test title", $ev->getPageTitle('de'));

        unset($ec->{'menuLabel'});
        unset($ec->{'pageTitle'});
        $this->assertEquals("test title", $ev->getMenuLabel('de'));
        $this->assertEquals("test title", $ev->getPageTitle('de'));
    }

    /**
     * Test adding and removing a sub element.
     * @return void
     */
    public function testSubElementAdditionAndRemoval()
    {
        $ec = new ElementContents();
        $sub = new ElementContents();
        $sub->{'field'} = 'some text';

        $ec->setSub('mysub', -1, $sub);
        $this->assertEquals([$sub], $ec->getSubs('mysub'));

        $removedSub = $ec->removeSub('mysub', 0);
        $this->assertNotNull($removedSub);
        $this->assertObjectHasAttribute('field', $removedSub);
        $this->assertEquals('some text', $removedSub->{'field'});

        $this->assertEquals([], $ec->getSubs('mysub'));
    }
}
