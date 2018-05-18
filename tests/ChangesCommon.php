<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\BaseStorageAdapter;
use Datahouse\Elements\Abstraction\Changes\ElementAttachChildElement;
use Datahouse\Elements\Abstraction\Changes\ElementContentsChange;
use Datahouse\Elements\Abstraction\Changes\ElementCopyContents;
use Datahouse\Elements\Abstraction\Changes\AddFileMeta;
use Datahouse\Elements\Abstraction\Changes\ElementCreate;
use Datahouse\Elements\Abstraction\Changes\ElementSetParent;
use Datahouse\Elements\Abstraction\Changes\ElementSetReference;
use Datahouse\Elements\Abstraction\Changes\ElementSetSlugs;
use Datahouse\Elements\Abstraction\Changes\Transaction;
use Datahouse\Elements\Abstraction\FileMeta;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\Slug;

/**
 * Trait ChangesCommon featuring test cases for modification of Elements
 * through changes as defined by IChange objects.
 *
 * @package Datahouse\Elements\Tests
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
trait ChangesCommon
{
    /**
     * test a simple title change
     *
     * @return void
     */
    public function testSimpleTitleChange()
    {
        /** @var IStorageAdapter $adapter */
        $adapter = $this->adapter;

        $elementId = Constants::ELE_ID_HOME;
        $element = $adapter->loadElement($elementId);
        $this->assertNotNull($element);

        $userAlice = $adapter->loadUser('alice');
        $this->assertNotNull($userAlice);

        $change = new ElementContentsChange(
            $element,
            2,
            'en',
            ['title'],
            'New Home'
        );

        $txn = new Transaction([$change]);
        $txn->setAuthor($userAlice);

        // This constitutes a valid transaction, test applying it.
        $this->assertTrue($adapter->validateTransaction($txn)->isSuccess());
        $this->assertTrue($adapter->applyTransaction($txn, null)->isSuccess());

        // Re-load the changed element and check its contents
        $element = $adapter->loadElement(Constants::ELE_ID_HOME);
        $this->assertNotNull($element);
        $contents = $element->getVersion(2)->getContentsFor('en');
        $this->assertEquals('New Home', $contents->{'title'});
    }

    /**
     * tests adding a translation
     *
     * @return void
     */
    public function testAddTranslation()
    {
        /** @var IStorageAdapter $adapter */
        $adapter = $this->adapter;

        $element = $adapter->loadElement(Constants::ELE_ID_HOME);
        $this->assertNotNull($element);

        $userAlice = $adapter->loadUser('alice');
        $this->assertNotNull($userAlice);

        $changes = [
            new ElementCopyContents($element, 2, 'en', 2, 'de'),
            new ElementContentsChange($element, 2, 'de', ['title'], 'Zuhause')
        ];

        $txn = new Transaction($changes);
        $txn->setAuthor($userAlice);

        // This constitutes a valid transaction, test applying it.
        $this->assertTrue($adapter->validateTransaction($txn)->isSuccess());
        $result = $adapter->applyTransaction($txn, null);
        $this->assertTrue($result->isSuccess());
        $xid = $result->getTransactionId();

        // Re-load the changed element and check its contents
        $element = $adapter->loadElement(Constants::ELE_ID_HOME);
        $this->assertNotNull($element);
        $contents = $element->getVersion(2)->getContentsFor('de');
        $this->assertTrue(isset($contents->{'title'}));
        $this->assertEquals('Zuhause', $contents->{'title'});
        // English content should remain unchanged
        $contents = $element->getVersion(2)->getContentsFor('en');
        $this->assertEquals('Home', $contents->{'title'});

        // Undo the transaction and check the element, again.
        $adapter->rollbackTransaction($xid);

        // Re-load the changed element and check its contents
        $element = $adapter->loadElement(Constants::ELE_ID_HOME);
        $this->assertNotNull($element);
        $contents = $element->getVersion(2)->getContentsFor('de');
        $this->assertNull($contents, "undo failed");
        $contents = $element->getVersion(2)->getContentsFor('en');
        $this->assertEquals('Home', $contents->{'title'});
    }

    /**
     * Tests adding a child to an existing element, giving it at least a
     * name in English.
     * @return void
     */
    public function testAddChild()
    {
        /** @var IStorageAdapter $adapter */
        $adapter = $this->adapter;

        $parentElementId = Constants::ELE_ID_HOME;
        $parent = $adapter->loadElement($parentElementId);
        $this->assertNotNull($parent);

        $userAlice = $adapter->loadUser('alice');
        $this->assertNotNull($userAlice);

        $ev = $parent->getVersion(1);
        $eleDefName = $ev->getDefinition();

        $createChildChange = new ElementCreate(
            'en',
            'new page',
            'page',
            $eleDefName
        );
        $child = $createChildChange->getGeneratedElement();
        $childElementId = $child->getId();

        $addChildChange = new ElementAttachChildElement(
            $parent,
            1,
            $child
        );

        $slug = new Slug();
        $slug->url = "my_page";
        $slug->language = 'en';
        $slug->default = true;

        $changes = [
            $createChildChange,
            new ElementSetParent($child, 1, $parent),
            $addChildChange,
            new ElementSetSlugs($child, 1, ['initial' => $slug]),
            new ElementContentsChange($child, 1, 'en', ['name'], 'my page')
        ];

        $txn = new Transaction($changes);
        $txn->setAuthor($userAlice);

        // This constitutes a valid transaction, test applying it.
        $result = $adapter->validateTransaction($txn);
        $this->assertEquals([], $result->getErrorMessages());
        $this->assertTrue($result->isSuccess());
        $result = $adapter->applyTransaction($txn, null);
        $this->assertTrue($result->isSuccess());
        $xid = $result->getTransactionId();

        // Re-load the changed element and check its name
        $child = $adapter->loadElement($childElementId);
        $this->assertNotNull($child);
        $this->assertEquals($parentElementId, $child->getParentId());
        $contents = $child->getVersion(1)->getContentsFor('en');
        $this->assertEquals($contents->{'name'}, 'my page');

        // Re-load the parent element and check its list of children
        $parent = $adapter->loadElement($parentElementId);
        $this->assertNotNull($parent);
        $ev = $parent->getVersion(1);
        $this->assertArrayHasKey(
            $childElementId,
            array_flip($ev->getChildren())
        );
        $ev = $parent->getVersion(2);
        $this->assertArrayHasKey(
            $childElementId,
            array_flip($ev->getChildren())
        );

        // FIXME: also check undoing the transaction
        $this->assertNotNull($xid);
    }

    /**
     * Test replacing a referenced element with @see ElementSetReference.
     *
     * @return void
     */
    public function testReplaceReference()
    {
        /** @var IStorageAdapter $adapter */
        $adapter = $this->adapter;

        $element = $adapter->loadElement(Constants::ELE_ID_HOME);
        $ev = $element->getVersion(2);

        $oldTarget = '54fd1711209fb1c0781092374132c66e79e2241b';
        $this->assertEquals($oldTarget, $ev->getLink('myLink'));

        $this->assertNotNull($element);

        $userAlice = $adapter->loadUser('alice');
        $this->assertNotNull($userAlice);

        $newTarget = 'c66be7210915f39e91456fc2eac9441012a0a3ea';
        $changes = [
            new ElementSetReference($element, 2, 'myLink', $newTarget)
        ];

        $txn = new Transaction($changes);
        $txn->setAuthor($userAlice);

        // This constitutes a valid transaction, test applying it.
        $this->assertTrue($adapter->validateTransaction($txn)->isSuccess());
        $result = $adapter->applyTransaction($txn, null);
        $this->assertTrue($result->isSuccess());
        $xid = $result->getTransactionId();

        // Re-load the changed element and check its contents
        $element = $adapter->loadElement(Constants::ELE_ID_HOME);
        $this->assertNotNull($element);

        $ev = $element->getVersion(2);
        $this->assertEquals($newTarget, $ev->getLink('myLink'));

        // Undo the transaction and check the element, again.
        $adapter->rollbackTransaction($xid);

        $element = $adapter->loadElement(Constants::ELE_ID_HOME);
        $ev = $element->getVersion(2);
        $this->assertEquals($oldTarget, $ev->getLink('myLink'));
    }

    /**
     * Test loading a file metadata object from the storage.
     *
     * @return void
     */
    public function testLoadFileMeta()
    {
        $id = '52538a80094f7b62948fd31e68fd17a315d8dc91';
        $fileMeta = $this->adapter->loadFileMeta($id);

        $this->assertEquals('selfie.jpg', $fileMeta->getOrigFileName());
        $this->assertEquals('.jpg', $fileMeta->getExtension());
        $this->assertEquals('images', $fileMeta->getCollection());
    }

    /**
     * Tests adding a new FileMeta object via the FileMetaAddChange.
     *
     * @return void
     */
    public function testFileUploadChange()
    {
        /* @var IStorageAdapter $adapter */
        $adapter = $this->adapter;

        $userAlice = $adapter->loadUser('alice');
        $this->assertNotNull($userAlice);

        // prepare a file meta data structure to save
        $id = BaseStorageAdapter::genRandomId();
        $fileMeta = new FileMeta($id);
        $fakeHash = BaseStorageAdapter::genRandomId();
        $fileMeta->populate($fakeHash, 'images', 'others.jpg', 'image/jpeg', 83284);

        $change = new AddFileMeta($fileMeta);
        $txn = new Transaction([$change]);
        $txn->setAuthor($userAlice);

        // This constitutes a valid transaction, test applying it.
        $this->assertTrue($adapter->validateTransaction($txn)->isSuccess());
        $this->assertTrue($adapter->applyTransaction($txn, null)->isSuccess());

        // Re-load the added meta data and check its contents
        $fileMeta = $adapter->loadFileMeta($id);
        $this->assertNotNull($fileMeta);
    }

    /**
     * @param string $elementId      to test
     * @param bool   $flushApcuCache control whether or not to flush APCU
     *                               before re-checking the changed element
     * @return string transaction id
     */
    private function applySlugUpdate(string $elementId, bool $flushApcuCache)
    {
        /** @var IStorageAdapter $adapter */
        $adapter = $this->adapter;

        $element = $adapter->loadElement($elementId);
        $this->assertNotNull($element);

        $userAlice = $adapter->loadUser('alice');
        $this->assertNotNull($userAlice);

        $existingSlugs = $element->getVersion(1)->getSlugs();
        $additionalSlug = new Slug();
        $additionalSlug->url = 'less';
        $additionalSlug->language = 'en';
        $additionalSlug->default = true;
        $newSlugs = array_merge($existingSlugs, [$additionalSlug]);

        $change = new ElementSetSlugs($element, 1, $newSlugs);
        $txn = new Transaction([$change]);
        $txn->setAuthor($userAlice);

        // This constitutes a valid transaction, test applying it.
        $validationResult = $adapter->validateTransaction($txn);
        $this->assertEquals([], $validationResult->getErrorMessages());
        $this->assertTrue($validationResult->isSuccess());
        $result = $adapter->applyTransaction($txn, null);
        $this->assertTrue($result->isSuccess());

        // Possibly flush the APCU cache in between before re-loading and
        // checking the element.
        if ($flushApcuCache) {
            apcu_clear_cache();
        }

        // Re-load the changed element and check its contents
        $element = $adapter->loadElement($elementId);
        $this->assertNotNull($element);

        $ev = $element->getVersion(1);
        $this->assertNotNull($ev);

        return $result->getTransactionId();
    }

    /**
     * @param string $xid       transaction to revert
     * @param string $elementId to test
     * @return void
     */
    private function revertSlugUpdate(string $xid, string $elementId)
    {
        /** @var IStorageAdapter $adapter */
        $adapter = $this->adapter;

        // Undo the transaction and check the element, again.
        $adapter->rollbackTransaction($xid);

        // Re-load the changed element and check its contents
        $element = $adapter->loadElement($elementId);
        $this->assertNotNull($element);

        $ev = $element->getVersion(1);
        $this->assertNotNull($ev);
    }

    /**
     * Test a simple and non-duplicate update of the slugs of an element, with
     * incremental updates of the url to element mapping.
     *
     * @return void
     */
    public function testValidSlugsUpdateWithIncrementalUpdate()
    {
        $xid = $this->applySlugUpdate(Constants::ELE_ID_MORE, false);
        $this->revertSlugUpdate($xid, Constants::ELE_ID_MORE);
    }

    /**
     * Test a simple and non-duplicate update of the slugs of an element, with
     * a full cache invalidation before the test and after the slug update
     * transaction.
     *
     * @return void
     */
    public function testValidSlugsUpdateWithCacheInvalidation()
    {
        /** @var IStorageAdapter $adapter */
        $adapter = $this->adapter;

        $adapter->recreateCacheData();
        $xid = $this->applySlugUpdate(Constants::ELE_ID_MORE, true);
        $adapter->recreateCacheData();
        $this->revertSlugUpdate($xid, Constants::ELE_ID_MORE);
    }

    /**
     * Test if removing all slugs (including the default) for language 'en'
     * of the 'subpage' element. Elements should prevent this to avoid an
     * unreachable element.
     *
     * @return void
     */
    public function testTryRemoveRequiredDefaultUrl()
    {
        /** @var IStorageAdapter $adapter */
        $adapter = $this->adapter;

        $element = $adapter->loadElement(Constants::ELE_ID_SUBPAGE);
        $this->assertNotNull($element);

        $userAlice = $adapter->loadUser('alice');
        $this->assertNotNull($userAlice);

        $existingSlugs = $element->getVersion(1)->getSlugs();
        $newSlugs = array_filter(
            $existingSlugs,
            function (Slug $slug) {
                return $slug->language != 'en';
            }
        );

        $change = new ElementSetSlugs($element, 1, $newSlugs);
        $txn = new Transaction([$change]);
        $txn->setAuthor($userAlice);

        $this->assertFalse($adapter->validateTransaction($txn)->isSuccess());
    }
}
