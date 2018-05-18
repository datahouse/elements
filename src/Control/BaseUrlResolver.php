<?php

namespace Datahouse\Elements\Control;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\UrlPointer;
use Datahouse\Elements\Control\Authorization\IAuthorizationHandler;
use Datahouse\Elements\Control\ContentSelection\IContentSelector;
use Datahouse\Elements\Control\Exceptions\NoUrlPointer;

/**
 * An example implementation of a simple resolver which looks up elements
 * given a URL.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class BaseUrlResolver implements IUrlResolver
{
    protected $handler;
    /** @var IStorageAdapter $adapter */
    protected $adapter;
    protected $csel;
    protected $auth;

    /**
     * @param BaseRequestHandler    $handler just for the default lang
     * @param IStorageAdapter       $adapter used to lookup data
     * @param IContentSelector      $csel    used for language preferences
     * @param IAuthorizationHandler $auth    used for language checks
     */
    public function __construct(
        BaseRequestHandler $handler,
        IStorageAdapter $adapter,
        IContentSelector $csel,
        IAuthorizationHandler $auth
    ) {
        $this->handler = $handler;
        $this->adapter = $adapter;
        $this->csel = $csel;
        $this->auth = $auth;
    }

    /**
     * Returns just the active default pointers for a given element id.
     *
     * @param string $elementId to retrieve default UrlPointers for.
     * @return array of UrlPointer(s)
     */
    protected function getDefaultPointersFor(string $elementId) : array
    {
        $pointers = array_filter(
            $this->adapter->loadUrlPointersByElement($elementId),
            function (UrlPointer $urlp) {
                return $urlp->isDefault() && !$urlp->isDeprecated();
            }
        );
        if (count($pointers) < 1) {
            throw new NoUrlPointer($elementId . " (no default urls)");
        }
        return $pointers;
    }

    /**
     * @param string $startUrl to look up
     * @return array tuple of elementId (string|null) and a
     *               redirectUrl (again of type string|null)
     */
    public function lookupUrl(string $startUrl) : array
    {
        $url = strtolower($startUrl);

        // Redirect from upper case URLs to lower case ones, modulo URL
        // encoded characters, i.e. '%2f' is considered the same as %2F, as
        // per the fine RFC.
        $needsRedirection = (rawurldecode($url) != rawurldecode($startUrl));

        // strip trailing slashes, but not the leading one
        while (strlen($url) > 1 && $url[strlen($url) - 1] == '/') {
            $url = substr($url, 0, strlen($url) - 1);
            $needsRedirection = true;
        }

        /** @var UrlPointer $urlp */
        $urlp = $this->adapter->loadUrlPointerByUrl($url);
        if (is_null($urlp)) {
            return [null, null];
        } else {
            $element = $this->adapter->loadElement($urlp->getElementId());
            try {
                $redirUrlp = $this->getLinkForElement($element);
            } catch (NoUrlPointer $e) {
                return [null, null];   // really shouldn't happen
            }
            assert($urlp->getElementId() == $redirUrlp->getElementId());
            if ($urlp->getUrl() != $redirUrlp->getUrl()) {
                $needsRedirection = true;
            }
        }

        $config = $this->handler->getReFactory()->getFactory()->getConfiguration();

        return [
            $element->getId(),
            $needsRedirection ? $config->rootUrl . $redirUrlp->getUrl() : null
        ];
    }

    /**
     * Lookup a link for a given element (page).
     *
     * @param Element $element to be linked to
     * @return UrlPointer pointing to the given element
     */
    public function getLinkForElement(Element $element) : UrlPointer
    {
        $langPrefs = $this->csel->getLanguagePreferences();
        if (count($langPrefs) == 0) {
            $langPrefs = ['en' => 1.0];
        }
        $pointers = $this->getDefaultPointersFor($element->getId());

        $authLanguages = $this->auth->getAuthorizedLanguages($element);

        $bestPointer = null;
        $bestScore = 0.0;
        foreach ($langPrefs as $langCountry => $factor) {
            $parts = explode('-', $langCountry);
            $language = $parts[0];
            // Don't offer any pointers prohibited for the given user.
            if (!array_key_exists($language, $authLanguages)) {
                continue;
            }
            /* @var UrlPointer $urlp */
            foreach ($pointers as $urlp) {
                if (in_array($language, $urlp->getLanguages()) &&
                    ($factor >= $bestScore || is_null($bestPointer))
                ) {
                    $bestScore = $factor;
                    $bestPointer = $urlp;
                }
            }
        }

        if (isset($bestPointer)) {
            return $bestPointer;
        } else {
            // fallback to the default language
            $options = $this->handler->getGlobalOptions();
            if (array_key_exists('DEFAULT_LANGUAGE', $options)) {
                $language = $options['DEFAULT_LANGUAGE'];
                foreach ($pointers as $urlp) {
                    if (in_array($language, $urlp->getLanguages())) {
                        return $urlp;
                    }
                }
            }

            // very last resort
            return reset($pointers);
        }
    }
}
