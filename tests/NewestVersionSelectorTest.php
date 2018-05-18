<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementContents;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Authorization\IAuthorizationHandler;
use Datahouse\Elements\Control\ContentSelection\IContentSelector;
use Datahouse\Elements\Control\ContentSelection\NewestVersionSelector;

/**
 * Tests the NewestVersionSelector (exclusively), using a mock auth handler
 * and elements, without any storage connection.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class NewestVersionSelectorTest extends \PHPUnit_Framework_TestCase
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
        $this->csel = new NewestVersionSelector($this->auth);
    }

    /**
     * Setup an example element with just one version and check different
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

        $test_fn = function (array $input, array $exp_output) use ($element) {
            $this->csel->setLanguagePreferences($input, 'en');
            $av = $this->csel->selectBestVersion(
                'view',
                User::getAnonymousUser(),
                $element
            );
            $this->assertEquals($exp_output, $av);
        };

        $test_fn(['en' => '1.0'], [1, 'en']);
        $test_fn(['de' => '1.0'], [1, 'de']);
        $test_fn(['de' => '1.0', 'en' => '0.8'], [1, 'de']);
        $test_fn(['da' => '1.0'], [1, 'en']);
        $test_fn(['da' => '1.0', 'de' => '0.8'], [1, 'de']);
    }
}
