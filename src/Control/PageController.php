<?php

namespace Datahouse\Elements\Control;

use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Exceptions\ResourceNotFound;
use Datahouse\Elements\Presentation\BasePageDefinition;

/**
 * Base controller class for all pages, i.e. elements shown as the website
 * itself. Note that this also handles admin edits of pages.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class PageController extends ElementController
{
    /**
     * @param HttpRequest $request to process
     * @param User        $user    for which to process things
     * @return HttpResponse
     * @throws ResourceNotFound
     */
    public function processRequest(
        HttpRequest $request,
        User $user
    ) : HttpResponse {
        assert($request->method === 'GET');
        assert(in_array($this->element->getType(), ['page', 'search']));

        $collector = $this->handler->getContentCollector();

        /* @var BasePageDefinition $eleDef */
        list ($eleDef, $elementData) = $collector->collectElementData(
            $user,
            $this->element
        );
        if (is_null($eleDef)) {
            throw new ResourceNotFound();
        }

        // TODO: fine grained permissions, for example, a reviewer shouldn't
        // see froala, and an editor shouldn't be allowed to change the
        // element's state.
        $hasEditAndPublishRights = !$user->isAnonymousUser()
            && !$elementData['viewOnly'];
        $permissions = [
            'admin' => $hasEditAndPublishRights,
            'allow_metadata_url_update' => true,
            'allow_metadata_tags_update' => true,
            'allow_template_switch' => true,
        ];

        // Populate menus
        $ancestors = $collector->loadAncestors($this->element);
        $menus = $collector->loadRequiredMenus(
            $eleDef->getRequiredMenus(),
            $ancestors,
            $user,
            // FIXME: this needs to be improved by making it configurable
            // for the application or something...
            function ($element, $lang, &$entry) use ($user) {
                $this->handler->refineMenuEntry(
                    $element,
                    $lang,
                    $entry
                );
            }
        );

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
        $templateData['current_definition'] = $eleDef->getDisplayName();
        $templateData['request'] = [
            'method' => $request->method,
            'url' => $request->url,
            'referer' => $request->getReferer() ?? null
        ];

        $renderer = $eleDef->getRenderer();
        $renderer->setTemplateData($templateData);

        $response = new HttpResponse();
        $response->setStatusCode(200);
        $response->setRenderer($renderer);
        return $response;
    }
}
