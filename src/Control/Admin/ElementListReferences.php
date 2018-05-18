<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\BaseJsonController;
use Datahouse\Elements\Control\BaseRequestHandler;
use Datahouse\Elements\Control\BaseRouter;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\IJsonResponse;

/**
 * Controller for listing possible referenced elements.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementListReferences extends BaseJsonController
{
    private $element;
    private $vno;
    private $refDef;
    private $refName;

    /**
     * @param BaseRouter         $router  invoking this controller
     * @param BaseRequestHandler $handler in charge of the request
     * @param Element            $element to load or change
     * @param int                $vno     version to change
     * @param array              $refDef  definition of the reference
     * @param string             $refName name of the reference to set
     */
    public function __construct(
        BaseRouter $router,
        BaseRequestHandler $handler,
        Element $element,
        int $vno,
        array $refDef,
        string $refName
    ) {
        parent::__construct($router, $handler);
        $this->element = $element;
        $this->vno = $vno;
        $this->refDef = $refDef;
        $this->refName = $refName;
    }

    /**
     * @return array allow only 'GET' requests.
     */
    public function enumAllowedMethods()
    {
        return ['GET'];
    }

    /**
     * @param HttpRequest $request to validate
     * @param User        $user    for which to process the request
     * @return IJsonResponse
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('request')
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user')
     */
    public function validateRequestData(
        HttpRequest $request,
        User $user
    ) : IJsonResponse {
        return new JsonAdminResponse(200);
    }

    /**
     * @param HttpRequest $request to process
     * @param User        $user    for which to process things
     * @return IJsonResponse
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('request')
     */
    public function processJsonRequest(
        HttpRequest $request,
        User $user
    ) : IJsonResponse {
        $adapter = $this->handler->getAdapter();

        $parent = $adapter->loadElement($this->refDef['parent']);
        /* @var ElementVersion $ev */
        list (, , $ev) = $this->handler->loadBestVersion($parent, $user);

        $options = [];
        foreach ($ev->getChildren() as $childId) {
            $element = $adapter->loadElement($childId);
            // FIXME: check permission (admin?) and visibility (deleted?) of
            // the element
            $options[] = [
                'element_id' => $element->getId(),
                'name' => $element->getDisplayName()
            ];
        }
        // FIXME: extend JsonResponse so it can carry the $options as a result
        // to the request.
        $response = new JsonAdminResponse(200);
        return $response;
    }
}
