<?php

namespace Mouf\Utils\Session\SessionHandler;

/**
 * Session handler that releases session lock quickly. Usefull for multiple ajax calls on the same page.
 *
 * @author Alexandre Chenieux
 */
class  OptimisticSessionHandler extends \SessionHandler
{
    /**
     * Contains the $_SESSION read at the first session_start().
     *
     * @var string
     */
    protected $session = null;

    /**
     * Prevent the session to be closed immediatly.
     *
     * @var string
     */
    protected $lock = false;

    /**
     * @var array
     */
    protected $conflictRules = array();

    const IGNORE = -1;
    const OVERRIDE = 1;
    const FAIL = 0;

    /**
     * Define the configuration value for "session.save_handler"
     * By default PHP save session in files.
     *
     * @param array $conflictRules
     */
    public function __construct(array $conflictRules = array())
    {
        $this->conflictRules = $conflictRules;
        ini_set('session.save_handler', 'files');
        register_shutdown_function(array($this, 'writeIfSessionChanged'));
    }

    /**
     * This function is automatically called after the "open" function
     * Use the PHP default "read" function, then save the data and close the session if the session has not to be locked.
     *
     * @param string $session_id
     *
     * @return string
     */
    public function read($session_id)
    {
        $data = parent::read($session_id);

        // Unserialize session (trick : session_decode write in $_SESSION)
        $oldSession = $_SESSION;
        session_decode($data);
        $this->session = $_SESSION;
        $_SESSION = $oldSession;

        if (!$this->lock) {
            $_SESSION = $this->session;
            session_write_close();
        }

        return $data;
    }

    /**
     * This function must be called when the session (which is closed) need to be checked and written
     * Compare differences between the saved session and the new content of $_SESSION
     * - if there is no differences, no need to reopen and save the session
     * - else the session is reopen and reread then the sessions are compared and merged.
     */
    public function writeIfSessionChanged()
    {
        if ($this->session === null) {
            return;
        }

        $currentSession = $_SESSION;
        $oldSession = $this->session;

        if ($currentSession === array()) {
            $this->lock = true;
            @session_start();
            session_destroy();
            $this->lock = false;

            return;
        }

        $needWrite = !$this->array_compare_recursive($oldSession, $currentSession);

        if ($needWrite) {
            $this->lock = true;
            //We need @session_start() because we can't send session cookie more then once.
            @session_start();
            $sameOldAndNew = $this->array_compare_recursive($_SESSION, $oldSession);

            if ($sameOldAndNew) {
                $_SESSION = $currentSession;
            } else {
                $keys = array_keys(array_merge($_SESSION, $currentSession, $oldSession));

                foreach ($keys as $key) {
                    $base = isset($oldSession[$key]) ? $oldSession[$key] : null;
                    $mine = isset($currentSession[$key]) ? $currentSession[$key] : null;
                    $theirs = isset($_SESSION[$key]) ? $_SESSION[$key] : null;
                    if ($base != $mine && $base != $theirs && $mine != $theirs) {
                        $hasConflictRules = false;
                        foreach ($this->conflictRules as $regex => $conflictRule) {
                            if (preg_match($regex, $key)) {
                                if ($conflictRule == self::OVERRIDE) {
                                    $hasConflictRules = true;
                                    $_SESSION[$key] = $mine;
                                    break;
                                } elseif ($conflictRule == self::IGNORE) {
                                    $hasConflictRules = true;
                                    $_SESSION[$key] = $theirs;
                                    break;
                                } elseif ($conflictRule == self::FAIL) {
                                    throw new SessionConflictException('Your session conflicts with a session change in another process on key "'.$key.'"');
                                }
                            }
                        }
                        if (!$hasConflictRules) {
                            throw new SessionConflictException('Your session conflicts with a session change in another process on key "'.$key.'.
                            You can configure a conflict rule which allow us to handle the conflict"');
                        }
                    } elseif ($base != $mine && $base == $theirs && $mine != $theirs) {
                        $_SESSION[$key] = $mine;
                    }
                }
            }
            session_write_close();
            $this->lock = false;
        }
    }

    /**
     * Compare recursively two arrays and return false if they are not the same.
     *
     * @param $array1
     * @param $array2
     *
     * @return bool
     */
    public function array_compare_recursive($array1, $array2)
    {
        if (count($array1) !== count($array2)) {
            return false;
        }

        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (!isset($array2[$key]) || !is_array($array2[$key])) {
                    return false;
                } else {
                    if (!$this->array_compare_recursive($value, $array2[$key])) {
                        return false;
                    }
                }
            } elseif (!isset($array2[$key]) || $array2[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
