<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\UrlPointer;
use Datahouse\Elements\Control\BaseUrlResolver;

/**
 * Trait UrlResolverCommon featuring test cases for the
 * BaseUrlResolver that are storage adapter agnostic.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
trait UrlResolverCommon
{
    /**
     * Try loading a simple url pointer.
     *
     * @return void
     */
    public function testLoadUrlPointer()
    {
        /** @var UrlPointer $urlp */
        $urlp = $this->adapter->loadUrlPointerByUrl('/subpage');
        $this->assertNotNull($urlp);
        $this->assertTrue($urlp->isDefault());
        $this->assertEquals(Constants::ELE_ID_SUBPAGE, $urlp->getElementId());
    }

    /**
     * Try loading a redirection pointer.
     *
     * @return void
     */
    public function testLoadUrlPointerRedirect()
    {
        /** @var UrlPointer $urlp */
        $urlp = $this->adapter->loadUrlPointerByUrl('/redirect');
        $this->assertNotNull($urlp);
        $this->assertNotTrue($urlp->isDefault());
    }

    /**
     * Try loading a simple url pointer after a full cache invalidation.
     *
     * @return void
     */
    public function testLoadUrlPointerAfterCacheInvalidation()
    {
        $this->adapter->recreateCacheData();

        // Re-run these two tests.
        $this->testLoadUrlPointer();
        $this->testLoadUrlPointerRedirect();
    }

    /**
     * Tests the @see BaseUrlResolver trying to load a simple UrlPointer.
     *
     * @return void
     */
    public function testUrlResolver()
    {
        /// $this->adapter->recreateCacheData();

        /* @var BaseUrlResolver $resolver */
        $resolver = $this->resolver;
        $langPrefs = ['en' => 1.0, 'de' => 0.5];
        $this->csel->method('getLanguagePreferences')->willReturn($langPrefs);
        list ($elementId, $redirectUrl) = $resolver->lookupUrl('/redirect');
        $this->assertEquals(Constants::ELE_ID_SUBPAGE, $elementId);
        $this->assertEquals('/subpage', $redirectUrl);
    }

    /**
     * Test the reverse operation: lookup an URL for a given element and
     * language preference for German.
     *
     * @return void
     */
    public function testDefaultEnglishUrl()
    {
        /* @var IStorageAdapter $adapter */
        $adapter = $this->adapter;
        /* @var BaseUrlResolver $resolver */
        $resolver = $this->resolver;
        $langPrefs = ['en' => 1.0];
        $this->csel->method('getLanguagePreferences')->willReturn($langPrefs);
        $element = $adapter->loadElement(Constants::ELE_ID_SUBPAGE);
        $urlp = $resolver->getLinkForElement($element);
        $this->assertEquals('/subpage', $urlp->getUrl());
    }

    /**
     * Test the reverse operation: lookup an URL for a given element and
     * language preference for German.
     *
     * @return void
     */
    public function testDefaultGermanUrl()
    {
        /* @var IStorageAdapter $adapter */
        $adapter = $this->adapter;
        /* @var BaseUrlResolver $resolver */
        $resolver = $this->resolver;
        $langPrefs = ['de' => 1.0, 'en' => 0.5];
        $this->csel->method('getLanguagePreferences')->willReturn($langPrefs);
        $element = $adapter->loadElement(Constants::ELE_ID_SUBPAGE);
        $link = $resolver->getLinkForElement($element);
        $this->assertEquals('/unterseite', $link->getUrl());
    }

    /**
     * Test url mapping recreation: with German preferred, asking for the
     * element under an English url should yield a redirect.
     *
     * @return void
     */
    public function testLanguageRedirect()
    {
        $this->adapter->recreateCacheData();

        $langPrefs = ['de' => 1.0, 'en' => 0.8];
        $this->csel->method('getLanguagePreferences')->willReturn($langPrefs);

        list ($elementId, $redirectUrl) =
            $this->resolver->lookupUrl('/subpage');
        $this->assertEquals(Constants::ELE_ID_SUBPAGE, $elementId);
        $this->assertEquals('/unterseite', $redirectUrl);
    }

    /**
     * Test url mapping recreation: a German slug shouldn't ever be added
     * below an English parent
     *
     * @return void
     */
    public function testLanguageMismatch()
    {
        $this->adapter->recreateCacheData();

        $langPrefs = ['en' => 1.0];
        $this->csel->method('getLanguagePreferences')->willReturn($langPrefs);

        list ($elementId, $redirectUrl) =
            $this->resolver->lookupUrl('/example/unterseite');
        $this->assertEquals(null, $elementId);
        $this->assertEquals(null, $redirectUrl);
    }
}
