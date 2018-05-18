<?php

namespace Datahouse\Elements\Control\Admin;

use RuntimeException;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\Slug;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\EleDefRegistry;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\IChangeProcess;
use Datahouse\Elements\Control\IJsonResponse;

/**
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementAddChild extends BaseElementAjaxController
{
    private $language;
    /* @var EleDefRegistry $eleDefRegistry */
    protected $eleDefRegistry;

    /**
     * @param BaseRouter         $router   invoking this controller
     * @param BaseRequestHandler $handler  in charge of the request
     * @param Element            $element  to load or change
     * @param string             $language of the version affected
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler,
        Element $element,
        string $language
    ) {
        // FIXME: should add to the last published and all following ones.
        $vno = $element->getNewestVersionNumber();
        parent::__construct($router, $handler, $element, $vno);
        $this->language = $language;
        $factory = $this->handler->getReFactory()->getFactory();
        $this->eleDefRegistry = $factory->createClass(EleDefRegistry::class);
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
        } else {
            return new JsonAdminResponse(200);
        }
    }

    /**
     * @param string $parentId to attach to
     * @param string $name     of the new element to create
     * @return Slug resulting usable slug
     */
    protected function findUsableSlugForNewChild($name)
    {
        $urlPart = rawurlencode(str_replace('/', '-', $name));

        // Acquire a unique slug to use for this new element.
        $adapter = $this->handler->getAdapter();
        $parentId = $this->element->getId();

        $slug = new Slug();
        $slug->language = $this->language;
        $slug->default = true;

        while (true) {
            $slug->url = $urlPart;
            $slugs = ['initial' => $slug];
            $vres = $adapter->checkSlugs($parentId, $slugs);
            assert(array_key_exists('initial', $vres));
            list ($status,) = $vres['initial'];
            if ($status == 'good') {
                break;
            } else {
                // Modify the proposed slug by postfixing a number or
                // incrementing it, until we found a non-conflicting
                // slug we can safely use.
                $urlPart = Slug::incrementSlugPostfixNumber($urlPart);
            }
        }

        return $slug;
    }

    /**
     * @param IChangeProcess $process to use for change generation
     * @return callable function for planning the transaction
     */
    public function getTxnPlanningFunc(IChangeProcess $process) : callable
    {
        $parentType = $this->element->getType();
        if ($parentType == 'collection') {
            // FIXME: this should be configurable per collection, but for now,
            // we simply check what element definition the first child uses.
            //
            // Also note that we can get away with newest version numbers for
            // parent and child, as the snippet's element definition cannot
            // change, anyways.
            $parentVno = $this->element->getNewestVersionNumber();
            $parentEv = $this->element->getVersion($parentVno);
            $curChildren = $parentEv->getChildren();
            if (empty($curChildren)) {
                throw new RuntimeException(
                    "hack doesn't work for empty collections"
                );
            }

            $adapter = $this->handler->getAdapter();
            $firstChild = $adapter->loadElement($curChildren[0]);
            $childVno = $firstChild->getNewestVersionNumber();
            $childEv = $firstChild->getVersion($childVno);

            $eleDef = $childEv->getDefinition();
        } else {
            $eleDef = $this->eleDefRegistry->getDefaultEleDef();
        }

        return function (
            User $user,
            HttpRequest $request
        ) use (
            $process,
            $eleDef,
            $parentType
        ) {
            $name = $request->getBody();
            $slugs = [];
            if ($parentType != 'collection') {
                $slugs['initial'] = $this->findUsableSlugForNewChild($name);
            }
            return $process->planTxnForElementAddChildChange(
                $user,
                $this->element, // parentElement to which to add an element
                $this->language,
                $eleDef,
                $name,
                $slugs
            );
        };
    }
}
