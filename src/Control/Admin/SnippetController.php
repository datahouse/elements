<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\ElementController;
use Datahouse\Elements\Control\Exceptions\AccessDenied;
use Datahouse\Elements\Control\Exceptions\ResourceNotFound;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\HttpResponse;
use Datahouse\Elements\Presentation\BasePageDefinition;
use Datahouse\Elements\Presentation\Exceptions\ConfigurationError;
use Datahouse\Elements\Presentation\SnippetRenderer;

/**
 * Controller for the general purpose snippet editor for admins.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class SnippetController extends ElementController
{
    /**
     * @param HttpRequest $request to process
     * @param User        $user    for which to process things
     * @return HttpResponse
     * @throws ConfigurationError|ResourceNotFound
     */
    public function processRequest(
        HttpRequest $request,
        User $user
    ) : HttpResponse {
        assert($request->method === 'GET');
        assert($this->element->getType() == 'snippet');

        if ($user->isAnonymousUser()) {
            throw new AccessDenied(
                "Access denied for snippet " . $this->element->getId()
            );
        }

        $collector = $this->handler->getContentCollector();
        /* @var BasePageDefinition $eleDef */
        list ($eleDef, $elementData) = $collector->collectElementData(
            $user,
            $this->element
        );
        if (is_null($eleDef) || is_null($elementData)) {
            throw new ResourceNotFound();
        }

        // TODO: fine grained permissions, for example, a reviewer shouldn't
        // see froala, and an editor shouldn't be allowed to change the
        // element's state.
        $permissions = [
            'admin' => !$elementData['viewOnly'],
            'allow_metadata_url_update' => false,
            'allow_metadata_tags_update' => true,
            'allow_template_switch' => false,
        ];

        // Snippets are not part of the page tree and therefore can't have a
        // menu.
        $menus = [];
        $collector = $this->handler->getContentCollector();
        $ancestors = $collector->loadAncestors($this->element);

        // Help the assemble method to determine the edit language.
        $sessionLanguage = $this->handler->getSessionHandler()->getLanguage();
        $permissions['create_' . $sessionLanguage] = array_key_exists(
            $sessionLanguage,
            $this->authorization->getAuthorizedLanguages($this->element)
        );

        $templateData = $this->assembleElementsTemplateData(
            $elementData,
            $eleDef,
            $permissions,
            $ancestors,
            $menus
        );

        $renderer = new SnippetRenderer();
        $renderer->setTemplateData($templateData);

        $response = new HttpResponse();
        $response->setStatusCode(200);
        $response->setRenderer($renderer);
        return $response;
    }
}
