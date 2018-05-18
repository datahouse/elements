<?php

namespace Datahouse\Elements\Control\Filter;

use RuntimeException;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\BaseStorageAdapter;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\Exceptions\NoUrlPointer;
use Datahouse\Elements\Control\IUrlResolver;
use Datahouse\Elements\ReFactory;

/**
 * An internal link filter which modifies internal links to use the element
 * ids, so links survive modifications of urls.
 *
 * @package Datahouse\Elements\Control\Filter
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class InternalLinkFilter extends InternalLinkMangling implements IInputFilter, IOutputFilter
{
    protected $adapter;
    protected $resolver;
    protected $counters;

    /**
     * @param ReFactory    $refactory for the configured root url
     * @param BaseRouter   $router    to use
     * @param IUrlResolver $resolver  for getting links for elements
     */
    public function __construct(
        ReFactory $refactory,
        BaseRouter $router,
        IUrlResolver $resolver
    ) {
        parent::__construct($refactory, $router);
        $this->adapter = $refactory->getFactory()->getStorageAdapter();
        $this->resolver = $resolver;
        $this->counters = [
            'in-transformed' => 0,
            'in-unresolvable' => 0,
            'out-transformed' => 0,
            'out-unresolvable' => 0,
        ];
    }

    /**
     * @param Element $element    affected
     * @param string  $relativeTo reference for relative links
     * @param array   $fieldDef   definition of the field to edit
     * @param string  $value      received from the browser, to be filtered
     * @return string the filtered value to be stored
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('fieldDef')
     */
    public function inFilter(
        Element $element,
        string $relativeTo,
        array $fieldDef,
        string $value
    ) : string {
        // Note that this matches the link tag up until its href attribute,
        // but not any attribute after that, i.e. not the entire tag.
        $pattern = '/<a([^>]*)\s+href\s*\=\s*\"([^\"]+)\"/i';
        if (preg_match_all(
            $pattern,
            $value,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        ) === false) {
            throw new RuntimeException("preg_match failed");
        }

        try {
            $urlp = $this->resolver->getLinkForElement($element);
            $refLink = $urlp->getUrl();
        } catch (NoUrlPointer $e) {
            $refLink = null;
        }

        foreach (array_reverse($matches) as $match) {
            $startIdx = $match[0][1];
            $matchLen = strlen($match[0][0]);
            $leadingAttributes = $match[1][0];
            $fullLink = $match[2][0];

            $link = $this->parseLink($fullLink, $relativeTo);

            // skip all external links and anchor-only links
            if (!$link->isInternal || is_null($link->path)) {
                continue;
            }

            $fileMetaId = $this->router->getLinkedFileMetaId($link->path);
            if ($fileMetaId) {
                $pseudoLink = 'filemeta:' . $fileMetaId;
            } else {
                // Check if this is a link to another element (page).
                list ($linkedElementId, )
                    = $this->resolver->lookupUrl($link->path);
                // In WWP, we currently have additional internal links that
                // are *not* handled by Elements. Make sure we don't scramble
                // those. Refs: #5962.
                if (isset($linkedElementId)) {
                    $pseudoLink = 'element:' . $linkedElementId;
                }
            }

            if (isset($pseudoLink)) {
                $replacement = '<a' . $leadingAttributes
                    . ' href="' . $pseudoLink . $link->anchor
                    . '"';
                $value = substr($value, 0, $startIdx)
                    . $replacement
                    . substr($value, $startIdx + $matchLen);
                $this->counters['in-transformed'] += 1;
            } else {
                error_log(
                    "element " . $element->getId()
                    . (isset($refLink) ? " ($refLink)" : '')
                    . " links to $link->path, which cannot be resolved"
                    . ", skipping"
                );
                $this->counters['in-unresolvable'] += 1;
            }
        }

        return $value;
    }

    /**
     * @param array  $fieldDef definition of the field, including type
     * @param string $value    current value, to be filtered
     * @param string $language to use for displaying
     * @return mixed the filtered value
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('fieldDef')
     */
    public function outFilter(
        array $fieldDef,
        string $value,
        string $language
    ) : string {
        $pattern = '/<a([^>]*)\s+href\s*\=\s*\"(element|filemeta)\:([^#\"]+)((?:#[^\"]*)?)\"/i';
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
            $leadingAttributes = $match[1][0];
            $type = $match[2][0];
            $linkedId = $match[3][0];
            $anchor = $match[4][0];

            if (BaseStorageAdapter::isValidElementId($linkedId) && $type == 'element') {
                try {
                    $element = $this->adapter->loadElement($linkedId);
                    if (is_null($element)) {
                        throw new RuntimeException(
                            "dangling link to $linkedId"
                        );
                    }
                    $urlp = $this->resolver->getLinkForElement($element);
                    $url = $this->config->rootUrl . $urlp->getUrl();
                    $replacement = '<a' . $leadingAttributes
                        . ' href="' . $url . $anchor . '"';
                    $value = substr($value, 0, $startIdx)
                        . $replacement
                        . substr($value, $startIdx + $matchLen);
                } catch (NoUrlPointer $e) {
                    // Linked element not found, don't replace the url.
                    error_log(
                        "FATAL: linked element not found: $linkedId"
                    );
                    $this->counters['out-unresolvable'] += 1;
                }
            } elseif (BaseStorageAdapter::isValidElementId($linkedId) && $type == 'filemeta') {
                $origFileName = $this->adapter->loadFileMeta($linkedId)->getOrigFileName();
                $url = $this->config->rootUrl . '/blobs/document/' . $linkedId . "/$origFileName";
                $replacement = '<a' . $leadingAttributes . ' href="' . $url . $anchor . '"';
                $value = substr($value, 0, $startIdx) . $replacement . substr($value, $startIdx + $matchLen);
            } else {
                // not a valid element id, don't replace the url.
                error_log("FATAL: invalid element id linked: $linkedId");
                $this->counters['out-unresolvable'] += 1;
            }
        }

        return $value;
    }

    /**
     * @return array current value of the counters
     */
    public function getCounters()
    {
        return $this->counters;
    }
}
