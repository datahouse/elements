<?php

namespace Datahouse\Elements\Control\ContentSelection;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\User;

/**
 * Interface IContentSelector
 *
 * Content selectors choose one element version out of the set of possible
 * and allowed versions for a specific action and user. It's using
 * IAuthorizationHandler underneath.
 *
 * @package Datahouse\Elements\Control
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
interface IContentSelector
{
    /**
     * Given a loaded element, this method needs to determine the best version
     * (taking into account the language and permissions for the element,
     * usually with the help of an IAuthHandler).
     *
     * @param string  $actionName (might equal a right)
     * @param User    $user       for which to select content
     * @param Element $element    offering versions and translations
     * @return array with the best best ElementVersion and the best language
     * available for that ElementVersion
     */
    public function selectBestVersion(
        string $actionName,
        User $user,
        Element $element
    );

    /**
     * @return array language preferences as a map of language to factor
     */
    public function getLanguagePreferences() : array;

    /**
     * Sets the language preferences for all further content selections.
     *
     * @param array  $languagePreferences a map of language to factor
     * @param string $defaultLanguage     to fall back to if nothing matches
     * @return void
     */
    public function setLanguagePreferences(
        array $languagePreferences,
        string $defaultLanguage
    );

    /**
     * Add to language preferences.
     *
     * @param string $language to add
     * @param float  $factor   relative quality factor of the language
     * @return void
     */
    public function addLanguagePreference(string $language, float $factor);
}
