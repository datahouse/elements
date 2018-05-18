<?php

namespace Datahouse\Elements\Tools;

use stdClass;
use RuntimeException;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

use Datahouse\Elements\Abstraction\BaseStorageAdapter;
use Datahouse\Elements\Abstraction\ElementContents;
use Datahouse\Elements\Constants;
use Datahouse\Elements\Configuration;

/**
 * Helper class for automatic migration of YAML storage adapter.
 *
 * Note that this code here needs to be able to deal with old serialized
 * versions and therefore cannot use any of the classes in the (current
 * code's) abstraction layer.
 *
 * @package Datahouse\Elements\Tools
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class YamlStorageMigrator
{
    /* @var Configuration $config */
    private $config;
    /* @var string $dir */
    private $dir;
    /* @var string $blobDir */
    private $blobDir;
    /* @var callable $getVersion */
    private $getVersion;

    /**
     * @param Configuration $config     of the app
     * @param string        $dir        base directory for YAML files
     * @param string        $blobDir    base directory of the blob storage
     * @param callable      $getVersion function to retrieve the current version
     */
    public function __construct(
        Configuration $config,
        string $dir,
        string $blobDir,
        callable $getVersion
    ) {
        $this->config = $config;
        $this->dir = $dir;
        $this->blobDir = $blobDir;
        $this->getVersion = $getVersion;
    }

    /**
     * @param int $version to set after a migration
     * @return void
     */
    private function setStorageVersion(int $version)
    {
        $path = $this->dir . '/version';
        file_put_contents($path, $version . "\n");
    }

    /**
     * Actual work horse for the 4 => 5 migration.
     *
     * @param string   $elementId being modified
     * @param stdClass $ev        element version
     * @return void
     */
    private function migratePageVersionSlugs(string $elementId, stdClass &$ev)
    {
        $defaultsSeen = [];
        $firstAliasPerLanguage = [];
        foreach ($ev->{'slugs'} as $slug) {
            // Ensure every slug has a language. Default to 'de'.
            if (!property_exists($slug, 'language')) {
                error_log(
                    "$elementId: adding language 'de' to url '$slug->url'"
                );
                $slug->{'language'} = 'de';
            }

            // Unescape all slashes.
            $slug->{'url'} = str_ireplace(
                "%2f",
                "/",
                $slug->{'url'}
            );

            // Drop the 'redirect' attribute, implicit now for non-defaults
            unset($slug->{'redirect'});

            // Don't use deprecated slugs for defaults or first aliases.
            if ($slug->{'deprecated'} ?? false) {
                continue;
            }

            // Ensure there's at most one default per language. Prefer the
            // first in the list.
            if ($slug->{'default'} ?? false) {
                if (array_key_exists($slug->{'language'}, $defaultsSeen)) {
                    error_log(
                        "$elementId: dropping default for url '$slug->url'"
                    );
                    unset($slug->{'default'});
                } else {
                    $defaultsSeen[$slug->{'language'}] = $slug;
                }
            } else {
                // Collect (non-deprecated) aliases, might be useful for later
                // propagation.
                $language = $slug->{'language'};
                if (!array_key_exists($language, $firstAliasPerLanguage)) {
                    $firstAliasPerLanguage[$language] = $slug;
                }
            }
        }

        // Ensure there's at least one default per language. Promote the first
        // alias in the list, if necessary.
        //
        // Note that the set of supported languages is hard-coded to WWP.
        foreach (['de', 'en', 'it', 'fr'] as $language) {
            if (!array_key_exists($language, $defaultsSeen)) {
                if (array_key_exists($language, $firstAliasPerLanguage)) {
                    $slug = $firstAliasPerLanguage[$language];
                    error_log(
                        "$elementId: promote first slug '$slug->url' " .
                        "to default for language $language"
                    );
                    $slug->{'default'} = true;
                    $defaultsSeen[$language] = $slug;
                } else {
                    if (count($defaultsSeen) > 0) {
                        // Copy a default from another language.
                        if (array_key_exists('de', $defaultsSeen)) {
                            $otherSlug = $defaultsSeen['de'];
                        } else {
                            $otherSlug = reset($defaultsSeen);
                        }
                    } else {
                        // Copy some other alias.
                        if (array_key_exists('de', $firstAliasPerLanguage)) {
                            $otherSlug = $firstAliasPerLanguage['de'];
                        } else {
                            $otherSlug = reset($firstAliasPerLanguage);
                        }
                    }

                    error_log(
                        "$elementId: use '$otherSlug->url' from " .
                        "$otherSlug->language as well for $language."
                    );

                    $slug = new stdClass();
                    $slug->{'url'} = $otherSlug->{'url'};
                    $slug->{'language'} = $language;
                    $slug->default = true;

                    assert(is_array($ev->{'slugs'}));
                    $ev->{'slugs'}[] = $slug;
                }
            }
        }
    }

    /**
     * Scans through the yaml storage filesystem to find all current and attic
     * element files.
     * @return array list of absolute paths
     */
    private function enumCurrentAndAtticElementsFiles() : array
    {
        $elementsDir = $this->dir . '/elements';
        $currentFiles = array_map(
            function ($filename) use ($elementsDir) {
                return $elementsDir . '/' . $filename;
            },
            array_filter(
                scandir($elementsDir),
                function ($name) {
                    return BaseStorageAdapter::isValidElementId($name);
                }
            )
        );

        $atticDir = $this->dir . '/attic/elements/';
        $atticFiles = array_map(
            function ($filename) use ($atticDir) {
                return $atticDir . '/' . $filename;
            },
            array_filter(
                scandir($atticDir),
                function ($name) {
                    $parts = explode('-', $name, 2);
                    return BaseStorageAdapter::isValidElementId($parts[0]);
                }
            )
        );

        return $currentFiles + $atticFiles;
    }

    /**
     * A loop over all elements, even in the attic, deserialising them from
     * YAML, calling a visitor for modifications and reserializing them to
     * the storage.
     *
     * @param callable $visitor with arguments elementId and the deserialized
     *                          YAML object as a reference.
     * @return void
     */
    private function visitAllElements(callable $visitor)
    {
        foreach ($this->enumCurrentAndAtticElementsFiles() as $path) {
            $parts = explode('-', basename($path), 2);
            $elementId = $parts[0];
            assert(BaseStorageAdapter::isValidElementId($elementId));
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException("uanble to read '$path'");
            } else {
                // Parse from disk.
                $yaml = new Parser();
                $obj = $yaml->parse($contents, Yaml::PARSE_OBJECT_FOR_MAP);
                if (is_null($obj)) {
                    throw new RuntimeException("failed to parse $elementId");
                }

                // Validate.
                assert(property_exists($obj, 'type'));
                assert(property_exists($obj, 'versions'));

                // Modify.
                $visitor($elementId, $obj);

                // Write the object back to disk.
                $writer = new CustomYamlFileDumper($path, 2);
                $flags = Yaml::DUMP_OBJECT_AS_MAP
                    | Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE
                    | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK;
                $writer->dump($obj, 999, 0, $flags);
            }
        }

        // Clear APCU cache, as we worked with the YAML files directly,
        // circumventing the storage adapter.
        apcu_clear_cache();
    }

    /**
     * Similar to the above method, but visits the deeper ElementContents.
     *
     * @param callable $visitor with arguments elementId, deserialized object,
     *                          version number, language and content
     * @return void
     */
    private function visitAllElementContents(callable $visitor)
    {
        $this->visitAllElements(function ($elementId, $obj) use ($visitor) {
            $versions = array_keys(get_object_vars($obj->{'versions'}));
            foreach ($versions as $vno) {
                $ev = $obj->{'versions'}->{$vno};
                $languages = array_keys(get_object_vars($ev->{'languages'}));
                foreach ($languages as $language) {
                    $ec = $ev->{'languages'}->{$language};
                    $visitor($elementId, $obj, $vno, $language, $ec);
                }
            }
        });
    }

    /**
     * @param string          $elementId  containing the contents to modify
     * @param int             $vno        containing the contents to modify
     * @param string          $language   of the contents to modify
     * @param string|null     $curSubName sub-element id, if any
     * @param ElementContents $ec         to modify
     * @param callable        $visitor    to invoke
     * @return void
     */
    private function callVisitorForElementContents(
        $elementId,
        $vno,
        $language,
        $curSubName,
        &$ec,
        callable $visitor
    ) {
        $directFields = array_keys(get_object_vars($ec));
        foreach ($directFields as $fieldName) {
            // Ignore sub elements, handled below. Ignore slugs as well,
            // these shouldn't be part of current (v5/6) elements.
            if ($fieldName == '__subs' || $fieldName == 'slugs') {
                continue;
            }
            assert(
                is_string($ec->{$fieldName})
                || is_integer($ec->{$fieldName})
            );
            $visitor(
                $elementId,
                $vno,
                $language,
                $curSubName,
                $fieldName,
                $ec->{$fieldName}
            );
        }

        if (property_exists($ec, '__subs')) {
            $subNames = array_keys(get_object_vars($ec->{'__subs'}));
            foreach ($subNames as $subName) {
                $subs = $ec->{'__subs'}->{$subName};
                foreach ($subs as $subIdx => &$sub) {
                    $this->callVisitorForElementContents(
                        $elementId,
                        $vno,
                        $language,
                        $curSubName . '/' . $subIdx . '/' . $subName,
                        $sub,
                        $visitor
                    );
                }
            }
        }
    }

    /**
     * An even deeper variant that visits fields, including those of sub
     * elements.
     *
     * @param callable $visitor too many arguments to list here, sorry.
     * @return void
     */
    private function visitAllElementFields(callable $visitor)
    {
        $this->visitAllElementContents(function (
            $elementId,
            $obj,
            $vno,
            $language,
            &$ec
        ) use (
            $visitor
        ) {
            $this->callVisitorForElementContents(
                $elementId,
                $vno,
                $language,
                null,
                $ec,
                $visitor
            );
        });
    }

    /**
     * @param string   $elementId being adjusted
     * @param stdClass $obj       actual contents to modify
     * @return void
     */
    private function adjustSlugsV5(string $elementId, stdClass &$obj)
    {
        foreach ($obj->{'versions'} as $ev) {
            $hasSlugs = count($ev->{'slugs'} ?? []) > 0;
            if ($elementId == Constants::ROOT_ELEMENT_ID) {
                if ($hasSlugs) {
                    error_log(
                        "$elementId: deleting slugs for root"
                    );
                    unset($ev->{'slugs'});
                }
            } elseif ($obj->{'type'} === 'page' ||
                $obj->{'type'} === 'search'
            ) {
                $this->migratePageVersionSlugs($elementId, $ev);
            } else {
                if ($hasSlugs) {
                    error_log(
                        "$elementId: type "
                        . $obj->{'type'} . ", deleting slugs"
                    );
                    unset($ev->{'slugs'});
                }
            }
        }
    }

    /**
     * Another loop over all elements, enforcing some new restrictions on
     * slugs: at least one default slug per language, all slugs need to have
     * a language, at most one default slug per language.
     *
     * @return void
     */
    private function migrateFromVersionFour()
    {
        $this->visitAllElements(function ($elementId, $obj) {
            $this->adjustSlugsV5($elementId, $obj);
        });

        // Also drop an existing url_element_mapping, if any.
        if (file_exists($this->dir . '/meta/url_element_mapping')) {
            unlink($this->dir . '/meta/url_element_mapping');
        }

        $this->setStorageVersion(5);
    }

    /**
     * @param string $fullLink link to parse
     * @return array
     */
    public function parseLink(string $fullLink) : array
    {
        // Filter away the anchor tag, if any.
        $poundIdx = strpos($fullLink, '#');
        if ($poundIdx === false) {
            $link = $fullLink;
            $anchor = '';
        } else {
            $link = substr($fullLink, 0, $poundIdx);
            $anchor = substr($fullLink, $poundIdx);
        }

        // Check for an URL scheme identifier.
        $colonIdx = strpos($link, ':');
        $scheme = $colonIdx === false ? null
            : substr($link, 0, $colonIdx);

        if (isset($scheme)) {
            $rootUrl = $this->config->rootUrl;
            $rootUrlLen = strlen($this->config->rootUrl);
            if (!in_array($scheme, ['http', 'https'])) {
                // Don't ever attempt to convert mailto links or re-rewrite
                // existing element links.
                $isInternal = false;
                $path = null;
            } else {
                $isInternal = substr($link, 0, $rootUrlLen) == $rootUrl;
                $path = substr($link, $rootUrlLen);
            }
        } else {
            $isInternal = true;
            if ($link === '') {
                // looks like an anchor-only link, e.g. '#someplace'
                $path = null;
            } elseif ($link[0] === '/') {
                // an absolute link
                $path = $link;
            } else {
                // a relative link
                $isInternal = false;
                $path = '...';
                /*
                $lastSlashIdx = strrpos($relativeTo, '/');
                assert($lastSlashIdx !== false);
                $parentPath = substr($relativeTo, 0, $lastSlashIdx);
                $path = $parentPath . '/' . $link;
                */
            }
        }

        // FIXME: eliminate all single- and double-dots as well as double
        // slashes from the path.
        return [
            'scheme' => $scheme,
            'isInternal' => $isInternal,
            'path' => $path,
            'anchor' => $anchor
        ];
    }

    /**
     * Rewrite blob links to the new internal representation.
     *
     * @return void
     */
    private function migrateFromVersionFive()
    {
        $this->visitAllElementFields(function (
            $elementId,
            $vno,
            $language,
            $subName,
            $fieldName,
            &$fieldValue
        ) {
            $pattern = '/<(a|img)([^\>\<]+)/i';
            if (preg_match_all(
                $pattern,
                $fieldValue,
                $matches,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE
            ) === false) {
                throw new RuntimeException("preg_match failed");
            }

            foreach (array_reverse($matches) as $match) {
                $startIdx = $match[0][1];
                $matchLen = strlen($match[0][0]);
                $tag = $match[1][0];
                $imageTagAttributes = $match[2][0];

                $attributes = [];
                $pattern = '/([-\\w]+)=\\"([^\\"]*)\\"/';
                if (preg_match_all(
                    $pattern,
                    $imageTagAttributes,
                    $matches,
                    PREG_SET_ORDER
                ) === false) {
                    throw new RuntimeException("preg_match failed");
                }

                // Collect all attributes of the tag.
                foreach ($matches as list(, $attrName, $attrValue)) {
                    $attributes[$attrName] = $attrValue;
                }

                $linkTagName = $tag == 'img' ? 'src' : 'href';

                // Modify the source attribute, if any.
                if (array_key_exists($linkTagName, $attributes)) {
                    $link = $this->parseLink($attributes[$linkTagName]);
                    if ($link['isInternal'] && $link['path']) {
                        $parts = explode('/', substr($link['path'], 1));
                        if (in_array($parts[0], ['blob', 'blobs'])) {
                            $fileMetaId = $parts[2];
                        } elseif (in_array($parts[0], ['image', 'document'])) {
                            $fileMetaId = $parts[1];
                        } else {
                            continue;
                        }

                        $attributes[$linkTagName] = 'filemeta:' . $fileMetaId;
                    }
                }

                $parts = [];
                foreach ($attributes as $attrName => $attrValue) {
                    $parts[] = $attrName . '="' . $attrValue . '"';
                }
                $replacement = '<' . $tag . ' ' . implode(' ', $parts);
                $fieldValue = substr($fieldValue, 0, $startIdx)
                    . $replacement
                    . substr($fieldValue, $startIdx + $matchLen);
            }
        });

        $this->setStorageVersion(6);
    }

    private function migrateFromVersionSix()
    {
        $this->visitAllElements(function ($elementId, $obj) {
            if (
                $obj->{'type'} != 'snippet'
                && $obj->{'type'} != 'root'
                && $obj->{'type'} != 'collection'
            ) {
                $versions = array_keys(get_object_vars($obj->{'versions'}));

                // Determine available languages and default slugs.
                $availableLanguages = [];
                $defaultSlugs = [];
                foreach ($versions as $vno) {
                    $ev = $obj->{'versions'}->{$vno};
                    foreach ($ev->{'languages'} as $lang => $_) {
                        $availableLanguages[$lang] = true;
                    }

                    foreach ($ev->{'slugs'} as $slug) {
                        if (
                            property_exists($slug, 'default')
                            && $slug->{'default'} == true
                            && property_exists($slug, 'url')
                            && (!property_exists($slug, 'deprecated')
                                || $slug->{'deprecated'} != true)
                        ) {
                            // Attic versions may contain slugs without a
                            // language. Use those defaults as a very last
                            // resort.
                            $slugLang = property_exists($slug, 'language')
                                ? $slug->{'language'} : 'all';
                            $defaultSlugs[$slugLang] = $slug->{'url'};
                        }
                    }
                }

                // Prefer German defaults, then English ones...
                if (array_key_exists('de', $defaultSlugs)) {
                    $defaultSlugUrl = $defaultSlugs['de'];
                } elseif (array_key_exists('en', $defaultSlugs)) {
                    $defaultSlugUrl = $defaultSlugs['en'];
                } elseif ($defaultSlugs) {
                    // Just take some existing slug.
                    $defaultSlugUrl = array_values($defaultSlugs)[0];
                } else {
                    // We need *some* default.
                    $defaultSlugUrl = "/__migration_step_default/" . $elementId;
                }

                // Ensure there's a default slug for each language available.
                foreach ($versions as $vno) {
                    $ev = $obj->{'versions'}->{$vno};

                    foreach ($availableLanguages as $reqLang => $_) {
                        $found = false;
                        foreach ($ev->{'slugs'} as $slug) {
                            if (
                                property_exists($slug, 'language')
                                && $slug->{'language'} == $reqLang
                                && property_exists($slug, 'default')
                                && $slug->{'default'} == true
                                && (!property_exists($slug, 'deprecated')
                                    || $slug->{'deprecated'} != true)
                            ) {
                                $found = true;
                            }
                        }

                        // Add a default slug for $reqLang if not found.
                        if (!$found) {
                            $defaultSlug = (object)[
                                'language' => $reqLang,
                                'default' => true,
                                'url' => $defaultSlugUrl
                            ];
                            array_push($ev->{'slugs'}, $defaultSlug);

                            error_log(
                                "$elementId:$vno: add default slug for "
                                . "language '$reqLang'"
                            );
                        }
                    }
                }
            }
        });

        $this->setStorageVersion(7);
    }

    /**
     * Performs the actual migration.
     * @return void
     */
    public function migrate()
    {
        // Grant this migration enough headroom WRT execution time.
        ini_set('max_execution_time', 7200);

        $currentVersion = ($this->getVersion)();
        while ($currentVersion != Constants::STORAGE_VERSION) {
            // Just to be extra sure, clear the APCU cache *before* every
            // migration step.
            apcu_clear_cache();

            switch ($currentVersion) {
                case 1:
                    throw new RuntimeException(
                        "cannot migrate from version 1 anymore"
                    );
                case 2:
                case 3:
                    throw new RuntimeException(
                        "cannot migrate from versions before 4 anymore, "
                        . "please use Elements v0.13.x"
                    );
                case 4:
                    $this->migrateFromVersionFour();
                    break;
                case 5:
                    $this->migrateFromVersionFive();
                    break;
                case 6:
                    $this->migrateFromVersionSix();
                    break;
                default:
                    throw new RuntimeException("unable to migrate");
            }

            // Cross check if the migration step updated the version so as to
            // ensure we're not entering an endless loop.
            $newVersion = ($this->getVersion)();
            if ($currentVersion == $newVersion) {
                throw new RuntimeException(
                    "migration step forgot to update version"
                );
            }
            $currentVersion = $newVersion;
        }
    }
}
