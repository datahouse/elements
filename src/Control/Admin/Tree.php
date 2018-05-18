<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\ReadOnlyMixin;
use Datahouse\Elements\Presentation\IRenderer;

/**
 * Tree controller
 *
 * @package Datahouse\Elements\Control\Admin
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class Tree extends AdminPageController
{
    use ReadOnlyMixin;

    /**
     * @param User $user who triggered this request
     * @return IRenderer
     */
    public function processGet(User $user) : IRenderer
    {
        $this->requireAuthenticated($user);

        $options = $this->handler->getGlobalOptions();

        $sh = $this->handler->getSessionHandler();
        $editLanguage = $sh->getLanguage();
        if (empty($editLanguage)) {
            $editLanguage = $options['DEFAULT_LANGUAGE'];
        }

        $template = $this->getAdminRenderer();
        $template->setTemplateData([
            'permissions' => ['admin' => true],
            'jsAdminData' => [
                'EDIT_LANGUAGE' => $editLanguage,
                'GLOBAL_OPTIONS' => $this->handler->getGlobalOptions()
            ]
        ]);
        return $template;
    }
}
