<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\Slug;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\IChangeProcess;
use Datahouse\Elements\Control\IJsonResponse;

/**
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementUrls extends BaseElementAjaxController
{
    /**
     * @param HttpRequest $request to process
     * @return array of Slugs to set
     */
    private function parseRequest(HttpRequest $request) : array
    {
        $urls = json_decode($request->getBody(), true);
        $slugs = [];
        foreach ($urls as $slugKey => $urlInfo) {
            $slug = new Slug();
            $parts = explode('/', $urlInfo['url']);
            $slug->url = implode('/', array_map(function ($v) {
                return rawurlencode(strtolower($v));
            }, $parts));
            $slug->language = $urlInfo['language'] ?? '';
            $slug->default = $urlInfo['default'] ?? false;
            $slugs[$slugKey] = $slug;
        }
        return $slugs;
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
        $defaults = [];
        $slugs = $this->parseRequest($request);
        /* @var Slug $slug */
        foreach ($slugs as $slug) {
            if ($slug->default) {
                $defaults[$slug->language] = true;
            }
            if ($slug->url == '' || $slug->language == '') {
                return new JsonAdminResponse(
                    400,
                    "invalid request: empty url or language"
                );
            }
        }

        $response = new JsonAdminResponse(200);
        $languages = array_keys(
            $this->element->getNewestVersionNumberByLanguage()
        );
        $missingDefaultLanguages = array_filter(
            $languages,
            function ($language) use ($defaults) {
                return !array_key_exists($language, $defaults);
            }
        );
        if (!empty($missingDefaultLanguages)) {
            $response->setCode(400);
            $response->appendClientInfo(
                $this->element->getId(),
                "meta_error",
                "need a default URL for: " .
                    implode(', ', $missingDefaultLanguages)
            );
        }

        // Further, more expensive checks only if we're looking good so far.
        $adapter = $this->handler->getAdapter();
        $parentId = $this->element->getParentId();
        $vres = $adapter->checkSlugs(
            $parentId,
            $slugs,
            $this->element->getId()
        );

        foreach ($vres as $key => list ($status, $errMsg)) {
            if ($status != 'good') {
                $response->setCode(400);
            }
            $response->appendClientInfo(
                $this->element->getId(),
                "slug_" . $status,
                [$key, $errMsg]
            );
        }

        return $response;
    }

    /**
     * @param IChangeProcess $process to use for change generation
     * @return callable function for planning the transaction
     */
    public function getTxnPlanningFunc(IChangeProcess $process) : callable
    {
        return function (User $user, HttpRequest $request) use ($process) {
            $slugs = $this->parseRequest($request);
            return $process->planTxnForElementSetUrls(
                $user,
                $this->element,
                $this->vno,
                $slugs
            );
        };
    }
}
