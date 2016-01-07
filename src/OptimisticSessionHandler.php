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

    private $sessionBeforeSessionStart;

    public function open($save_path , $name){
        $this->sessionBeforeSessionStart = isset($_SESSION)?$_SESSION:[];
        error_log(__LINE__ . " OPEN START :: "  . $_SERVER['REQUEST_URI'] . " :: " . var_export(isset($this->sessionBeforeSessionStart['wo6GWnGEJkteGVCElMY9MoufUserLogin']), true));
        //error_log("##" . __LINE__ . var_export($this->sessionBeforeSessionStart, true));
        parent::open($save_path , $name);
        //error_log("##" . __LINE__ . var_export($_SESSION, true));
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
        $_SESSION = $this->sessionBeforeSessionStart;
        error_log(__LINE__ . " READ START ::"  . $_SERVER['REQUEST_URI'] . " :: " . var_export(isset($_SESSION['wo6GWnGEJkteGVCElMY9MoufUserLogin']), true));
        //error_log(__LINE__ . var_export($_SESSION, true));
        $diskSession = $this->getSessionStoredOnDisk($session_id);
        //error_log(__LINE__ . var_export($diskSession, true));
        if (!$this->lock) {
            //$_SESSION = $this->session;
            session_write_close();
        }
        //error_log(__LINE__ . var_export($this->session, true));
        $ret = $this->compareSessions($this->session, $_SESSION, $diskSession);
        $finalSession = $ret['finalSession'];

        $this->session = $finalSession;
        $_SESSION = $finalSession;
        error_log(__LINE__ . " READ FINAL ::"  . $_SERVER['REQUEST_URI'] . " :: " . var_export(isset($_SESSION['wo6GWnGEJkteGVCElMY9MoufUserLogin']), true));
        return session_encode();
    }

    /**
     * Reads a session from the disk and returns it.
     *
     * @param string $session_id
     * @return mixed
     */
    private function getSessionStoredOnDisk($session_id) {
        $data = parent::read($session_id);

        // Unserialize session (trick : session_decode writes in $_SESSION)
        $currentSession = $_SESSION;
        session_decode($data);
        //error_log(__LINE__ . var_export($data, true));
        $diskSession = $_SESSION;
        $_SESSION = $currentSession;

        return $diskSession;
    }

    /**
     * This function must be called when the session (which is closed) need to be checked and written
     * Compare differences between the saved session and the new content of $_SESSION
     * - if there is no differences, no need to reopen and save the session
     * - else the session is reopen and reread then the sessions are compared and merged.
     */
    public function writeIfSessionChanged()
    {
        error_log(__LINE__ . " WRITE CLOSE START :: "  . $_SERVER['REQUEST_URI'] . " :: " . var_export(isset($_SESSION['wo6GWnGEJkteGVCElMY9MoufUserLogin']), true));
        if ($this->session === null) {
            error_log("NULL SESSION");
            return;
        }

        //$currentSession = $_SESSION;
        //$oldSession = $this->session;

        if ($_SESSION === array()) {
            error_log("EMPTY SESSION");
            $this->lock = true;
            @session_start();
            session_destroy();
            $this->lock = false;
            return;
        }

        $this->lock = true;
        //We need to '@' the session_start() because we can't send session cookie more then once.
        //error_log(__LINE__ . var_export($_SESSION, true));
        @session_start();
        error_log(__LINE__ . " WRITE CLOSE END :::: " . var_export($_SESSION, true));

        session_write_close();
        $this->lock = false;
//        error_log(var_export($_SESSION, true));
        /*$needWrite = !$this->array_compare_recursive($oldSession, $currentSession);

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
        }*/
    }

    /**
     * @param $oldSession
     * @param $localSession
     * @param $remoteSession
     * @return ["needWrite"=>bool, "finalSession"=>array]
     */
    private function compareSessions($oldSession, $localSession, $remoteSession) {
        if ($oldSession === null){
            $oldSession = [];
        }
        if ($localSession === null){
            $localSession = [];
        }
        if ($remoteSession === null){
            $remoteSession = [];
        }

        $needWrite = !$this->array_compare_recursive($oldSession, $localSession);

        if ($needWrite) {
            $remoteChanged = !$this->array_compare_recursive($remoteSession, $oldSession);

            if (!$remoteChanged) {
                $finalSession = $localSession;
            } else {
                $finalSession = $remoteSession;
                $keys = array_keys(array_merge($remoteSession, $localSession, $oldSession));

                foreach ($keys as $key) {
                    $base = isset($oldSession[$key]) ? $oldSession[$key] : null;
                    $mine = isset($localSession[$key]) ? $localSession[$key] : null;
                    $theirs = isset($remoteSession[$key]) ? $remoteSession[$key] : null;
                    if ($base != $mine && $base != $theirs && $mine != $theirs) {
                        $hasConflictRules = false;
                        foreach ($this->conflictRules as $regex => $conflictRule) {
                            if (preg_match($regex, $key)) {
                                if ($conflictRule == self::OVERRIDE) {
                                    $hasConflictRules = true;
                                    $finalSession[$key] = $mine;
                                    break;
                                } elseif ($conflictRule == self::IGNORE) {
                                    $hasConflictRules = true;
                                    $finalSession[$key] = $theirs;
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
                        $finalSession[$key] = $mine;
                    }
                }
            }
        } else {
            $finalSession = $remoteSession;
        }

        return ["needWrite"=>$needWrite, "finalSession"=>$finalSession];
    }

    /**
     * Compare recursively two arrays and return false if they are not the same.
     *
     * @param array $array1
     * @param array $array2
     *
     * @return bool
     */
    public function array_compare_recursive(array $array1, array $array2)
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
