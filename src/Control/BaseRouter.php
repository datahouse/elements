<?php

namespace Datahouse\Elements\Control;

use RuntimeException;

use Datahouse\Elements\Abstraction\BaseStorageAdapter;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Constants;
use Datahouse\Elements\Control\Admin;
use Datahouse\Elements\Control\Exceptions\BadRequest;
use Datahouse\Elements\Control\Exceptions\Redirection;
use Datahouse\Elements\Control\Exceptions\ResourceNotFound;
use Datahouse\Elements\Control\Exceptions\RouterException;
use Datahouse\Elements\Presentation\Exceptions\ConfigurationError;

/**
 * A trivial router, without expensive regexes or yaml parsing, all
 * configuration in php itself.
 *
 * Applications may provide a router derived from this class to add
 * controllers for custom ajax requests or such.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class BaseRouter
{
    /* @var int $idx position in the parts array */
    private $idx;
    /** @var array $parts of the url */
    private $parts;

    /** @var BaseRequestHandler $handler */
    protected $handler;
    /** @var IStorageAdapter $adapter */
    protected $adapter;
    /** @var HttpRequest $request to route */
    protected $request;
    /** @var IUrlResolver $resolver for ordinary element requests */
    protected $resolver;
    /* @var EleDefRegistry $eleDefRegistry */
    protected $eleDefRegistry;

    /**
     * @param IStorageAdapter $adapter        to lookup data from
     * @param IUrlResolver    $resolver       for ordinary element requests
     * @param EleDefRegistry  $eleDefRegistry to lookup element definitions
     */
    public function __construct(
        IStorageAdapter $adapter,
        IUrlResolver $resolver,
        EleDefRegistry $eleDefRegistry
    ) {
        $this->adapter = $adapter;
        $this->resolver = $resolver;
        $this->eleDefRegistry = $eleDefRegistry;
    }

    /**
     * Advance to the next part of the url. Use @see current() to retrieve
     * the part.
     *
     * @return bool if there is a next part
     */
    final public function advance() : bool
    {
        $this->idx += 1;
        return $this->idx < count($this->parts);
    }

    /**
     * @return string the current part of the url to resolve.
     */
    public function current() : string
    {
        return $this->parts[$this->idx];
    }

    /**
     * Peek at the part following the current one, prior to advance.
     *
     * @return string|null the next part of the url or null
     */
    final public function next()
    {
        return $this->idx + 1 < count($this->parts)
            ? $this->parts[$this->idx + 1]
            : null;
    }

    /**
     * Main router entry point that starts the routing decision. This method
     * is intentionally declared final. Hint: @see route for adding
     * routes to the top level.
     *
     * @param BaseRequestHandler $handler used
     * @param HttpRequest        $request to route
     * @return IController
     * @throws RouterException
     */
    final public function startRouting(
        BaseRequestHandler $handler,
        HttpRequest $request
    ) : IController {
        $this->handler = $handler;
        $this->request = $request;
        assert($this->request->url[0] == '/');
        $this->parts = explode('/', substr($this->request->url, 1));
        $this->idx = 0;
        return $this->route();
    }

    /**
     * Top level entry point for routing, may be overriden by applications.
     *
     * @return IController
     * @throws ConfigurationError
     * @throws RouterException
     */
    public function route() : IController
    {
        $part = $this->current();
        if ($part === 'css') {
            return $this->routeAssetic('css');
        } elseif ($part === 'js') {
            return $this->routeAssetic('js');
        } elseif ($part === 'admin') {
            return $this->routeAdmin();
        } elseif (!$this->adapter->isInitialized()) {
            throw new Redirection(307, 'admin/setup');
        } else {
            $version = $this->adapter->getStorageVersion();
            if ($version != Constants::STORAGE_VERSION) {
                throw new ConfigurationError(
                    "migration needed: expected version "
                    . Constants::STORAGE_VERSION
                    . " but storage is at " . $version
                );
            } elseif ($part === 'snippet') {
                return $this->routeSnippet();
            } elseif ($part === 'blob' || $part === 'blobs') {
                return $this->routeBlobs();
            } elseif ($part === 'image') {
                // Backwards-compatible image link...
                return $this->deprecatedBlobRoute('image');
            } elseif ($part === 'document') {
                // Backwards-compatible document link...
                return $this->deprecatedBlobRoute('document');
            } elseif ($part === 'assets') {
                throw new ConfigurationError("error in apache config");
            } else {
                /** @var Element $page */
                $page = $this->resolveElement($this->request->url);
                if ($page->getType() === 'page') {
                    return new PageController($this, $this->handler, $page);
                } elseif ($page->getType() === 'search') {
                    return new SearchController($this, $this->handler, $page);
                } else {
                    error_log(
                        "requested element " . $page->getId() .
                        " is not a page"
                    );
                    throw new ResourceNotFound();
                }
            }
        }
    }

    /**
     * Route requests for javascripts and stylesheets to assetic.
     *
     * @param string $type of asset to serve
     * @return IController
     */
    protected function routeAssetic($type) : IController
    {
        if (!$this->advance()) {
            throw new ResourceNotFound();
        }
        $collection = $this->current();

        if (!$this->advance()) {
            throw new ResourceNotFound();
        }
        $filename = $this->current();

        return new AsseticResourceController(
            $this,
            $this->handler,
            $type,
            $collection,
            $filename
        );
    }

    /**
     * Route requests for the snippet editor
     *
     * @return IController
     */
    protected function routeSnippet() : IController
    {
        if (!$this->advance()) {
            throw new ResourceNotFound();
        }
        $elementId = $this->current();

        $element = $this->adapter->loadElement($elementId);
        if (is_null($element) || $element->getType() !== 'snippet') {
            throw new ResourceNotFound();
        }

        return new Admin\SnippetController($this, $this->handler, $element);
    }

    /**
     * Route requests for blobs in our BlobStorage.
     *
     * @return IController
     */
    protected function routeBlobs() : IController
    {
        if (!$this->advance()) {
            throw new ResourceNotFound();
        }
        $collection = $this->current();

        if (!$this->advance()) {
            throw new ResourceNotFound();
        }
        $fileId = $this->current();

        $fileMeta = $this->adapter->loadFileMeta($fileId);
        if (is_null($fileMeta) ||
            ($fileMeta->getCollection() != $collection &&
             $collection != 'images' && $fileMeta->getCollection() != 'image')
        ) {
            throw new ResourceNotFound();
        }

        return new StaticBlobController($this, $this->handler, $fileMeta);
    }

    /**
     * @param string $collection to serve
     * @return IController
     */
    protected function deprecatedBlobRoute(string $collection) : IController
    {
        $collection = $collection . 's'; // add plural 's'

        if (!$this->advance()) {
            throw new ResourceNotFound();
        }
        $fileId = $this->current();

        $fileMeta = $this->adapter->loadFileMeta($fileId);
        if (is_null($fileMeta) || $fileMeta->getCollection() != $collection) {
            throw new ResourceNotFound();
        }

        return new StaticBlobController($this, $this->handler, $fileMeta);
    }

    /**
     * Checks if a given absolute path refers to a blob.
     *
     * @param string $path an internal, absolute path
     * @return string|null the file meta id of the referenced blob
     */
    public function getLinkedFileMetaId(string $path)
    {
        assert($path[0] == '/');
        $parts = explode('/', substr($path, 1));
        if (in_array($parts[0], ['blob', 'blobs'])) {
            // parts[1] is the collection name
            return $parts[2];
        } elseif (in_array($parts[0], ['image', 'document'])) {
            return $parts[1];
        } else {
            return null;
        }
    }

    /**
     * @return IController
     */
    protected function routeAdmin() : IController
    {
        if (!$this->advance()) {
            // called plain /admin, redirect to sub-directory
            throw new Redirection(301, '/admin/');
        }

        switch ($this->current()) {
            case '':
            case 'dashboard':
                return new Admin\Dashboard($this, $this->handler);
            case 'blob-upload':
                return $this->routeBlobUpload();
            case 'cache':
                return new Admin\Cache($this, $this->handler);
            case 'element':
                return $this->routeElement();
            case 'login':
                return new Admin\Login($this, $this->handler);
            case 'logout':
                return new Admin\Logout($this, $this->handler);
            case 'report':
            case 'reports':
                return new Admin\Reports($this, $this->handler);
            case 'session':
                return new Admin\Logout($this, $this->handler);
            case 'setup':
                return new Admin\Setup($this, $this->handler);
            case 'tree':
                return $this->routeTree();
            default:
                throw new ResourceNotFound();
        }
    }

    /**
     * @return IController
     */
    protected function routeTree() : IController
    {
        $part = $this->advance() ? $this->current() : '';
        if ($part === '') {
            return new Admin\Tree($this, $this->handler);
        } elseif ($part === 'data') {
            return new Admin\TreeData($this, $this->handler);
        } else {
            throw new ResourceNotFound();
        }
    }

    /**
     * @param string $url to look up
     * @return Element
     * @throws ResourceNotFound
     * @throws Redirection
     */
    public function resolveElement($url)
    {
        list ($elementId, $redirectUrl)
            = $this->resolver->lookupUrl($url);
        if (is_null($elementId)) {
            throw new ResourceNotFound('', $url);
        } elseif (isset($redirectUrl)) {
            throw new Redirection(301, $redirectUrl);
        } else {
            $element = $this->adapter->loadElement($elementId);
            if (is_null($element)) {
                throw new RuntimeException(
                    "url pointer points to inexisting element '"
                    . $elementId . "'"
                );
            }
            return $element;
        }
    }

    /**
     * @return IController
     * @throws RouterException
     */
    protected function routeElement() : IController
    {
        $elementId = $this->advance() ? $this->current() : '';
        if (!BaseStorageAdapter::isValidElementId($elementId)) {
            throw new ResourceNotFound('invaild element id: ' . $elementId);
        }

        $element = $this->adapter->loadElement($elementId);
        if (is_null($element)) {
            throw new ResourceNotFound('no such element');
        }

        $action = $this->advance() ? $this->current() : '';
        if ($action == '') {
            throw new ResourceNotFound('cannot access element directly');
        } elseif ($action == 'new_child') {
            return $this->routeNewElementChild($element);
        } elseif ($action == 'template') {
            return $this->routeSetElementTemplate($element);
        } elseif ($action == 'field' || $action == 'state' ||
                  $action == 'add_sub' || $action == 'remove_sub' ||
                  $action == 'remove' || $action == 'reference' ||
                  $action == 'rename' || $action == 'urls' ||
                  $action == 'move_child'
        ) {
            return $this->routeElementVersion($element, $action);
        } else {
            throw new ResourceNotFound('no such operation on elements');
        }
    }

    /**
     * @param Element $element to manipulate
     * @param string  $action  to perform
     * @return IController
     * @throws RouterException
     */
    protected function routeElementVersion(
        Element $element,
        string $action
    ) : IController {
        if (!$this->advance()) {
            throw new ResourceNotFound('missing version number');
        }

        $vno = $this->current();

        $ev = $element->getVersion($vno);
        if (is_null($ev)) {
            throw new ResourceNotFound('no such version');
        }

        switch ($action) {
            case 'remove_sub':
            case 'add_sub':
                return $this->routeElementSub($element, $ev, $vno, $action);
            case 'reference':
                return $this->routeElementReference($element, $vno);
            case 'field':
                // retrieve or edit a field of an element
                return $this->routeElementField($element, $vno);
            case 'state':
                return $this->routeElementState($element, $vno);
            case 'remove':
                return new Admin\ElementRemove(
                    $this,
                    $this->handler,
                    $element,
                    $vno
                );
            case 'rename':
                return $this->routeElementRename($element, $vno);
            case 'urls':
                return new Admin\ElementUrls(
                    $this,
                    $this->handler,
                    $element,
                    $vno
                );
            case 'move_child':
                return new Admin\ElementMove(
                    $this,
                    $this->handler,
                    $element,
                    $vno
                );
            default:
                throw new ResourceNotFound('operation unknown');
        }
    }

    /**
     * @param Element        $element affected
     * @param ElementVersion $ev      affected
     * @param int            $vno     to fetch or store
     * @param string         $action  'add' or 'remove'
     * @return IController
     * @throws RouterException
     */
    protected function routeElementSub(
        Element $element,
        ElementVersion $ev,
        int $vno,
        string $action
    ) : IController {
        if (!$this->advance()) {
            throw new ResourceNotFound('missing sub name');
        }
        $subName = $this->current();

        if ($action == 'remove_sub') {
            if (!$this->advance()) {
                throw new ResourceNotFound('missing sub index');
            }
            $subIndex = intval($this->current());
        } else {
            $subIndex = null;
        }

        $language = $this->advance() ? $this->current() : '';

        if ($action == 'add_sub') {
            return new Admin\ElementAddSub(
                $this,
                $this->handler,
                $element,
                $vno,
                $language,
                $subName
            );
        } elseif ($action == 'remove_sub') {
            $ec = $ev->getContentsFor($language);
            if (is_null($ec)) {
                throw new ResourceNotFound('language does not exist');
            }
            $subs = $ec->getSubs($subName);
            if ($subIndex < 0 || $subIndex >= count($subs)) {
                throw new ResourceNotFound('sub element does not exist');
            }

            return new Admin\ElementRemoveSub(
                $this,
                $this->handler,
                $element,
                $vno,
                $language,
                $subName,
                $subIndex
            );
        } else {
            assert(false);
        }
    }

    /**
     * @param Element $element affected
     * @param int     $vno     to fetch or store
     * @return IController
     * @throws RouterException
     */
    protected function routeElementReference(
        Element $element,
        int $vno
    ) : IController {
        if (!$this->advance()) {
            throw new ResourceNotFound('missing ref name');
        }
        $refName = $this->current();

        // Check if the given reference exists
        $ev = $element->getVersion($vno);
        $eleDefId = $ev->getDefinition();
        $eleDef = $this->eleDefRegistry->getEleDefById($eleDefId);
        $knownRefs = $eleDef->getKnownReferences();
        if (!array_key_exists($refName, $knownRefs)) {
            throw new ResourceNotFound('no such reference');
        }

        $refDef = $knownRefs[$refName];
        if (!$refDef['selectable']) {
            throw new BadRequest('not a selectable reference');
        }

        if ($this->request->method === 'POST') {
            return new Admin\ElementSetReference(
                $this,
                $this->handler,
                $element,
                $vno,
                $refDef,
                $refName
            );
        } else {
            return new Admin\ElementListReferences(
                $this,
                $this->handler,
                $element,
                $vno,
                $refDef,
                $refName
            );
        }
    }

    /**
     * @return IController
     */
    protected function routeBlobUpload()
    {
        if (!$this->advance()) {
            throw new ResourceNotFound('missing upload type');
        }
        $collection = $this->current();
        return new Admin\FileUpload(
            $this,
            $this->handler,
            $collection
        );
    }

    /**
     * @param Element $element affected
     * @param int     $vno     to fetch or store
     * @return IController
     * @throws RouterException
     */
    protected function routeElementField(
        Element $element,
        int $vno
    ) : IController {
        if (!$this->advance()) {
            throw new ResourceNotFound('missing field name');
        }
        $fieldName = $this->current();

        // Just a quick first check, ElementField will check if the field
        // exists as per the element definition.
        $fieldNameParts = explode('-', $fieldName);
        if (!BaseStorageAdapter::isValidFieldName($fieldNameParts[0])) {
            throw new ResourceNotFound('invalid field name');
        }

        if (!$this->advance()) {
            throw new ResourceNotFound('missing language');
        }
        $language = $this->current();

        if (!BaseStorageAdapter::isValidLanguage($language)) {
            throw new ResourceNotFound('invalid language');
        }

        return new Admin\ElementField(
            $this,
            $this->handler,
            $this->resolver,
            $element,
            $vno,
            $language,
            $fieldName
        );
    }

    /**
     * @param Element $element affected
     * @param int     $vno     to fetch or store
     * @return IController
     * @throws RouterException
     */
    protected function routeElementRename(
        Element $element,
        int $vno
    ) : IController {
        if (!$this->advance()) {
            throw new ResourceNotFound('missing language');
        }
        $language = $this->current();
        if (!BaseStorageAdapter::isValidLanguage($language)) {
            throw new ResourceNotFound('invalid language');
        }

        return new Admin\ElementRename(
            $this,
            $this->handler,
            $element,
            $vno,
            $language
        );
    }

    /**
     * @param Element $element affected
     * @param int     $vno     to fetch or store
     * @return IController
     */
    protected function routeElementState(
        Element $element,
        int $vno
    ) : IController {
        $ev = $element->getVersion($vno);
        if (is_null($ev)) {
            throw new ResourceNotFound('no such version');
        }
        return new Admin\ElementState(
            $this,
            $this->handler,
            $element,
            $vno
        );
    }

    /**
     * routeNewElementChild
     *
     * @param Element $element to add a child to
     * @return IController
     */
    protected function routeNewElementChild(Element $element) : IController
    {
        $language = $this->advance() ? $this->current() : '';
        if (!BaseStorageAdapter::isValidLanguage($language)) {
            throw new ResourceNotFound('invalid language');
        }
        return new Admin\ElementAddChild(
            $this,
            $this->handler,
            $element,
            $language
        );
    }

    /**
     * @param Element $element the element to change
     * @return IController
     */
    protected function routeSetElementTemplate(Element $element) : IController
    {
        $versionNumber = $this->advance() ? $this->current() : '';
        return new Admin\ElementTemplate(
            $this,
            $this->handler,
            $element,
            $versionNumber
        );
    }
}
