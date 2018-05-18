<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\HttpRequestHandler;

/**
 * Testing the HttpRequestHandler.
 *
 * @package Datahouse\Elements\Tests
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class BaseRequestHandlerIsolatedTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return \Closure for simplified testing of input output pairs
     */
    public function getParserTestFn()
    {
        return function ($input, $exp_output) {
            $this->assertEquals(
                $exp_output,
                HttpRequestHandler::parseAcceptedLanguages($input)
            );
        };
    }

    /**
     * Exercise the parser for the HTTP/1.1 Accepted-Languages field.
     * @return void
     */
    public function testParseAcceptedLanguages()
    {
        $test_fn = $this->getParserTestFn();
        $test_fn('en', ['en' => '1.0']);
        $test_fn('en, de;q=0.8', ['en' => '1.0', 'de' => '0.8']);

        // Example from RFC
        $test_fn(
            'da, en-gb;q=0.8, en;q=0.7',
            ['da' => '1.0', 'en-gb' => '0.8', 'en' => '0.7']
        );
    }

    /**
     * Tests the accepted languages parser on some corner cases.
     * @return void
     */
    public function testParseInvalidAcceptedLanguages()
    {
        $test_fn = $this->getParserTestFn();
        $test_fn(null, []);
        $test_fn('', []);
        $test_fn('en,', ['en' => '1.0']);
        $test_fn('en;', ['en' => '1.0']);
        $test_fn('en;q=', ['en' => '1.0']);
    }

    /**
     * Tests mixing in the session language.
     * @return void
     */
    public function testLanguagePreferences()
    {
        $test_fn = function (
            string $inAcceptLang,
            string $inSessionLang,
            array $expOutput
        ) {
            $request = new HttpRequest();
            $request->populateFrom([
                'HTTP_ACCEPT_LANGUAGE' => $inAcceptLang
            ], [], [], []);
            $preferences = HttpRequestHandler::getLanguagePreferences(
                $request,
                $inSessionLang
            );
            $this->assertEquals($expOutput, $preferences);
        };

        $test_fn('en-US,en;q=0.8,de-CH;q=0.6,de;q=0.4', '', [
            'en-US' => '1.0',
            'en' => '0.8',
            'de-CH' => '0.6',
            'de' => '0.4'
        ]);

        // Same with 'en' preset as the session language.
        $test_fn('en-US,en;q=0.8,de-CH;q=0.6,de;q=0.4', 'en', [
            'en' => '2.0',
            'en-US' => '1.0',
            'de-CH' => '0.6',
            'de' => '0.4'
        ]);

        // Again with 'fi' preset as the session language.
        $test_fn('en-US,en;q=0.8,de-CH;q=0.6,de;q=0.4', 'fi', [
            'fi' => '2.0',
            'en-US' => '1.0',
            'en' => '0.8',
            'de-CH' => '0.6',
            'de' => '0.4'
        ]);
    }
}
