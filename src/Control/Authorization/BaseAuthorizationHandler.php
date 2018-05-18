<?php

namespace Datahouse\Elements\Control\Authorization;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\User;

/**
 * Abstract class BaseAuthorizationHandler - basics for authorization
 * handlers.
 *
 * @package Datahouse\Elements\Control\Authorization
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
abstract class BaseAuthorizationHandler implements IAuthorizationHandler
{
    /** @var IStorageAdapter $adapter of the storage layer */
    protected $adapter;

    /**
     * BaseAuthorizationHandler constructor.
     *
     * @param IStorageAdapter $adapter to use
     */
    public function __construct(IStorageAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @param Element  $element  the element for which to filter versions
     * @param \Closure $filterFn function implementing the filter condition
     * @return array of matching tuples of the form:
     * (int $version_number, array of strings for authorized languages)
     */
    protected function filterVersions(Element $element, $filterFn)
    {
        $result = [];
        /**
         * @var int            $vno
         * @var ElementVersion $version
         */
        foreach (array_filter($element->getVersions(), $filterFn, ARRAY_FILTER_USE_BOTH) as $vno => $version) {
            $result[] = [$vno, array_keys($version->getLanguages())];
        }
        return $result;
    }

    /**
     * getAuthorizedVersions
     *
     * @param string  $rightName name of right
     * @param User    $user      a user requesting a right
     * @param Element $element   the element the right was requested on
     *
     * @return mixed
     */
    abstract public function getAuthorizedVersions(
        $rightName,
        User $user,
        Element $element
    );

    /**
     * A default implementation that grants admin access to all known users,
     * except for the special anonymous user.
     *
     * @param User $user to check
     * @return bool
     */
    public function permitAdminAccess(User $user)
    {
        return !$user->isAnonymousUser();
    }
}
