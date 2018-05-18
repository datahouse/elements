<?php

namespace Datahouse\Elements\Control\ContentSelection;

/**
 * Class NewestVersionSelector
 *
 * A simple content selector that always picks the newest authorized version
 * and possibly shows contents for that version in a language other that what
 * the user requested.
 *
 * @package Datahouse\Elements\Control
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class NewestVersionSelector extends BaseContentSelector
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
        $newestVersion = max(array_values($newestPerLanguage));
        $bestScore = 0.0;
        if ($vno == $newestVersion) {
            $found = false;
            if (array_key_exists($lang, $this->languagePreferences)) {
                $score = floatval($this->languagePreferences[$lang]);
                if ($score >= $bestScore) {
                    $bestScore = $score;
                }
            }

            // Give english a slight benefit to deterministically choose it
            // over all others if none of the given preferences match.
            if (!$found && $lang == $this->defaultLanguage) {
                $bestScore = 0.01;
            }
        }
        return $bestScore;
    }
}
