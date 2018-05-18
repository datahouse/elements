<?php

namespace Datahouse\Elements\Control\Authorization;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\User;

/**
 * Interface IAuthorizationHandler
 *
 * All authorization tasks should be performed by an authorization handler
 * that implements this interface.
 *
 * @package Datahouse\Elements\Control\Authorization
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
interface IAuthorizationHandler
{
    /**
     * Checks for a certain right on the element and returns an array with the
     * newest version visible per language for which the user has access
     * permissions.
     *
     * @param string  $rightName permission kind to check for
     * @param User    $user      user for whom to check
     * @param Element $element   element to be accessed
     * @return array of tuples of the following form:
     * (int version_number, array of lang strings)
     */
    public function getAuthorizedVersions(
        $rightName,
        User $user,
        Element $element
    );

    /**
     * Return the languages possible to view, create or edit for the given
     * element. Note that this doesn't imply a version for the returned
     * language exists, but just that it may be created by the given user,
     * if it doesn't exist.
     *
     * @param Element $element to view or modify
     * @return array actually a may with language => true
     */
    public function getAuthorizedLanguages(Element $element);

    /**
     * A preliminary check to determine if the given user is granted
     * permissions to the admin area at all.
     *
     * @param User $user to check
     * @return bool
     */
    public function permitAdminAccess(User $user);
}
