<?php

namespace Datahouse\Elements\Control\Admin;

use RuntimeException;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Constants;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\EleDefRegistry;
use Datahouse\Elements\Control\Filter\ClassAttributeFilter;
use Datahouse\Elements\Control\Filter\EmptyStyleAttributeFilter;
use Datahouse\Elements\Control\Filter\IInputFilter;
use Datahouse\Elements\Control\Filter\ImageAttributeFilter;
use Datahouse\Elements\Control\Filter\InternalLinkFilter;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\IChangeProcess;
use Datahouse\Elements\Control\IUrlResolver;
use Datahouse\Elements\Control\IJsonResponse;
use Datahouse\Elements\Presentation\IElementDefinition;

/**
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementField extends BaseElementAjaxController
{
    private $resolver;
    private $language;
    private $fieldNameParts;

    /* @var EleDefRegistry $eleDefRegistry */
    private $eleDefRegistry;

    /**
     * @param BaseRouter         $router    invoking this controller
     * @param BaseRequestHandler $handler   in charge of the request
     * @param IUrlResolver       $resolver  to pass on to filters
     * @param Element            $element   to load or change
     * @param int                $vno       version to change
     * @param string             $language  of the version affected
     * @param string             $fieldName to load or change
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler,
        IUrlResolver $resolver,
        Element $element,
        int $vno,
        string $language,
        string $fieldName
    ) {
        parent::__construct($router, $handler, $element, $vno);
        $this->resolver = $resolver;
        $this->language = $language;
        $this->fieldNameParts = explode('-', $fieldName);

        $factory = $this->handler->getReFactory()->getFactory();
        $this->eleDefRegistry = $factory->createClass(EleDefRegistry::class);
    }

    /**
     * Checks the element definition(s) if the given field name is valid for
     * the element loaded.
     *
     * @param ElementVersion $ev to check
     * @return bool true if the given field is known
     */
    private function isEditableField(ElementVersion $ev) : bool
    {
        $eleDef = $this->eleDefRegistry->getEleDefById($ev->getDefinition());

        $knownFields = $eleDef->getKnownContentFields();
        $knownSubs = $eleDef->getKnownSubElements();

        if (count($this->fieldNameParts) == 1
            && array_key_exists($this->fieldNameParts[0], $knownFields)
        ) {
            return true;
        }

        if (count($this->fieldNameParts) == 3
            && array_key_exists($this->fieldNameParts[0], $knownSubs)
        ) {
            $subName = $this->fieldNameParts[0];
            /* @var IElementDefinition $subElementDef */
            $subElementDef = $knownSubs[$subName]['definition'];
            $subElementFields = $subElementDef->getKnownContentFields();
            if (array_key_exists($this->fieldNameParts[2], $subElementFields)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param HttpRequest $request to validate
     * @param User        $user    for which to process the request
     * @return IJsonResponse
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user')
     */
    public function validateRequestData(
        HttpRequest $request,
        User $user
    ) : IJsonResponse {
        $body = $request->getBody();
        if (is_null($body)) {
            return new JsonAdminResponse(
                400,
                'missing contents for element field to store'
            );
        }
        if (is_null($request->getParameter('edit_language'))) {
            return new JsonAdminResponse(
                400,
                'missing language indication for element field to store'
            );
        }

        $ev = $this->element->getVersion($this->vno);
        if ($this->isEditableField($ev)) {
            return new JsonAdminResponse(200);
        } else {
            return new JsonAdminResponse(404, 'field not found');
        }
    }

    /**
     * Lookup a field definition given an element definition and the field
     * name parts, resolving down to sub elements.
     *
     * @param IElementDefinition $eleDef to start from
     * @param array              $parts  of a combined field name
     * @return array
     * @throws RuntimeException
     */
    public function getFieldDefToEdit(
        IElementDefinition $eleDef,
        array $parts
    ) : array {
        if (count($parts) % 2 != 1) {
            throw new RuntimeException(
                "cannot handle fieldName: " . implode('-', $parts)
            );
        }

        // Resolve sub elements
        while (count($parts) > 1) {
            list ($subName, ) = array_splice($parts, 0, 2);
            $subElements = $eleDef->getKnownSubElements();
            if (array_key_exists($subName, $subElements)) {
                $eleDef = $subElements[$subName]['definition'];
            } else {
                throw new RuntimeException("unknown sub element: $subName");
            }
        }

        assert(count($parts) == 1);
        $fieldName = $parts[0];
        $knownFields = $eleDef->getKnownContentFields();
        if (array_key_exists($fieldName, $knownFields)) {
            return $knownFields[$fieldName];
        } else {
            throw new RuntimeException(
                "unknown field: $fieldName for element type " .
                get_class($eleDef)
            );
        }
    }

    /**
     * @param IChangeProcess $process to use for change generation
     * @return callable function for planning the transaction
     */
    public function getTxnPlanningFunc(IChangeProcess $process) : callable
    {
        $sessLanguage = $this->handler->getSessionHandler()->getLanguage();
        $editLanguage = empty($sessLanguage) ? $this->language : $sessLanguage;
        return function (
            User $user,
            HttpRequest $request
        ) use (
            $process,
            $editLanguage
        ) {
            $newContent = $request->getBody();

            $refactory = $this->handler->getReFactory();
            $factory = $refactory->getFactory();
            $filters = [
                new ClassAttributeFilter(),
                new EmptyStyleAttributeFilter(),
                new ImageAttributeFilter(
                    $refactory,
                    $factory->getRouter(),
                    $factory->getStorageAdapter()
                ),
                new InternalLinkFilter(
                    $this->handler->getReFactory(),
                    $factory->getRouter(),
                    $this->resolver
                )
            ];

            $ev = $this->element->getVersion($this->vno);
            $defName = $ev->getDefinition();
            assert(is_string($defName));
            $eleDef = $this->eleDefRegistry->getEleDefById($defName);
            assert(isset($eleDef));

            $fieldDef = $this->getFieldDefToEdit(
                $eleDef,
                $this->fieldNameParts
            );

            $referer = $request->getReferer();
            $rootUrl = Constants::getRootUrl();
            if (substr($referer, 0, strlen($rootUrl)) == $rootUrl) {
                $relativeTo = substr($referer, strlen($rootUrl));
                /* @var IInputFilter $filter */
                foreach ($filters as $filter) {
                    $newContent = $filter->inFilter(
                        $this->element,
                        $relativeTo,
                        $fieldDef,
                        $newContent
                    );
                }
            } else {
                error_log(
                    "WARNING: referer '$referer' doesn't match " .
                    "root url '$rootUrl' (ElementField.php)"
                );
            }

            // Inputs are valid and the element exists. Ask the IChangeProcess
            // to generate a transaction representing this change.
            return $process->planTxnForElementContentChange(
                $user,
                $this->element,
                $this->vno,
                $this->language,
                $editLanguage,
                $this->fieldNameParts,
                $newContent
            );
        };
    }
}
