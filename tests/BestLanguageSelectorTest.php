<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementContents;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Authorization\IAuthorizationHandler;
use Datahouse\Elements\Control\ContentSelection\IContentSelector;
use Datahouse\Elements\Control\ContentSelection\BestLanguageSelector;

/**
 * Tests the BestLanguageSelector (exclusively), using a mock auth handler
 * and elements, without any storage connection.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class BestLanguageSelectorTest extends \PHPUnit_Framework_TestCase
{
    /** @var IAuthorizationHandler $auth */
    private $auth;
    /** @var IContentSelector $cset */
    private $csel;

    /**
     * Setup a mock auth handler and content selector
     *
     * @return void
     */
    public function setUp()
    {
        $this->auth = new NullAuthorizationHandler();
        $this->csel = new BestLanguageSelector($this->auth);
    }

    /**
     * Setup an example element with three versions and check different
     * combinations of language preferences.
     *
     * @return void
     */
    public function testLanguagePreference()
    {
        $element = new Element();
        $version_one = new ElementVersion();
        $version_one->addLanguage('de', new ElementContents([
            'title' => 'Deutscher Test'
        ]));
        $version_one->addLanguage('en', new ElementContents([
            'title' => 'English test'
        ]));
        $element->addVersion(1, $version_one);

        $version_two = new ElementVersion();
        $version_two->addLanguage('de', new ElementContents([
            'title' => 'Deutscher Test v2'
        ]));
        $element->addVersion(2, $version_two);

        $version_three = new ElementVersion();
        $version_three->addLanguage('en', new ElementContents([
            'title' => 'English test v3'
        ]));
        $version_three->addLanguage('fr', new ElementContents([
            'title' => 'French test v3'
        ]));
        $element->addVersion(3, $version_three);

        $test_fn = function (array $input, array $exp_output) use ($element) {
            $this->csel->setLanguagePreferences($input, 'en');
            $av = $this->csel->selectBestVersion(
                'view',
                User::getAnonymousUser(),
                $element
            );
            $this->assertEquals($exp_output, $av);
        };

        $test_fn(['en' => '1.0'], [3, 'en']);
        $test_fn(['de' => '1.0'], [2, 'de']);
        $test_fn(['fr' => '1.0'], [3, 'fr']);
        // the user prefers German over English
        $test_fn(['de' => '1.0', 'en' => '0.8'], [2, 'de']);
        // Danish is not available at all, fallback to English
        $test_fn(['da' => '1.0'], [3, 'en']);
        // Danish still not available, but German is.
        $test_fn(['da' => '1.0', 'de' => '0.8'], [2, 'de']);
    }
}
