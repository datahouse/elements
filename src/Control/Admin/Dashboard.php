<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Exceptions\Redirection;
use Datahouse\Elements\Control\ReadOnlyMixin;
use Datahouse\Elements\Presentation\IRenderer;

/**
 * The dashboard, the thing that should be the main entry point after login
 * to the admin frontend.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class Dashboard extends AdminPageController
{
    use ReadOnlyMixin;

    /**
     * @param User $user who triggered this request
     * @return IRenderer with all data required to render the result
     * @throws Redirection
     */
    public function processGet(User $user) : IRenderer
    {
        if ($user->isAnonymousUser()) {
            throw new Redirection(307, '/admin/login');
        } else {
            $template = $this->getAdminRenderer();
            $template->setTemplateData([
                'permissions' => ['admin' => true]
            ]);
            return $template;
        }
    }
}
