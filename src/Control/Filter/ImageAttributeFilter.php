<?php

namespace Datahouse\Elements\Control\Filter;

use RuntimeException;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Constants;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\ReFactory;

/**
 * Filter for image tags, eliminating some froala cruft. (Currently only an
 * out filter, should probably be an input filter, instead).
 *
 * @package Datahouse\Elements\Control\Filter
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ImageAttributeFilter extends InternalLinkMangling implements IInputFilter, IOutputFilter
{
    private $adapter;

    const GOOD_ATTRIBUTES = ['alt', 'class', 'href', 'src', 'style'];
    const STRIPPED_ATTRIBUTES = [
        'data-xid',
        'data-message',
        'data-success',
        'message',
        'success',
        'xid',
    ];

    /**
     * @param ReFactory       $refactory for fetching the configuration
     * @param BaseRouter      $router    to use
     * @param IStorageAdapter $adapter   to use
     */
    public function __construct(
        ReFactory $refactory,
        BaseRouter $router,
        IStorageAdapter $adapter
    ) {
        parent::__construct($refactory, $router);
        $this->adapter = $adapter;
    }

    /**
     * @param Element $element    affected
     * @param string  $relativeTo reference for relative links
     * @param array   $fieldDef   definition of the field to edit
     * @param string  $value      received from the browser, to be filtered
     * @return string the filtered value to be stored
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('element')
     */
    public function inFilter(
        Element $element,
        string $relativeTo,
        array $fieldDef,
        string $value
    ) : string {
        assert(array_key_exists('type', $fieldDef));
        $pattern = '/<img([^\>]+)(?:\/>|>)/i';
        if (preg_match_all(
            $pattern,
            $value,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        ) === false) {
            throw new RuntimeException("preg_match failed");
        }

        foreach (array_reverse($matches) as $match) {
            $startIdx = $match[0][1];
            $matchLen = strlen($match[0][0]);
            $imageTagAttributes = $match[1][0];

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
                if (in_array($attrName, static::GOOD_ATTRIBUTES)) {
                    $attributes[$attrName] = $attrValue;
                } elseif (in_array($attrName, static::STRIPPED_ATTRIBUTES)) {
                    // ignoring these attributes
                } else {
                    error_log("Stripping unknown image attribute: '$attrName'");
                }
            }

            // Modify the source attribute, if any.
            if (array_key_exists('src', $attributes)) {
                $link = $this->parseLink($attributes['src'], $relativeTo);
                if ($link->isInternal && $link->path) {
                    $fileMetaId = $this->router->getLinkedFileMetaId(
                        $link->path
                    );
                    if (!is_null($fileMetaId)) {
                        $attributes['src'] = 'filemeta:' . $fileMetaId;
                    }
                }
            }

            $parts = [];
            foreach ($attributes as $attrName => $attrValue) {
                $parts[] = $attrName . '="' . $attrValue . '"';
            }
            $replacement = '<img ' . implode(' ', $parts) . '/>';
            $value = substr($value, 0, $startIdx)
                . $replacement
                . substr($value, $startIdx + $matchLen);
        }

        // As we're closing all image tags, we simply remove all separate
        // closing tags as well. This means we're also closing unclosed image
        // tags. See unit tests.
        return preg_replace('/\s*<\/img\s*>/', '', $value);
    }

    /**
     * @param array  $fieldDef definition of the field, including type
     * @param string $value    current value, to be filtered
     * @param string $language to use for displaying
     * @return mixed the filtered value
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('language')
     */
    public function outFilter(
        array $fieldDef,
        string $value,
        string $language
    ) : string {
        assert(array_key_exists('type', $fieldDef));
        $pattern = '/<img([^\>]+)(?:\/>|>)/i';
        if (preg_match_all(
            $pattern,
            $value,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        ) === false) {
            throw new RuntimeException("preg_match failed");
        }

        // If there's no source attribute, add one.
        $missingImageUrl = $this->config->rootUrl .
            Constants::MISSING_IMAGE_INDICATOR;

        if ($fieldDef['type'] == 'image' && count($matches) == 0) {
            return '<img src="' . $missingImageUrl . '"/>';
        }

        foreach (array_reverse($matches) as $match) {
            $startIdx = $match[0][1] + 4;   // 4 == strlen('<img')
            $imageTagAttributes = $match[1][0];

            $pattern = '/src=\\"(filemeta:[^\\"]*)\\"/';
            if (preg_match_all(
                $pattern,
                $imageTagAttributes,
                $subMatches,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE
            ) === false) {
                throw new RuntimeException("preg_match failed");
            }

            if (count($subMatches) == 0) {
                $value = substr($value, 0, $startIdx)
                    . ' src="' . $missingImageUrl . '"'
                    . substr($value, $startIdx);
            } else {
                foreach ($subMatches as $subMatch) {
                    $srcAttrPos = $startIdx + $subMatch[0][1];
                    $fullMatch = $subMatch[0][0];
                    $matchLen = strlen($fullMatch);
                    $attrValue = $subMatch[1][0];

                    // Modify the source attribute, if any.
                    $fileMetaId = substr($attrValue, strlen('filemeta:'));
                    $fileMeta = $this->adapter->loadFileMeta($fileMetaId);
                    if ($fileMeta) {
                        $origFileName = $fileMeta->getOrigFileName();
                        $attrValue = $this->config->rootUrl . '/blobs/images/'
                            . $fileMetaId . '/'
                            . $origFileName;
                    } else {
                        error_log(
                            "WARNING: unknown file meta id, not replaced: "
                            . $fileMetaId
                        );
                    }

                    $value = substr($value, 0, $srcAttrPos)
                        . 'src="' . $attrValue . '"'
                        . substr($value, $srcAttrPos + $matchLen);
                }
            }
        }

        return $value;
    }
}
