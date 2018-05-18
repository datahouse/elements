<?php

namespace Datahouse\Elements\Control\ContentSelection;

/**
 * Class BestLanguageSelector
 *
 * A somewhat more sophisticated content selector than NewestVersionSelector
 * that prefers better matching content (by language) over the absolute newest
 * version of an element.
 *
 * @package Datahouse\Elements\Control
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class BestLanguageSelector extends BaseContentSelector
{
    /**
     * @param int    $vno               version number to assess
     * @param string $lang              language of that version to assess
     * @param array  $newestPerLanguage newest version per language
     * @return float the resulting score
     */
    protected function getScoreForVersion(
        int $vno,
        string $lang,
        array $newestPerLanguage
    ) : float {
        if (array_key_exists($lang, $this->languagePreferences)) {
            $score = floatval($this->languagePreferences[$lang]);
            assert(array_key_exists($lang, $newestPerLanguage));
            return $vno == $newestPerLanguage[$lang] ? $score : -1.0;
        }

        // If we didn't find any matching language, give the default language
        // a slight benefit to deterministically choose it over all others if
        // none of the given preferences match.
        if (array_key_exists($this->defaultLanguage, $newestPerLanguage) &&
            $lang == $this->defaultLanguage
        ) {
            return $vno == $newestPerLanguage[$lang] ? -0.2 : -1.0;
        } else {
            return $vno == $newestPerLanguage[$lang] ? -0.8 : -1.0;
        }
    }
}
