<?php

namespace Datahouse\Elements\Control;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Exceptions\ResourceNotFound;
use Datahouse\Elements\Presentation\Exceptions\ConfigurationError;
use Datahouse\Elements\Presentation\IElementDefinition;

/**
 * Controller for full text searching.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class SearchController extends PageController
{
    protected $searchQuery;

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
        $this->searchQuery = $request->getParameter('q') ?? '';
        return parent::processRequest($request, $user);
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
        // Standard page controller stuff...
        $result = parent::assembleElementsTemplateData(
            $elementData,
            $eleDef,
            $permissions,
            $ancestors,
            $menus
        );

        // ...then extend with search results
        $esInterface = $this->handler->getElasticsearchInterface();
        $collector = $this->handler->getContentCollector();
        $result['query'] = $this->searchQuery;
        $result['searchresults'] = $collector->performTextSearch(
            $esInterface,
            $this->element,
            $this->searchQuery
        );
        return $result;
    }
}
