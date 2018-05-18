<?php

namespace Datahouse\Elements\Control;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Control\Authorization\IAuthorizationHandler;
use Datahouse\Elements\Presentation\Exceptions\ConfigurationError;
use Datahouse\Elements\Presentation\IElementDefinition;

/**
 * Common base controller for all elements to be displayed in the browser
 * for normal users or to be edited by an admin user.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
abstract class ElementController extends BaseController
{
    protected $element;
    protected $authorization;
    protected $eleDefRegistry;

    /**
     * @param BaseRouter         $router  invoking this controller
     * @param BaseRequestHandler $handler in charge of the request
     * @param Element            $element to display
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler,
        Element $element
    ) {
        parent::__construct($router, $handler);
        $factory = $this->handler->getReFactory()->getFactory();
        $this->authorization = $factory->getAuthorizationHandler();
        $this->eleDefRegistry = $factory->createClass(EleDefRegistry::class);
        $this->element = $element;
    }

    /**
     * Supports only 'GET'
     *
     * @return string[]
     */
    public function enumAllowedMethods()
    {
        return ['GET'];
    }

    /**
     * @param array              $elementData collected by collectElementData
     * @param IElementDefinition $eleDef      of the page or snippet to display
     * @return array
     * @throws ConfigurationError
     */
    protected function collectElementsInfo(
        array $elementData,
        IElementDefinition $eleDef
    ) : array {
        $elementsInfo = [
            $this->element->getId() => [
                'type' => 'main_page',
                'version' => $elementData['version'],
                'language' => $elementData['language'],
                'fields' => $eleDef->getKnownContentFields()
            ]
        ];

        foreach ($elementData['subs'] as $subName => $subs) {
            $subFields = [];
            /* @var IElementDefinition $subDef */
            $subDef = $elementData['subDefs'][$subName];
            $knownSubFields = $subDef->getKnownContentFields();
            foreach ($subs as $subIdx => $link) {
                foreach ($knownSubFields as $fieldName => $fieldDef) {
                    $combinedFieldName = $subName . '-' . $subIdx .
                        '-' . $fieldName;
                    $subFields[$combinedFieldName] = $fieldDef;
                }
            }
            $elementsInfo[$this->element->getId()]['fields'] = array_merge(
                $elementsInfo[$this->element->getId()]['fields'],
                $subFields
            );
        }

        foreach ($elementData['refs'] as $refName => $link) {
            $selectedLink = null;
            if (is_null($link)) {
                continue;
            }
            if ($link['selectable'] || $link['direct']) {
                $selectedId = $link['selected'] ?? '';
                foreach ($link['children'] as $childInfo) {
                    if ($childInfo['element_id'] == $selectedId) {
                        $selectedLink = $childInfo;
                        break;
                    }
                }

                if (!is_null($selectedLink)) {
                    /* @var IElementDefinition $refDef */
                    $refDef = $selectedLink['definition'] ?? null;
                    if (is_null($refDef)) {
                        throw new ConfigurationError(
                            "missing definition for link $refName"
                        );
                    }
                    $elementsInfo[$selectedLink['element_id']] = [
                        'type' => 'referenced',
                        'ref_name' => $refName,
                        'version' => $selectedLink['version'],
                        'language' => $selectedLink['language'],
                        'fields' => $refDef->getKnownContentFields()
                    ];
                }
            } else {
                foreach ($link['children'] as $index => $childInfo) {
                    /* @var IElementDefinition $refEleDef */
                    $refDef = $childInfo['definition'] ?? null;
                    if (is_null($refDef)) {
                        error_log("missing definition for link $refName");
                    }
                    assert(!is_null($refDef));
                    $elementsInfo[$childInfo['element_id']] = [
                        'type' => 'referenced',
                        'ref_name' => $refName,
                        'ref_index' => $index,
                        'version' => $childInfo['version'],
                        'language' => $childInfo['language'],
                        'fields' => $refDef->getKnownContentFields()
                    ];
                }
            }
        }
        return $elementsInfo;
    }

    /**
     * @param array              $elementData collected element data
     * @param IElementDefinition $eleDef      of the page or snippet to view
     * @param array              $permissions granted to the user
     * @param Element[]          $ancestors   of the current element
     * @param array              $menus       to display for this element
     * @return array
     * @throws ConfigurationError
     */
    protected function assembleElementsTemplateData(
        array $elementData,
        IElementDefinition $eleDef,
        array $permissions,
        array $ancestors,
        array $menus
    ) : array {
        // Populate the elementsInfo array for JavaScript
        $elementsInfo = $this->collectElementsInfo($elementData, $eleDef);

        $sessionLanguage = $this->handler->getSessionHandler()->getLanguage();
        $editLanguage = $permissions['create_' . $sessionLanguage]
            ? $sessionLanguage : $elementData['language'];

        // Cross-check the page's state against known ones and choose the
        // appropriate CSS class for displaying the state.
        $pageState = $elementData['state'];
        $knownStates = $this->handler->getChangeProcess()->enumAllowedStates();
        assert(array_key_exists($pageState, $knownStates));
        $pageStateClass = $knownStates[$pageState]['css-class'] ?? '';

        $knownPageDefIds = $this->eleDefRegistry->enumPageDefIds();
        $jsAdminData = [
            'PAGE_ELEMENT_ID' => $this->element->getId(),
            'PAGE_ELEMENT_VERSION' => $elementData['version'],
            'PAGE_ELEMENT_STATE' => $elementData['state'],
            'PAGE_SLUGS' => $elementData['slugs'],
            'KNOWN_STATES' => array_keys($knownStates),
            'ELEMENTS_INFO' => $elementsInfo,
            'EDIT_LANGUAGE' => $editLanguage,
            'GLOBAL_OPTIONS' => $this->handler->getGlobalOptions()
        ];

        $ancestorIds = array_map(function (Element $e) {
            return $e->getId();
        }, $ancestors);

        $jsUserData = [
            'SESSION_LANGUAGE' => $sessionLanguage,
            'PAGE_LANGUAGE' => $elementData['language'],
            'FILE_UPLOAD_SIZE_LIMIT' => min([
                    StaticHelper::returnBytes(ini_get('memory_limit')),
                    StaticHelper::returnBytes(ini_get('post_max_size')),
                    StaticHelper::returnBytes(ini_get('upload_max_filesize')),
                ]) - 1024,
            'PAGE_ANCESTORS' => $ancestorIds
        ];

        return [
            'jsAdminData' => $jsAdminData,
            'jsUserData' => $jsUserData,
            'permissions' => $permissions,
            'known_definition_ids' => $knownPageDefIds,
            'page_element_state_class' => $pageStateClass,
            'fields' => $elementData['fields'],
            'fieldInfo' => $elementData['fieldInfo'],
            'pageTitle' => $elementData['pageTitle'],
            'element_id' => $elementData['element_id'],
            'ancestor_ids' => $ancestorIds,
            'menus' => $menus,
            'subs' => $elementData['subs'],
            'refs' => $elementData['refs']
        ];
    }
}
