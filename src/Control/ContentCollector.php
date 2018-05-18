<?php

namespace Datahouse\Elements\Control;

use RuntimeException;

use Datahouse\Elements\Abstraction\BaseStorageAdapter;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementContents;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Configuration;
use Datahouse\Elements\Constants;
use Datahouse\Elements\Control\ContentSelection\IContentSelector;
use Datahouse\Elements\Control\Exceptions\NoUrlPointer;
use Datahouse\Elements\Control\Filter\ImageAttributeFilter;
use Datahouse\Elements\Control\Filter\InternalLinkFilter;
use Datahouse\Elements\Control\Filter\IOutputFilter;
use Datahouse\Elements\Control\TextSearch\ElasticsearchInterface;
use Datahouse\Elements\Presentation\BasePageDefinition;
use Datahouse\Elements\Presentation\Exceptions\ConfigurationError;
use Datahouse\Elements\Presentation\IElementDefinition;
use Datahouse\Elements\ReFactory;

/**
 * A helper class in between the storage adapter and the request handler,
 * encapsulating logic collecting data of elements from the storage adapter.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ContentCollector
{
    protected $refactory;
    protected $adapter;
    protected $process;
    protected $csel;
    protected $resolver;
    protected $eleDefRegistry;

    /**
     * ContentCollector constructor.
     *
     * @param ReFactory        $refactory      for dynamic object creation
     * @param IStorageAdapter  $adapter        storage adapter to use
     * @param IChangeProcess   $process        change process to apply
     * @param IContentSelector $csel           underlying content selector
     * @param IUrlResolver     $resolver       url resolver to apply
     * @param EleDefRegistry   $eleDefRegistry element definition registry
     */
    public function __construct(
        ReFactory $refactory,
        IStorageAdapter $adapter,
        IChangeProcess $process,
        IContentSelector $csel,
        IUrlResolver $resolver,
        EleDefRegistry $eleDefRegistry
    ) {
        $this->refactory = $refactory;
        $this->adapter = $adapter;
        $this->process = $process;
        $this->csel = $csel;
        $this->resolver = $resolver;
        $this->eleDefRegistry = $eleDefRegistry;
    }

    /**
     * @return IContentSelector used by this collector
     */
    public function getContentSelector() : IContentSelector
    {
        return $this->csel;
    }

    /**
     * @return IUrlResolver
     */
    public function getUrlResolver() : IUrlResolver
    {
        return $this->resolver;
    }

    /**
     * FIXME: callers should use the (Re)Factory directly.
     * @deprecated
     *
     * @return Configuration elements configuration
     */
    public function getConfiguration() : Configuration
    {
        return $this->refactory->getFactory()->getConfiguration();
    }

    /**
     * @param Element $element the element
     * @param User    $user    user requesting on the element
     *
     * @return array
     */
    public function loadBestVersion(Element $element, User $user)
    {
        $type = $element->getType();
        assert(array_key_exists($type, Constants::VALID_ELEMENT_TYPES));
        list($vno, $pageLang) = $this->csel->selectBestVersion(
            'view',
            $user,
            $element
        );
        if ($vno > 0 && !empty($pageLang)) {
            $pageVersion = $element->getVersion($vno);
            return [$vno, $pageLang, $pageVersion];
        } else {
            return [$vno, $pageLang, null];
        }
    }

    /**
     * @param string  $actionName to perform
     * @param Element $element    the element
     * @param User    $user       user requesting on the element
     * @return array
     */
    public function loadBestVersionEx(
        string $actionName,
        Element $element,
        User $user
    ) {
        // Try the requested action, first.
        list($vno, $lang) = $this->csel->selectBestVersion(
            $actionName,
            $user,
            $element
        );
        $viewOnly = true;
        if ($vno > 0 && strlen($lang) > 0) {
            $viewOnly = false;
        } elseif ($actionName != 'view') {
            // Try the view-only permission.
            list ($vno, $lang) = $this->csel->selectBestVersion(
                'view',
                $user,
                $element
            );
        }
        $ev = $vno > 0 ? $element->getVersion($vno) : null;
        return [$vno, $lang, $ev, $viewOnly];
    }

    /**
     * @param ElementContents $ec        element contents to filter
     * @param string          $fieldName affected field
     * @param array           $fieldDef  definition of the field
     * @return array
     */
    private function filterImageField(
        ElementContents $ec,
        $fieldName,
        $fieldDef
    ) : array {
        $rootUrl = $this->refactory->getFactory()->getConfiguration()->rootUrl;
        $attributes = [
            'src' => $rootUrl . Constants::MISSING_IMAGE_INDICATOR
        ];  // default source attribute
        if (false !== preg_match_all(
            '/(\\w+)=\\"([^\\"]+)\\"/',
            $ec->{$fieldName} ?? $fieldDef['default'] ?? '',
            $matches,
            PREG_SET_ORDER
        )) {
            $goodAttributes = [
                'alt',
                'class',
                'href',
                'src',
                'style'
            ];
            $ignoredAttributes = [
                'xid',      // no idea why Froala saves these three
                'message',
                'success'
            ];
            foreach ($matches as list(, $attrName, $attrValue)) {
                if (in_array($attrName, $goodAttributes)) {
                    $attributes[$attrName] = $attrValue;
                } elseif (in_array($attrName, $ignoredAttributes)) {
                    // ignoring these attributes
                } else {
                    error_log("Unknown image attribute: '$attrName'");
                }
            }
        }
        return $attributes;
    }

    /**
     * Filter fields based on the element definition. Ignores fields present
     * in $ec but unknown to the definition. Turns the given ElementContents
     * instance into a plain php array that Twig can handle.
     *
     * @param IElementDefinition $eleDef   to check against
     * @param ElementContents    $ec       providing field values
     * @param string             $language used for displaying
     * @return array map of field names to field values
     * @throws ConfigurationError
     */
    private function filterFieldsByDefinition(
        IElementDefinition $eleDef,
        ElementContents $ec,
        string $language
    ) {
        $fields = [];
        $fieldInfo = [];
        foreach ($eleDef->getKnownContentFields() as $fieldName => $def) {
            $factory = $this->refactory->getFactory();
            // FIXME: make these configurable
            $filters = [
                // FIXME: why doesn't this work?
                //$factory->createClass(InternalLinkFilter::class),
                //$factory->createClass(ImageAttributeFilter::class)
                new InternalLinkFilter(
                    $this->refactory,
                    $factory->getRouter(),
                    $factory->getUrlResolver()
                ),
                new ImageAttributeFilter(
                    $this->refactory,
                    $factory->getRouter(),
                    $factory->getStorageAdapter()
                )
            ];
            $value = $ec->{$fieldName} ?? '';

            // Apply filters
            /* @var IOutputFilter $filter */
            foreach ($filters as $filter) {
                if (!array_key_exists('type', $def)) {
                    throw new ConfigurationError(
                        "definition for field $fieldName lacks a type"
                    );
                }
                $value = $filter->outFilter($def, $value, $language);
            }

            $type = $def['type'];
            $fieldInfo[$fieldName] = [ 'type' => $type ];
            if ($type == 'image') {
                $fields[$fieldName] = $value;
            } elseif (in_array($type, ['text', 'meta', 'tag'])) {
                $default = $def['default'] ?? '';
                $fields[$fieldName] = strlen($value) ? $value : $default;
                if ($type === 'tag') {
                    if (!array_key_exists('validChoices', $def)) {
                        throw new RuntimeException(
                            "field of type tag misses " .
                            "definition of validChoices"
                        );
                    }
                    $fieldInfo[$fieldName]['validChoices'] = $def['validChoices'];
                }
            } else {
                throw new RuntimeException("Unknown type: '$type'");
            }
        }
        return [$fields, $fieldInfo];
    }

    /**
     * @param User              $user        for which to collect data
     * @param int               $depth       recursion depth
     * @param array             $subInfo     sub element definition from parent
     * @param ElementContents[] $subContents contents of the sub element
     * @param string            $subName     name of the collection
     * @param string            $language    of the sub elements retrieved
     * @return array
     */
    public function collectSubEleData(
        User $user,
        int $depth,
        array $subInfo,
        array $subContents,
        string $subName,
        string $language
    ) {
        $subs = [];
        /* @var IElementDefinition $subDef */
        $subDef = $subInfo['definition'];
        foreach ($subContents as $subIdx => $subContent) {
            // FIXME: sub-elements should be able to reference other
            // elements, but they are just an ElementContents object, which
            // doesn't have links at all.
            /*
            $refs = $this->collectElementReferences(
                $user,
                $subContent,
                $depth,
                $subDef
            );
            */

            list ($fields, $fieldInfo) = $this->filterFieldsByDefinition(
                $subDef,
                $subContent,
                $language
            );
            $subs[] = [
                // sub-elements do not have their own id, but use
                // the parent's.
                'sub_name' => $subName,
                'sub_idx' => $subIdx,
                'fields' => $fields,
                'fieldInfo' => $fieldInfo,
                'language' => $language,
                // 'refs' => $refs
            ];
        }
        return [$subDef, $subs];
    }

    /**
     * @param ElementVersion $startEV to start from
     * @param User           $user    for authorization
     * @param callable       $filter  applied to child elements
     * @param int            $limit   after which to stop loading elements
     * @return string[] array of element ids of all children
     */
    public function determineVisibleChildrenRecursive(
        ElementVersion $startEV,
        User $user,
        callable $filter,
        int $limit
    ) : array {
        $stack = [$startEV];
        $result = [];
        while (!empty($stack)) {
            /* @var ElementVersion $cur */
            $cur = array_pop($stack);
            $children = $cur->getChildren();
            foreach ($children as $childId) {
                $child = $this->adapter->loadElement($childId);
                list ($vno, , $ev)
                    = $this->loadBestVersion($child, $user);
                if (isset($ev)) {
                    array_push($stack, $ev);
                    if ($filter($child, $vno)) {
                        $result[] = $childId;
                        if ($limit >= 0 && count($result) >= $limit) {
                            return $result;
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @param User               $user   for which to collect data
     * @param ElementContents    $ec     resolved contents affected
     * @param int                $depth  recursion depth
     * @param IElementDefinition $eleDef element definition
     * @param string             $lang   language used
     * @return array
     */
    protected function collectElementSubs(
        User $user,
        ElementContents $ec,
        int $depth,
        IElementDefinition $eleDef,
        string $lang
    ) : array {
        $knownSubs = $eleDef->getKnownSubElements();
        $subs = [];
        $subDefs = [];
        foreach ($knownSubs as $subName => $subInfo) {
            $subContents = $ec->getSubs($subName);
            if (isset($subContents)) {
                list ($subDef, $subData) = $this->collectSubEleData(
                    $user,
                    $depth,
                    $subInfo,
                    $subContents,
                    $subName,
                    $lang
                );
                $subDefs[$subName] = $subDef;
                $subs[$subName] = $subData;
            }
        }
        return array($subs, $subDefs);
    }

    /**
     * @param User           $user         for which to collect data
     * @param int            $depth        recursion depth
     * @param array          $linkDef      link definition
     * @param ElementVersion $parentEV     of the children to collect data from
     * @param bool           $selectable   flag from the link def
     * @param string|null    $refElementId the selected child, if any
     * @return array
     */
    protected function collectReferencedChildren(
        User $user,
        int $depth,
        array $linkDef,
        ElementVersion $parentEV,
        bool $selectable,
        $refElementId
    ):array {
        $childDefName = $linkDef['definition'] ?? '';
        $children = $this->determineVisibleChildrenRecursive(
            $parentEV,
            $user,
            function (Element $element, int $vno) {
                return $element->getType() == 'snippet';
            },
            $linkDef['limit'] ?? -1
        );
        $childData = [];
        foreach ($children as $childId) {
            $child = $this->adapter->loadElement($childId);
            list (, $twigData) = $this->collectElementData(
                $user,
                $child,
                $depth + 1,
                $childDefName
            );
            // Skip child elements for which there's no single version
            // for which the user has access rights.
            if (isset($twigData)) {
                $childData[] = $twigData;
            }
        }

        $result = [
            'children' => $childData,
        ];
        if ($selectable) {
            $result['selected'] = in_array($refElementId, $children)
                ? $refElementId : null;
        }
        return $result;
    }

    /**
     * @param User   $user         user to collect data for
     * @param string $refElementId id of the referenced element
     * @param int    $depth        invocation depth
     * @return array|null
     * @throws ConfigurationError
     */
    protected function loadSingleRef(
        User $user,
        string $refElementId,
        int $depth
    ) {
        // This method returns an array of options. In the non-admin case,
        // this array just happens to be exactly of size one.
        if (!empty($refElementId)) {
            $refElement = $this->adapter->loadElement($refElementId);
            list (, $twigData) = $this->collectElementData(
                $user,
                $refElement,
                $depth + 1
            );

            // Note that $twigData may be null, here, if the user does
            // not have permissions to see any version of the linked
            // element.
            if (!is_null($twigData)) {
                $twigData['selectable'] = true;
                return $twigData;
            }
        }
        return null;
    }

    /**
     * @param User               $user             for which to collect data
     * @param ElementVersion     $ev               resolved version affected
     * @param string             $refererElementId for a hack
     * @param int                $depth            recursion depth
     * @param IElementDefinition $eleDef           element definition
     * @return array
     * @throws ConfigurationError
     */
    protected function collectElementReferences(
        User $user,
        ElementVersion $ev,
        string $refererElementId,
        int $depth,
        IElementDefinition $eleDef
    ) : array {
        $knownRefs = $eleDef->getKnownReferences();
        $refs = [];
        foreach ($knownRefs as $linkName => $linkDef) {
            $selectable = boolval($linkDef['selectable']
                ?? !array_key_exists('direct', $linkDef));
            if ($selectable) {
                $refElementId = $ev->getLink($linkName);
            } else {
                $refElementId = null;
            }

            // FIXME: being non-anonymous should not be enough to be eligible
            // to change referenced elements.
            if ($selectable && $user->isAnonymousUser()) {
                // short-circuit for the standard, non-admin case
                assert(
                    BaseStorageAdapter::isValidElementId($linkDef['parent'])
                );
                $refInfo = $this->loadSingleRef($user, $refElementId, $depth);
                $refs[$linkName] = [
                    'element_id' => $linkDef['parent'],
                    'children' => isset($refInfo) ? [$refInfo] : [],
                    'selected' => $refElementId,
                    'selectable' => true,
                    'direct' => false
                ];
            } elseif (!$selectable && isset($linkDef['direct'])) {
                $direct = $this->adapter->loadElement($linkDef['direct']);
                if (isset($direct) && $refererElementId != $linkDef['direct']) {
                    list (, $twigData) = $this->collectElementData(
                        $user,
                        $direct,
                        $depth + 1,
                        $linkDef['recurse'] ?? true
                    );
                } else {
                    error_log(
                        "Missing directly linked element: " .
                        $linkDef['direct']
                    );
                    $twigData = null;
                }

                if (isset($twigData)) {
                    $refs[$linkName] = [
                        'children' => [$twigData],
                        'selectable' => false,
                        'selected' => $linkDef['direct'],
                        'direct' => true
                    ];
                } else {
                    $refs[$linkName] = null;
                }
            } else {
                // In the non-selectable case, the page definition only
                // specifies a parent collection. All contained elements need
                // to be loaded in that case. Later filtering may apply.
                $parent = $this->adapter->loadElement($linkDef['parent'] ?? '');
                if (is_null($parent)) {
                    throw new \RuntimeException(
                        "Parent element '" . $linkDef['parent']
                        . "' defined for link '$linkName' could not be found."
                    );
                }

                /** @var ElementVersion $parentEV */
                list ($parentVno, $parentLang, $parentEV)
                    = $this->loadBestVersion($parent, $user);
                $finalState = $this->process->getFinalState();

                $twigData = [
                    'element_id' => $linkDef['parent'],
                    'version' => $parentVno,
                    'language' => $parentLang,
                    'definition' => null,
                    'direct' => false,
                    'selectable' => $selectable
                ];
                if (is_null($parentEV)) {
                    error_log(
                        "WARNING: No valid or authorized version found " .
                        "for parent element '" . $linkDef['parent'] .
                        "' defined for link '$linkName' and user '"
                        . $user->getId() . "'"
                    );
                    $twigData['children'] = [];
                } elseif ($parentEV->getState() == $finalState) {
                    error_log(
                        "WARNING: Parent element '" . $linkDef['parent'] .
                        "' has been deleted and is not visible to user '" .
                        $user->getId() . "'"
                    );
                    $twigData['children'] = [];
                } else {
                    $childrenData = $this->collectReferencedChildren(
                        $user,
                        $depth,
                        $linkDef,
                        $parentEV,
                        $selectable,
                        $refElementId
                    );
                    $twigData = array_merge($twigData, $childrenData);
                }
                $refs[$linkName] = $twigData;
            }
        }
        return $refs;
    }

    /**
     * The actual work horse of collectElementData, implemented as a separate
     * method for testability.
     *
     * @param User               $user      for which to collect data
     * @param string             $elementId id of the element
     * @param ElementContents    $ec        resolved contents affected
     * @param ElementVersion     $ev        resolved version affected
     * @param int                $depth     recursion depth
     * @param bool               $recurse   to further referenced elements
     * @param IElementDefinition $eleDef    element definition
     * @param int                $vno       version number to use
     * @param string             $language  language used
     * @return array
     * @throws ConfigurationError
     */
    public function collectElementContentsData(
        User $user,
        string $elementId,
        ElementContents $ec,
        ElementVersion $ev,
        int $depth,
        bool $recurse,
        IElementDefinition $eleDef,
        int $vno,
        string $language
    ) {
        list($subs, $subDefs) = $this->collectElementSubs(
            $user,
            $ec,
            $depth,
            $eleDef,
            $language
        );
        if ($recurse) {
            $refs = $this->collectElementReferences(
                $user,
                $ev,
                $elementId,
                $depth,
                $eleDef
            );
        } else {
            $refs = null;
        }

        list ($fields, $fieldInfo)
            = $this->filterFieldsByDefinition($eleDef, $ec, $language);
        try {
            $pageTitle = $ev->getPageTitle($language);
        } catch (RuntimeException $e) {
            $pageTitle = "<untitled element>";
            error_log(
                "WARNING: element $elementId version $vno/$language: "
                . $e->getMessage()
            );
        }

        $elementData = [
            'element_id' => $elementId,
            'version' => $vno,
            'language' => $language,
            'state' => $ev->getState(),
            'fields' => $fields,
            'pageTitle' => $pageTitle,
            'fieldInfo' => $fieldInfo,
            'definition' => $eleDef,
            'subDefs' => $subDefs,
            'subs' => $subs,
            'refs' => $refs
        ];

        if ($depth == 1) {
            $slugs = $ev->getSlugs();
            $encodedSlugs = [];
            foreach ($slugs as $slug) {
                if (!$slug->deprecated) {
                    $encodedSlugs[] = [
                        'url' => rawurldecode($slug->url),
                        'language' => $slug->language,
                        'default' => $slug->default ?? false
                    ];
                }
            }
            $elementData['slugs'] = $encodedSlugs;
        }

        return $elementData;
    }

    /**
     * Recursive loader of element data, validating against the element
     * definition, returning data needed to render the element.
     *
     * @param User    $user       user for which to collect data
     * @param Element $element    element to collect data for
     * @param int     $depth      recursion depth, starting at 1
     * @param bool    $recurse    down to child elements
     * @param string  $expDefName expected element definition (optional)
     * @return array of data ready to be passed to the renderer
     * @throws ConfigurationError
     */
    public function collectElementData(
        User $user,
        Element $element,
        int $depth = 1,
        bool $recurse = true,
        string $expDefName = ''
    ) : array {
        /** @var ElementVersion $ev */
        list ($vno, $lang, $ev, $viewOnly)= $this->loadBestVersionEx(
            // FIXME: we shouldn't need to hard-code this action name
            // here. Even less if you consider other permissions like
            // publish.
            'edit',
            $element,
            $user
        );

        $finalState = $this->process->getFinalState();
        if (is_null($ev) || $ev->getState() == $finalState) {
            return [null, null];
        }

        assert(isset($ev));
        $ec = $ev->getContentsFor($lang);

        // Load the definition's name and cross check against expectations.
        $defName = $ev->getDefinition();
        if (!is_string($defName)) {
            throw new \RuntimeException(
                "element " . $element->getId() . " misses a definition"
            );
        }
        if (!empty($expDefName)) {
            if ($defName != $expDefName) {
                throw new \RuntimeException(
                    "Child " . $element->getId() . " uses definition "
                    . $defName . ", but the link expects children of type "
                    . $expDefName
                );
            }
        }

        // Load the element's definition, if available, or fall back to the
        // expected definition, if none is given.
        assert(is_string($defName) || is_null($defName));
        $eleDef = isset($defName)
            ? $this->eleDefRegistry->getEleDefById($defName) : null;
        assert(isset($eleDef));

        if ($depth == 1) {
            // Simply add this with a q even higher than what we used for the
            // session's language.
            $this->csel->addLanguagePreference($lang, 99.0);

            if ($element->getType() == 'page' &&
                !$eleDef instanceof BasePageDefinition
            ) {
                throw new ConfigurationError(
                    "Page elements must have an element definition based " .
                    "on TwigElementDefinition"
                );
            }
        }

        $elementData = $this->collectElementContentsData(
            $user,
            $element->getId(),
            $ec,
            $ev,
            $depth,
            $recurse,
            $eleDef,
            $vno,
            $lang
        );
        $elementData['viewOnly'] = $viewOnly;
        return [$eleDef, $elementData];
    }

    /**
     * @param Element $currentElement to load ancestors for
     * @return Element[] of ancestors, including the current element
     */
    public function loadAncestors(Element $currentElement) : array
    {
        // Determine all ancestors of the current element, up to the root.
        $ancestors = [];
        $element = $currentElement;
        while (!is_null($element)) {
            $ancestors[] = $element;
            $elementId = $element->getParentId();
            $element = isset($elementId)
                ? $this->adapter->loadElement($elementId) : null;
        }
        $ancestors = array_reverse($ancestors);
        return $ancestors;
    }

    /**
     * Assembles all required menus or submenus for the given language.
     *
     * @param array    $reqMenus    template requirements
     * @param array    $ancestors   of the current element (page)
     * @param User     $user        for authorization
     * @param callable $refinerFunc for menu entry refinement
     * @return array of menu entries
     */
    public function loadRequiredMenus(
        array $reqMenus,
        array $ancestors,
        User $user,
        callable $refinerFunc
    ) : array {
        // Trigger assembly of the required menus.
        $menus = [];
        foreach ($reqMenus as $name => $menu_def) {
            if ($menu_def['start_level'] > 0) {
                $level = $menu_def['start_level'] - 1;
            } else {
                // 0 and below is interpreted as an offset from the current
                // element backwards, i.e. start_level == 0 gives the
                // children of the current element.
                $level = count($ancestors) - 1 - $menu_def['start_level'];
            }
            if (array_key_exists($level, $ancestors)) {
                $parent = $ancestors[$level];
                $activeChildren = array_map(
                    function (Element $e) {
                        return $e->getId();
                    },
                    array_slice($ancestors, $level + 1)
                );
                $menus[$name] = $this->assembleMenuFrom(
                    'view',
                    $parent,
                    $activeChildren,
                    isset($menu_def['depth']) ? $menu_def['depth'] : null,
                    $user,
                    $refinerFunc
                );
            } else {
                $menus[$name] = null;
            }
        }
        return $menus;
    }

    /**
     * Assembles a single level of a menu consisting of children of one
     * parent element.
     *
     * @param string   $actionName     for permission checks, usually 'view'
     * @param Element  $parent         element from which to fetch children
     * @param string[] $activeChildren ids of the active children
     * @param int|null $depth          depth limit of the menu to generate
     * @param User     $user           for authorization
     * @param callable $refinerFunc    for menu entry refinement
     * @return array of menu entries, possibly nested
     */
    public function assembleMenuFrom(
        string $actionName,
        Element $parent,
        array $activeChildren,
        $depth,
        User $user,
        callable $refinerFunc
    ) : array {
        // FIXME: this needs caching, but for now, we apply brute force.
        /** @var ElementVersion $parentVersion */
        list (, , $parentVersion) = $this->loadBestVersion($parent, $user);
        $finalState = $this->process->getFinalState();

        // If there's no version the user is allowed to see, this method
        // should probably not be called at all.
        assert(isset($parentVersion));
        assert($parentVersion->getState() != $finalState);

        $menuEntries = [];
        foreach ($parentVersion->getChildren() as $childElementId) {
            $child = $this->adapter->loadElement($childElementId);
            assert(!is_null($child));

            $childType = $child->getType();
            assert(array_key_exists(
                $childType,
                Constants::VALID_ELEMENT_TYPES
            ));

            // Skip search result pages.
            //
            // FIXME: #4994 demands a more generally usable solution to this
            // problem, but for now, that's good enough.
            if ($childType == 'search') {
                continue;
            }

            if ($child->getParentId() != $parent->getId()) {
                throw new RuntimeException(
                    "parent " . $parent->getId() . " references child "
                    . $child->getId()
                    . " but the child links to a different parent: "
                    . $child->getParentId()
                );
            }

            // Try the requested action, first.
            list ($vno, $lang, $ev, $viewOnly) = $this->loadBestVersionEx(
                $actionName,
                $child,
                $user
            );

            // if there's no valid version the given user is allowed
            // view, we simply skip the child.
            if ($vno <= 0) {
                continue;
            }
            $ev = $child->getVersion($vno);
            assert(isset($ev));

            // Similarly, we skip deleted elements.
            if ($ev->getState() == $finalState) {
                continue;
            }

            try {
                // FIXME: this method is used for the admin tree as well, where
                // we should display the name, not the menuLabel.
                $menuLabel = $ev->getMenuLabel($lang);
            } catch (RuntimeException $e) {
                $menuLabel = "<unnamed element>";
                error_log(
                    "WARNING: element " . $child->getId() . " version $vno/$lang: "
                    . $e->getMessage()
                );
            }

            $entry = [
                'label' => $menuLabel,
                'id' => $childElementId,
                'active' => ($activeChildren[0] ?? '') == $childElementId,
                'version' => $vno,
                'view_only' => $viewOnly
            ];

            $refinerFunc($child, $lang, $entry);

            // recurse for nested levels
            if (is_null($depth) || $depth > 1) {
                $entry['children'] = $this->assembleMenuFrom(
                    $actionName,
                    $child,
                    array_slice($activeChildren, 1),
                    is_null($depth) ? null : $depth - 1,
                    $user,
                    $refinerFunc
                );
            }

            $menuEntries[] = $entry;
        }

        return $menuEntries;
    }

    /**
     * @param ElasticsearchInterface $esInterface to use
     * @param Element                $element     for result presentation
     * @param string                 $query       original query
     * @return array
     */
    public function performTextSearch(
        ElasticsearchInterface $esInterface,
        Element $element,
        string $query
    ) : array {
        // Text search isn't user dependent, but only contents visible for
        // anonymous is being indexed.
        $user = User::getAnonymousUser();

        /* @var ElementVersion $ev */
        list (, , $ev) = $this->loadBestVersion($element, $user);
        list ($indexName, $searchResults) = $esInterface->search(
            $element->getId(),
            $this->csel->getLanguagePreferences(),
            $query
        );

        if ($searchResults['timed_out']) {
            // FIXME: should be handled more gracefully
            throw new RuntimeException("search timed out");
        } else {
            error_log("query took: " . $searchResults['took']);
            error_log("timed out:  " . $searchResults['timed_out']);
            error_log("hits:       " . $searchResults['hits']['total']);
            error_log("best score: " . $searchResults['hits']['max_score']);

            $matchedElements = [];
            $maxHits = 10;
            foreach ($searchResults['hits']['hits'] as $hit) {
                $maxHits -= 1;
                if ($maxHits <= 0) {
                    break;
                }
                $element = $this->adapter->loadElement($hit['_id']);
                // Skip elements that don't exist anymore.
                if (is_null($element)) {
                    continue;
                }

                // Again, note that we load the version visible to anonymous.
                // Given the index is based on these versions, that's probably
                // what we should display.
                list (, $language, $ev)
                    = $this->loadBestVersion($element, $user);

                assert(array_key_exists('highlight', $hit));
                $contents = '';
                foreach (array_values($hit['highlight']) as $highlighted) {
                    $contents .= '<p>' . implode(' ... ', $highlighted) . '</p>';
                }

                if ($element->getType() == 'page') {
                    try {
                        $urlp = $this->resolver->getLinkForElement($element);
                    } catch (NoUrlPointer $e) {
                        continue;
                    }
                } else {
                    $urlp = $esInterface->getLinkForSnippet(
                        $indexName,
                        $element
                    );
                }

                $matchedElements[] = [
                    'element_id' => $hit['_id'],
                    'score' => $hit['_score'],
                    'title' => $ev->getPageTitle($language),
                    'contents' => $contents,
                    'display_link' => urldecode($urlp->getUrl()),
                    'link' => $urlp->getUrl()
                ];
            }

            return [
                'total_hits' => $searchResults['hits']['total'],
                'max_score' => $searchResults['hits']['max_score'],
                'query_time' => $searchResults['took'],
                'matches' => $matchedElements
            ];
        }
    }

    /**
     * @param IElementDefinition $eleDef for field definitions
     * @param array              $fields with data from collectElementData
     * @return array
     */
    private function collectIndexedFieldContents($eleDef, $fields) : array
    {
        $strippedFields = [];
        $knownFields = $eleDef->getKnownContentFields();
        foreach ($fields as $fieldName => $fieldData) {
            assert(array_key_exists($fieldName, $knownFields));
            if (!array_key_exists('type', $knownFields[$fieldName])) {
                error_log(
                    'WARNING: missing type for field ' . $fieldName .
                    ', the full text search index ignores it.'
                );
                continue;
            }
            if ($knownFields[$fieldName]['type'] !== 'text') {
                continue;
            }

            // Some transformation prior to indexing.
            $strippedData = preg_replace('|<br\s*>|', ' ', $fieldData);
            $strippedData = preg_replace('|</a>|', ' ', $strippedData);
            $strippedData = preg_replace('|</p>|', ' ', $strippedData);
            $strippedData = preg_replace('|</li>|', ' ', $strippedData);
            $strippedData = preg_replace('|</h\d+>|', ' ', $strippedData);
            $strippedData = strip_tags($strippedData);
            $strippedData = html_entity_decode(
                $strippedData,
                ENT_QUOTES | ENT_HTML401,
                'UTF-8'
            );
            $strippedData = preg_replace('/\s+/', ' ', $strippedData);

            if (strlen($strippedData) > 0) {
                $strippedFields[] = $strippedData;
            }
        }
        return $strippedFields;
    }

    /**
     * @param Element $element for which to collect text to index
     * @return string
     */
    private function collectIndexedContents(Element $element) : string
    {
        /* @var BasePageDefinition $eleDef */
        list ($eleDef, $elementData) = $this->collectElementData(
            User::getAnonymousUser(),
            $element
        );

        if (is_null($elementData)) {
            return '';
        }

        $strippedFields = $this->collectIndexedFieldContents(
            $eleDef,
            $elementData['fields']
        );
        foreach ($elementData['subs'] as $subName => $subs) {
            $subEleDef = $elementData['subDefs'][$subName];
            foreach ($subs as $sub) {
                $strippedFields = array_merge(
                    $strippedFields,
                    $this->collectIndexedFieldContents(
                        $subEleDef,
                        $sub['fields']
                    )
                );
            }
        }

        if (count($strippedFields) > 0) {
            return implode("\n\n", $strippedFields);
        } else {
            return '';
        }
    }

    /**
     * This is a quick hack to eliminate deleted elements from the search
     * index. Unfortunately, deleting an element with children only marks
     * the parent deleted. The children would get reachable via this
     * full text search feature. See #5633.
     *
     * @param Element $element to check
     * @return bool
     */
    public function checkAllParentsAlive(Element $element) : bool
    {
        $ancestors = $this->loadAncestors($element);
        /* @var Element $ancestor */
        foreach ($ancestors as $ancestor) {
            /* @var ElementVersion $ev */
            foreach ($ancestor->getVersions() as $ev) {
                // FIXME: ask the process for 'finished' state.
                if ($ev->getState() == 'deleted') {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param ElasticsearchInterface $esInterface to use for indexing
     * @param [string]               $elementIds  to reindex
     * @return void
     */
    public function triggerReindexOfElements(
        ElasticsearchInterface $esInterface,
        array $elementIds
    ) {
        apcu_clear_cache();

        foreach ($elementIds as $elementId) {
            $element = $this->adapter->loadElement($elementId);
            // FIXME: this is a hack to hide elements that should have been
            // deleted.
            if (!$this->checkAllParentsAlive($element)) {
                continue;
            }

            // No need to index the search result page itself or collections.
            if (in_array($element->getType(), ['search', 'collection'])) {
                continue;
            }

            $indices = $esInterface->getIndicesPerElement($element);
            // Skip if this element is not contained in any index.
            if (count($indices) == 0) {
                error_log("skipping element " . $element->getId());
                continue;
            }

            $titles = [];
            $contents = [];
            $knownLanguages = $element->getNewestVersionNumberByLanguage();
            foreach (array_keys($knownLanguages) as $language) {
                $preferences = [$language => '99.0'];
                $this->csel->setLanguagePreferences($preferences, $language);

                $contentToIndex = $this->collectIndexedContents($element);
                if (strlen($contentToIndex) > 0) {
                    $contents[$language] = $contentToIndex;
                }
            }

            // Add the element to all indices covering it.
            foreach ($indices as $indexName) {
                $esInterface->indexPutElement(
                    $indexName,
                    $element->getId(),
                    $titles,
                    $contents
                );
            }

            error_log(
                "processed element " . $element->getId() .
                " (for " . implode(', ', $indices) . ")"
            );
        }
    }

    /**
     * Triggers a background refresh of all full text search indexes.
     *
     * @param ElasticsearchInterface $esInterface to use for indexing
     * @return void
     */
    public function triggerFTSFullReindex(ElasticsearchInterface $esInterface)
    {
        error_log("refreshing all indices");
        $esInterface->recreateAllIndices();
        $this->triggerReindexOfElements(
            $esInterface,
            $this->adapter->enumAllElementIds()
        );
    }
}
