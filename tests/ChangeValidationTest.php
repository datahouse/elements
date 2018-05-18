<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\Changes\ElementAddVersion;
use Datahouse\Elements\Abstraction\Changes\ElementContentsChange;
use Datahouse\Elements\Abstraction\Changes\ElementCopyContents;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementContents;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\IStorageAdapter;

/**
 * ChangeValidationTest - exercises change and transaction validation
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class ChangeValidationTest extends \PHPUnit_Framework_TestCase
{
    /* @var IStorageAdapter */
    protected $adapter;

    /**
     * Generate a mock storage adapter - so far only the (untested)
     * ElementSetSlugs change uses it.
     * @return void
     */
    public function setUp()
    {
        $this->adapter = $this->getMockBuilder(IStorageAdapter::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * A single AddVersion change on a new element should be valid.
     *
     * @return void
     */
    public function testAddVersion()
    {
        $ele = new Element();
        $ev = new ElementVersion();
        $contents = new ElementContents();
        $contents->{'title'} = 'old title';
        $ev->addLanguage('de', $contents);
        $ev->setState('published');
        $ele->addVersion(1, $ev);
        $change = new ElementAddVersion($ele, 2, 'editing');
        $result = $change->validate([]);
        $this->assertTrue($result->isSuccess());
    }

    /**
     * A change on a content field that exists must be a valid change.
     *
     * @return void
     */
    public function testChangeExistentContents()
    {
        $ele = new Element();
        $ev = new ElementVersion();
        $contents = new ElementContents();
        $contents->{'title'} = 'old title';
        $ev->addLanguage('fr', $contents);
        $ele->addVersion(7, $ev);
        $change = new ElementContentsChange($ele, 7, 'fr', ['title'], 'non');
        $result = $change->validate([]);
        $this->assertTrue($result->isSuccess());
    }

    /**
     * Try a change on an existing version, but on a language that doesn't
     * exist, yet.
     *
     * @return void
     */
    public function testChangeNonExistentLanguage()
    {
        $ele = new Element();
        $ev = new ElementVersion();
        $contents = new ElementContents();
        $contents->{'title'} = 'old title';
        $ev->addLanguage('fr', $contents);
        $ele->addVersion(7, $ev);
        $change = new ElementContentsChange($ele, 7, 'it', ['title'], 'no');
        $result = $change->validate([]);
        $this->assertFalse($result->isSuccess());
        $errors = $result->getErrorMessages();
        $this->assertEquals(1, count($errors));
        // something's wrong with the language to edit, so that word should be
        // part of the error message.
        $this->assertRegExp('/language/', $errors[0]);
    }

    /**
     * Changing an nonexistent version must be invalid.
     *
     * @return void
     */
    public function testChangeInexistingVersion()
    {
        $ele = new Element();
        $change = new ElementContentsChange($ele, 7, 'fr', ['title'], 'no');
        $result = $change->validate([]);
        $this->assertFalse($result->isSuccess());
        $errors = $result->getErrorMessages();
        $this->assertEquals(1, count($errors));
        $this->assertGreaterThan(0, count($errors));
    }

    /**
     * A change following a newly added translation (on a pre-existing
     * version) must be valid. This is based on testChangeNonExistentLanguage,
     * but with a preceding AddLanguage change.
     *
     * @return void
     */
    public function testChangeNewlyAddedLanguage()
    {
        $ele = new Element();
        $ev = new ElementVersion();
        $contents = new ElementContents();
        $contents->{'title'} = 'old title';
        $ev->addLanguage('fr', $contents);
        $ele->addVersion(7, $ev);
        $change = new ElementContentsChange($ele, 7, 'it', ['title'], 'no');
        $precedingChange= new ElementCopyContents($ele, 7, 'fr', 7, 'it');
        $result = $change->validate([$precedingChange]);
        $this->assertTrue($result->isSuccess());
    }

    /**
     * A change following a newly added version (and language) must be valid.
     *
     * @return void
     */
    public function testChangeNewlyAddedVersion()
    {
        $ele = new Element();
        $change = new ElementContentsChange($ele, 2, 'it', ['title'], 'no');
        $precedingChanges = [
            new ElementAddVersion($ele, 2, 'editing'),
            new ElementCopyContents($ele, 1, 'it', 2, 'it')
        ];
        $result = $change->validate($precedingChanges);
        $this->assertTrue($result->isSuccess());
    }
}
