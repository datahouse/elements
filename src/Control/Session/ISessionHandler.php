<?php

namespace Datahouse\Elements\Control\Session;

/**
 * Interface a session handler needs to implement.
 *
 * @package Datahouse\Elements\Control\Session
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
interface ISessionHandler
{
    /**
     * Initialize the session, called once per request.
     *
     * @return void
     */
    public function initializeSession();

    /**
     * Checks if the session is initialized.
     *
     * @return bool
     */
    public function isSessionInitialized();

    /**
     * @return string id of the user currently logged in, or an empty string
     */
    public function getUser() : string;

    /**
     *
     * @param string $userId  to store to the session
     * @param bool   $isAdmin flag for the corresponding cookie
     * @return void
     */
    public function setUser(string $userId, bool $isAdmin);

    /**
     * Disassociate the user from the session (i.e. logout)
     *
     * @return void
     */
    public function unsetUser();

    /**
     * @return string language set for the current session
     */
    public function getLanguage() : string;

    /**
     * @param string $language to set for the current session
     * @return void
     */
    public function setLanguage(string $language);
}
