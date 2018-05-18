<?php

namespace Datahouse\Elements\Control\Admin;

use RuntimeException;

use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Admin\Setup\ISetupCheck;
use Datahouse\Elements\Control\Admin\Setup\SetupCheckStorageInitialized;
use Datahouse\Elements\Control\Admin\Setup\SetupCheckStorageVersion;
use Datahouse\Elements\Control\Exceptions\AccessDenied;
use Datahouse\Elements\Control\Exceptions\Redirection;
use Datahouse\Elements\Control\HttpRequest;
use Datahouse\Elements\Control\HttpResponse;

/**
 * Initial data setup controller
 *
 * @package Datahouse\Elements\Control\Admin
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class Setup extends AdminPageController
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
     * @throws AccessDenied|Redirection
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("user"))
     */
    public function processRequest(
        HttpRequest $request,
        User $user
    ) : HttpResponse {
        $factory = $this->handler->getReFactory()->getFactory();
        $adapter = $factory->getStorageAdapter();
        $config = $factory->getConfiguration();

        // Instantiate ISetupCheck classes provided by the application.
        $defaultChecks = [
            new SetupCheckStorageInitialized($adapter),
            new SetupCheckStorageVersion($adapter, $config),
        ];

        $checkClassNames = $config->setupChecks ?? [];
        $appChecks = array_map(function (string $className) use ($factory) {
            return $factory->createClass($className);
        }, $checkClassNames);

        $aborted = false;
        $needsMigration = false;
        $checkResults = [];
        /* @var ISetupCheck $check */
        foreach (array_merge($defaultChecks, $appChecks) as $check) {
            if (!$aborted) {
                $checkResult = $check->check();
                if (!$checkResult) {
                    if ($request->method === 'POST') {
                        $check->adapt();
                        $checkResult = $check->check();
                        if (!$checkResult) {
                            throw new RuntimeException(
                                "setup check " . get_class($check) .
                                " failed after adaption"
                            );
                        }
                    } else {
                        $needsMigration = true;
                        if ($check->abortOnFailure()) {
                            $aborted = true;
                        }
                    }
                }
            } else {
                $checkResult = false;
            }

            $checkResults[] = [
                'state' => $checkResult,
                'description' => $check->getDescription()
            ];
        }

        $renderer = $this->getAdminRenderer();
        $renderer->setTemplateData([
            'initialized' => $checkResults[0]['state'],
            'permissions' => ['admin' => true],
            'checks' => $checkResults,
            'needs_migration' => $needsMigration
        ]);

        $response = new HttpResponse();
        $response->setStatusCode(200);
        $response->setRenderer($renderer);
        return $response;
    }
}
