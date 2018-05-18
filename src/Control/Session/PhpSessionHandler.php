<?php

namespace Datahouse\Elements\Control\Session;

/**
 * Class PhpSessionHandler - the only session handler currently known, using a
 * plain, stupid PHP session.
 *
 * @package Datahouse\Elements\Control\Session
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class PhpSessionHandler implements ISessionHandler
{
    /**
     * @return void
     */
    public function initializeSession()
    {
        session_start();
    }

    /**
     * Checks if the session is initialized.
     *
     * @return bool
     */
    public function isSessionInitialized()
    {
        return isset($_SERVER) && isset($_SESSION);
    }

    /**
     * @return string id of the user currently logged in
     */
    public function getUser() : string
    {
        assert($this->isSessionInitialized());
        return $_SESSION['userId'] ?? '';
    }

    /**
     * @return string language chosen or an empty string
     */
    public function getLanguage() : string
    {
        assert($this->isSessionInitialized());
        return $_SESSION['language'] ?? '';
    }

    /**
     * Associate a user with the current session.
     *
     * @param string $userId  to store to the session
     * @param bool   $isAdmin flag for the corresponding cookie
     * @return void
     */
    public function setUser(string $userId, bool $isAdmin)
    {
        $_SESSION['userId'] = $userId;
        if ($isAdmin) {
            setcookie('admin', 'true', 0, '/');
        }
    }

    /**
     * Disassociate the user from the session (i.e. logout)
     *
     * @return void
     */
    public function unsetUser()
    {
        $_SESSION['userId'] = '';
        setcookie('admin', 'false', time() - 1, '/');
    }

    /**
     * @param string $language to use
     * @return void
     */
    public function setLanguage(string $language)
    {
        $_SESSION['language'] = $language;
        setcookie('language', $language, 0, '/');
    }
}
