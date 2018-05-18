<?php

namespace Datahouse\Elements\Control\ContentSelection;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Authorization\IAuthorizationHandler;

/**
 * Abstract class BaseContentSelector
 *
 * Basics for content selectors - most of them should probably derive from
 * this class.
 *
 * @package Datahouse\Elements\Control
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
abstract class BaseContentSelector implements IContentSelector
{
    /** @var IAuthorizationHandler $auth the auth handler used underneath */
    protected $auth;

    /** array $accepted_languages from the HTTP header */
    protected $languagePreferences;
    protected $defaultLanguage;

    /**
     * BaseContentSelector constructor.
     *
     * @param IAuthorizationHandler $auth to use
     */
    public function __construct(IAuthorizationHandler $auth)
    {
        $this->auth = $auth;
        $this->languagePreferences = [];
    }

    /**
     * @inheritdoc
     * @param array  $languagePreferences a map of language to score
     * @param string $defaultLanguage     to fall back to if nothing matches
     * @return void
     */
    public function setLanguagePreferences(
        array $languagePreferences,
        string $defaultLanguage
    ) {
        $this->languagePreferences = $languagePreferences;
        $this->defaultLanguage = $defaultLanguage;
    }

    /**
     * Actual work-horse of content selection: the score method.
     *
     * @param int    $vno               version number to assess
     * @param string $lang              language of that version to assess
     * @param array  $newestPerLanguage newest version per language
     * @return float the resulting score
     */
    abstract protected function getScoreForVersion(
        int $vno,
        string $lang,
        array $newestPerLanguage
    ) : float;

    /**
     * @param string  $actionName to apply on the element
     * @param User    $user       to authorize
     * @param Element $element    for which to select an ElementVersion
     * @return array actually a tuple with
     * (int $best_version_number, string $best_language)
     */
    public function selectBestVersion(
        string $actionName,
        User $user,
        Element $element
    ) {
        $av = $this->auth->getAuthorizedVersions($actionName, $user, $element);

        // Collect a list of all available and authorized languages and
        // their respective newest version of this element.
        $newestPerLanguage = [];
        foreach ($av as list($vno, $authorizedLanguages)) {
            foreach ($authorizedLanguages as $lang) {
                if (!array_key_exists($lang, $newestPerLanguage)
                    || $vno > $newestPerLanguage[$lang]
                ) {
                    $newestPerLanguage[$lang] = $vno;
                }
            }
        }

        $bestChoice = [-1, null];
        $bestScore = null;
        foreach ($av as list($vno, $authorizedLanguages)) {
            foreach ($authorizedLanguages as $lang) {
                $score = $this->getScoreForVersion(
                    $vno,
                    $lang,
                    $newestPerLanguage
                );
                if (is_null($bestScore) || $score > $bestScore) {
                    $bestScore = $score;
                    $bestChoice = [$vno, $lang];
                }
            }
        }
        return $bestChoice;
    }

    /**
     * @param string $language to add
     * @param float  $factor   relative quality factor of the language
     * @return void
     */
    public function addLanguagePreference(string $language, float $factor)
    {
        $this->languagePreferences[$language] = $factor;
    }

    /**
     * @return array language preferences
     */
    public function getLanguagePreferences() : array
    {
        return $this->languagePreferences;
    }
}
