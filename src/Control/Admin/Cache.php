<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Exceptions\Redirection;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\HttpResponse;
use Datahouse\Elements\Control\TextSearch\FullReindexJob;
use Datahouse\Elements\Presentation\Exceptions\ConfigurationError;
use Datahouse\Elements\Tools\BgWorkerClient;
use Datahouse\Elements\Tools\BgWorkerServer;

/**
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class Cache extends AdminPageController
{
    /**
     * @return string[]
     */
    public function enumAllowedMethods()
    {
        return ['GET', 'POST'];
    }

    /**
     * @param HttpRequest $request to process
     * @param User        $user    who triggered this request
     * @return HttpResponse
     * @throws Redirection
     * @throws ConfigurationError
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("user"))
     */
    public function processRequest(
        HttpRequest $request,
        User $user
    ) : HttpResponse {
        $this->requireAuthenticated($user);

        $options = $this->handler->getGlobalOptions();

        $sh = $this->handler->getSessionHandler();
        $editLanguage = $sh->getLanguage();
        if (empty($editLanguage)) {
            $editLanguage = $options['DEFAULT_LANGUAGE'];
        }

        if ($request->method === 'POST') {
            $action = $request->getParameter('action');
            if ($action === 'invalidate-elements') {
                apcu_clear_cache();
                $this->handler->getAdapter()->recreateCacheData();
                $msg = "elements cache cleared";
            } elseif ($action === 'fts-reindex') {
                $bgwClient = new BgWorkerClient();
                $esInterface = $this->handler->getElasticsearchInterface();
                $job = new FullReindexJob();
                $job->config = $esInterface->getConfiguration();
                $bgwClient->enqueueJob($job);
                $msg = "started index recreation it the background";
            } else {
                $msg = "no or unknown action specified";
            }
        }

        $elementsCacheInfo = array_map("strval", array_intersect_key(
            apcu_cache_info(),
            array_flip(['num_slots', 'num_hits', 'nm_misses', 'num_inserts',
                'num_entries', 'expunges', 'start_time', 'mem_size',
                'memory_type'])
        ));

        $template = $this->getAdminRenderer();
        $template->setTemplateData([
            'permissions' => ['admin' => true],
            'msg' => $msg ?? null,
            'jsAdminData' => [
                'EDIT_LANGUAGE' => $editLanguage,
                'GLOBAL_OPTIONS' => $this->handler->getGlobalOptions()
            ],
            'elementsCacheInfo' => $elementsCacheInfo
        ]);

        $response = new HttpResponse();
        $response->setStatusCode(200);
        $response->setRenderer($template);
        return $response;
    }
}
