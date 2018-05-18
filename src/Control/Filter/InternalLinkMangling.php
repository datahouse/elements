<?php

namespace Datahouse\Elements\Control\Filter;

use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\ReFactory;

/**
 * Link rewriting methods usable for both, the ImageAttributeFilter and the
 * InternalLinkFilter
 *
 * @package Datahouse\Elements\Control\Filter
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2017 by Datahouse AG
 */
class InternalLinkMangling
{
    protected $config;
    protected $router;

    /**
     * @param ReFactory  $refactory for the configured root url
     * @param BaseRouter $router    to use
     */
    public function __construct(ReFactory $refactory, BaseRouter $router)
    {
        $this->config = $refactory->getFactory()->getConfiguration();
        $this->router = $router;
    }

    /**
     * @param string $fullLink   link to parse
     * @param string $relativeTo an absolute URL to which relative links refer
     *                           to, requires a trailing slash after the
     *                           domain or port.
     * @return ParsedInternalLink
     */
    public function parseLink(
        string $fullLink,
        string $relativeTo
    ) : ParsedInternalLink {
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
                $lastSlashIdx = strrpos($relativeTo, '/');
                assert($lastSlashIdx !== false);
                $parentPath = substr($relativeTo, 0, $lastSlashIdx);
                $path = $parentPath . '/' . $link;
            }
        }

        // FIXME: eliminate all single- and double-dots as well as double
        // slashes from the path.

        $result = new ParsedInternalLink();
        $result->scheme = $scheme;
        $result->isInternal = $isInternal;
        $result->path = $path;
        $result->anchor = $anchor;
        return $result;
    }
}
