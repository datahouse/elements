<?php

namespace Datahouse\Elements\Tests;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Authorization\IAuthorizationHandler;

/**
 * The NullAuthHandler grants all users all rights on all versions of all
 * elements.
 *
 * @package Datahouse\Elements\Tests
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class NullAuthorizationHandler implements IAuthorizationHandler
{
    /**
     * @inheritdoc
     * @param string  $rightName permission kind to check for
     * @param User    $user      user for whom to check
     * @param Element $element   element to be accessed
     * @return array of tuples
     */
    public function getAuthorizedVersions(
        $rightName,
        User $user,
        Element $element
    ) {
        // $right_name is not used
        // $user is not used
        $result = [];
        /* @var ElementVersion $version */
        foreach ($element->getVersions() as $vno => $version) {
            $result[] = [
                $vno,
                array_keys($version->getLanguages())
            ];
        }
        return $result;
    }

    /**
     * Return the languages possible to view, create or edit for the given
     * element. Note that this doesn't imply a version for the returned
     * language exists, but just that it may be created by the given user,
     * if it doesn't exist.
     *
     * @param Element $element to view or modify
     * @return array actually a may with language => true
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('element')
     */
    public function getAuthorizedLanguages(Element $element)
    {
        return [
            'de' => true,
            'en' => true,
            'fr' => true,
            'it' => true
        ];
    }

    /**
     * @param User $user to check
     * @return bool
     */
    public function permitAdminAccess(User $user)
    {
        return !$user->isAnonymousUser();
    }
}
