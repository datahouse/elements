<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\BaseStorageAdapter;
use Datahouse\Elements\Abstraction\BlobStorage;
use Datahouse\Elements\Abstraction\Changes\ElementContentsChange;
use Datahouse\Elements\Abstraction\Changes\ElementCopyContents;
use Datahouse\Elements\Abstraction\Changes\ElementStateChange;
use Datahouse\Elements\Abstraction\Changes\IChange;
use Datahouse\Elements\Abstraction\Changes\Transaction;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementContents;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\TransactionResult;
use Datahouse\Elements\Abstraction\User;

/**
 * Test case for the base ChangeProcess base classes, with mocked elements,
 * independent of any storage adapter.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class TransactionValidationTest extends \PHPUnit_Framework_TestCase
{
    /* @var Element $element */
    private $ele;
    /* @var User $user */
    private $user;
    /* @var IStorageAdapter $process */
    private $adapter;

    /**
     * Setup a test user, element and a simple change process.
     *
     * @return void
     */
    public function setUp()
    {
        $this->user = new User('alice');
        $this->ele = $this->prepareTestElement();

        $blobStorage = $this->getMockBuilder(BlobStorage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->adapter = $this->getMockForAbstractClass(
            BaseStorageAdapter::class,
            [$blobStorage]
        );
    }

    /**
     * @param IChange[] $changes to test
     * @return TransactionResult result of the validation
     */
    public function validateTransaction(array $changes) : TransactionResult
    {
        $txn = new Transaction($changes);
        $txn->setAuthor($this->user);
        return $this->adapter->validateTransaction($txn);
    }

    /**
     * Prepare an Element with two versions, one published, one editing.
     *
     * @return Element for tests
     */
    protected function prepareTestElement()
    {
        $element = new Element();
        $version_one = new ElementVersion();
        $version_one->addLanguage('de', new ElementContents([
            'title' => 'Deutscher Test'
        ]));
        $version_one->addLanguage('en', new ElementContents([
            'title' => 'English test'
        ]));
        $version_one->setState('published');
        $element->addVersion(1, $version_one);

        $version_two = new ElementVersion();
        $version_two->addLanguage('de', new ElementContents([
            'title' => 'zweiter deutscher Test'
        ]));
        $version_two->addLanguage('en', new ElementContents([
            'title' => 'second English test'
        ]));
        $version_two->setState('editing');
        $element->addVersion(2, $version_two);

        return $element;
    }

    /**
     * Tests a simple content change followed by a state change to publish
     * the second version. Both changes should be fine according to the
     * TwoStateProcess.
     *
     * @return void
     */
    public function testBasicElementChange()
    {
        $contentChange = new ElementContentsChange(
            $this->ele,
            2,
            'en',
            ['title'],
            'New English Title'
        );
        $result = $this->validateTransaction([$contentChange]);
        $this->assertEquals([], $result->getErrorMessages());
        $this->assertTrue($result->isSuccess());

        $stateChange = new ElementStateChange($this->ele, 2, 'published');
        $result = $this->validateTransaction([$contentChange, $stateChange]);
        $this->assertEquals([], $result->getErrorMessages());
        $this->assertTrue($result->isSuccess());
        $this->assertEmpty($result->getErrorMessages());
    }

    /**
     * Tests validation of copying contents between versions and languages.
     *
     * @return void
     */
    public function testCopyContents()
    {
        // this one is editing, and should be ok
        $valid_change = new ElementCopyContents($this->ele, 1, 'de', 1, 'fr');
        $result = $this->validateTransaction([$valid_change]);
        $this->assertEquals([], $result->getErrorMessages());
        $this->assertTrue($result->isSuccess());

        // this one is editing, and should be ok
        $valid_change = new ElementCopyContents($this->ele, 1, 'de', 2, 'fr');
        $result = $this->validateTransaction([$valid_change]);
        $this->assertEquals([], $result->getErrorMessages());
        $this->assertTrue($result->isSuccess());

        // this one tries to add to a version that doesn't exist
        $invalid_change = new ElementCopyContents($this->ele, 1, 'de', 3, 'fr');
        $result = $this->validateTransaction([$invalid_change]);
        $this->assertNotEmpty($result->getErrorMessages());
        $this->assertFalse($result->isSuccess());

        // this one tries to add to a version that doesn't exist
        $invalid_change = new ElementCopyContents($this->ele, 1, 'de', 3, 'fr');
        $result = $this->validateTransaction([$invalid_change]);
        $this->assertNotEmpty($result->getErrorMessages());
        $this->assertFalse($result->isSuccess());

        // this one tries to copy from a language that doesn't exist
        $invalid_change = new ElementCopyContents($this->ele, 1, 'fr', 2, 'fr');
        $result = $this->validateTransaction([$invalid_change]);
        $this->assertNotEmpty($result->getErrorMessages());
        $this->assertFalse($result->isSuccess());

        // source and target cannot match
        $invalid_copy = new ElementCopyContents($this->ele, 2, 'de', 2, 'de');
        $result = $this->validateTransaction([$invalid_copy]);
        $this->assertNotEmpty($result->getErrorMessages());
        $this->assertFalse($result->isSuccess());
    }
}
