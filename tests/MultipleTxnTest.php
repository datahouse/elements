<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\Changes\ElementAddVersion;
use Datahouse\Elements\Abstraction\Changes\ElementContentsChange;
use Datahouse\Elements\Abstraction\Changes\ElementCopyContents;
use Datahouse\Elements\Abstraction\Changes\Transaction;
use Datahouse\Elements\Tests\Helpers\ExampleChangeProcess;

/**
 * Trait MultipleTxnTest
 *
 * @package Datahouse\Elements\Tests
 * @author      Helmar Tröller (htr) <helmar.troeller@datahouse.ch>
 * @license (c) 2014 - 2016 by Datahouse AG
 */
trait MultipleTxnTest
{
    /**
     * Initialization common to both tests that follow.
     *
     * @return array of initialized objects
     */
    private function commonPreparation()
    {
        $element_id = '54fd1711209fb1c0781092374132c66e79e2241b';
        $element = $this->adapter->loadElement($element_id);
        $this->assertNotNull($element);

        $user_alice = $this->adapter->loadUser('alice');
        $this->assertNotNull($user_alice);

        // first addVersion should succeed and insert a new version two to
        // the element
        $changes = [
            new ElementAddVersion($element, 2, 'editing'),
            new ElementCopyContents($element, 1, 'en', 2, 'en'),
            new ElementContentsChange($element, 2, 'en', ['title'], 'test a')
        ];

        $txn = new Transaction($changes);
        $txn->setAuthor($user_alice);
        $result = $this->adapter->validateTransaction($txn);
        $this->assertTrue($result->isSuccess(), 'Validating request failed!');
        $result = $this->adapter->applyTransaction($txn);
        $this->assertTrue($result->isSuccess(), 'unable to add new version');
        $this->assertEquals(2, $element->getNewestVersionNumber());

        return [$element, $user_alice];
    }

    /**
     * Two transaction adding a new version on the same base version number.
     * This should fail, since after the first transaction there is new content that might be overwritten otherwise
     *
     * @return void
     */
    public function testAddMultipleVersions()
    {
        list($element, $user_alice) = $this->commonPreparation();

        //second test adding a new version not having version number 2 should fail
        $changes = [
            new ElementAddVersion($element, 2, 'editing'),
            new ElementCopyContents($element, 1, 'en', 2, 'en'),
            new ElementContentsChange($element, 2, 'en', ['title'], 'test b')
        ];

        $txn = new Transaction($changes);
        $txn->setAuthor($user_alice);
        $result = $this->adapter->validateTransaction($txn);
        $this->assertFalse($result->isSuccess(), 'Validating request succeeded but shouldn\'t!');

        // third transaction, bob should not be able to overwrite alices new version
        $user_bob = $this->adapter->loadUser('bob');
        $this->assertNotNull($user_bob);
        $changes = [
            new ElementAddVersion($element, 2, 'editing'),
            new ElementCopyContents($element, 1, 'en', 2, 'en'),
            new ElementContentsChange($element, 3, 'en', ['title'], 'test a')
        ];

        $txn = new Transaction($changes);
        $txn->setAuthor($user_bob);
        $result = $this->adapter->validateTransaction($txn);
        $this->assertFalse($result->isSuccess(), 'Validating request failed!');
    }

    /**
     * This test has multiple transaction:
     * first one adds a new version with content
     * second transaction tries to add a translation to the initially published and fails
     * third transaction adds a new language to the new version
     * fourth transaction add the language from tx 3 again and fails
     *
     * @return void
     */
    public function testAddVersionAddTranslation()
    {
        list($element, $user_alice) = $this->commonPreparation();

        // try adding a translation to the existing published version
        $changes = [
            new ElementCopyContents($element, 1, 'de', 1, 'en'),
            new ElementContentsChange($element, 1, 'de', ['title'], 'test deutsch')
        ];
        $txn = new Transaction($changes);
        $txn->setAuthor($user_alice);
        $result = $this->adapter->validateTransaction($txn);
        // version one is published, so adding a translation should fail
        $this->assertFalse(
            $result->isSuccess(),
            'inserted translation for published version'
        );

        // add a translation to the existing new version
        $changes = [
            new ElementCopyContents($element, 2, 'en', 2, 'de'),
            new ElementContentsChange($element, 2, 'de', ['title'], 'test deutsch')
        ];
        $txn = new Transaction($changes);
        $txn->setAuthor($user_alice);
        $result = $this->adapter->validateTransaction($txn);
        $this->assertTrue($result->isSuccess(), 'Cannot add translation!');
        $result = $this->adapter->applyTransaction($txn);
        $this->assertTrue($result->isSuccess(), 'unable to add new translation');
        $this->assertEquals(2, $element->getNewestVersionNumber());
        $content = $element->getVersion($element->getNewestVersionNumber())
            ->getContentsFor('de');
        $this->assertNotNull($content);
    }
}
