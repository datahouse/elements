<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\ElementContents;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Control\IChangeProcess;

/**
 * ChangeValidationTest - exercises change and transaction validation
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class ElementVersionCloneTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return void
     */
    public function testCloneExistentVersion()
    {
        $srcEv = new ElementVersion();
        $contents = new ElementContents();
        $contents->{'title'} = 'old title';
        $contents->{'subtitle'} = 'old sub title';

        $srcEv->addLanguage('en', $contents);

        $tgtEv = $srcEv->deepCopyWithoutContents();
        $this->assertNotEquals($srcEv, $tgtEv);
        $this->assertEquals([], $tgtEv->getLanguages());

        // Change that changes on the new contents element have no effect on
        // the old one and vice versa.
        $contents = new ElementContents();
        $contents->{'title'} = 'nouveau titre';
        $srcEv->addLanguage('fr', $contents);

        $contents = new ElementContents();
        $contents->{'title'} = 'neuer Titel';
        $contents->{'subtitle'} = 'ein Untertitel';
        $tgtEv->addLanguage('de', $contents);

        $this->assertEquals(['en', 'fr'], array_keys($srcEv->getLanguages()));
        $this->assertEquals(['de'], array_keys($tgtEv->getLanguages()));
    }
}
