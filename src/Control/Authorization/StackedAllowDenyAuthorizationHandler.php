<?php

namespace Datahouse\Elements\Control\Authorization;

use stdClass;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\User;

/**
 * An usable example of a functional authentication handler. It checks
 * permissions of an element and all its parents, taking into account allow
 * and deny clauses for each right.
 *
 * @package Datahouse\Elements\Control\Authorization
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class StackedAllowDenyAuthorizationHandler extends BaseAuthorizationHandler
{
    /**
     * getRightsImplyingView
     *
     * @return string[]
     */
    protected function getRightsImplyingView()
    {
        return ['edit', 'publish'];
    }

    /**
     * @return string[]
     */
    public function getStatesAllowingAnonymousViewing()
    {
        return ['published', 'deleted'];
    }

    /**
     * Checks an element's permissions for a given user's group.
     *
     * @param string   $type       must be either 'allow' or 'deny'
     * @param string   $rightName  right to check
     * @param array    $userGroups groups to check
     * @param stdClass $elePerms   an element's permissions
     * @return bool found or not
     */
    public function checkGroupRight($type, $rightName, $userGroups, $elePerms)
    {
        assert($type == 'allow' || $type == 'deny');
        foreach ($userGroups as $group) {
            if (isset($elePerms->{$type}->{$group})) {
                $perms = $elePerms->{$type}->{$group};
                if (is_array($perms) && in_array($rightName, $perms)) {
                     return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if the given user has a certain right on the given element.
     *
     * This method scans the tree up to the root element, if necessary, and
     * applies the first permission or prohibition found. This also is where
     * the magic group 'all' is created.
     *
     * @param string  $rightName to check
     * @param User    $user      to authorize
     * @param Element $element   affected
     * @return bool
     */
    protected function hasPermission(
        $rightName,
        User $user,
        Element $element
    ) : bool {
        // The 'view' right enjoys special treatment by getAuthorizedVersions
        // of the authorization handler and shouldn't ever be queried for
        // here.
        assert($rightName !== "view");

        $elePerms = $element->getPermissions();
        $userId = $user->getId();
        $userGroups = $user->getGroups();
        $userGroups[] = 'all';

        if (!$user->isAnonymousUser()) {
            $allowances = $elePerms->{'allow'}->{$userId} ?? [];
            $foundUserAllowance = in_array($rightName, $allowances);
            $prohibitions = $elePerms->{'deny'}->{$userId} ?? [];
            $foundUserProhibition = in_array($rightName, $prohibitions);
        } else {
            $foundUserAllowance = false;
            $foundUserProhibition = false;
        }
        $foundGroupAllowance = $this->checkGroupRight(
            'allow',
            $rightName,
            $userGroups,
            $elePerms
        );
        $foundGroupProhibition = $this->checkGroupRight(
            'deny',
            $rightName,
            $userGroups,
            $elePerms
        );

        // Check for storage data consistency.
        assert(!($foundUserAllowance && $foundUserProhibition));

        // Direct user allowances or prohibitions override possible group
        // rights set on the very same element. Group prohibitions are
        // override allowances.
        if ($foundUserProhibition ||
            ($foundGroupProhibition && !$foundUserAllowance)
        ) {
            return false;
        } elseif ($foundUserAllowance || $foundGroupAllowance) {
            return true;
        } else {
            // If this element itself doesn't offer a clear decision, recurse
            // to the parent element
            $parentId = $element->getParentId();
            if (isset($parentId)) {
                $parent = $this->adapter->loadElement($parentId);
                return $this->hasPermission(
                    $rightName,
                    $user,
                    $parent
                );
            } else {
                // Rights not explicitly granted default to prohibited. The
                // exception of right granted even to anonymous are covered
                // above, already.
                return false;
            }
        }
    }

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
        // 'view' right is implicit, but depends on the state of the element,
        // while other rights are granted by the element definitions.
        if ($rightName == 'view') {
            // an editor or publisher always gets the view right as well, so
            // we check for those.
            $mayEdit = false;
            foreach ($this->getRightsImplyingView() as $rightName) {
                $perm = $this->hasPermission($rightName, $user, $element);
                if ($perm) {
                    $mayEdit = true;
                    break;
                }
            }
            $filterFn = function (
                ElementVersion $version,
                $vno
            ) use ($mayEdit) {
                return $mayEdit || in_array(
                    $version->getState(),
                    $this->getStatesAllowingAnonymousViewing()
                );
            };
            return $this->filterVersions($element, $filterFn);
        } else {
            $perm = $this->hasPermission($rightName, $user, $element);
            if ($perm) {
                $filterFn = function (ElementVersion $version, $vno) {
                    return true;
                };
                return $this->filterVersions($element, $filterFn);
            } else {
                // no rights
                return [];
            }
        }
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
        return ['en' => true];
    }
}
