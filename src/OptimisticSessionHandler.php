<?php

namespace Mouf\Utils\Session\SessionHandler;

use Psr\Log\LoggerInterface;

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

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Tells if the "read" function has been called.
     * This allows the "writeIfSessionChanged" to check if the OptimisticSessionHandler has been unregistered.
     *
     * @var bool
     */
    private $readCalled;

    /**
     * @var bool
     */
    private $fisrtSessionStart = true;

    /**
     * @var bool
     */
    private $shutdownFunctionRegistered = false;

    const IGNORE = -1;
    const OVERRIDE = 1;
    const FAIL = 0;

    /**
     * Define the configuration value for "session.save_handler"
     * By default PHP save session in files.
     *
     * @param array $conflictRules
     */
    public function __construct(array $conflictRules = array(), LoggerInterface $logger = null)
    {
        $this->conflictRules = $conflictRules;
        $this->logger = $logger;

        ini_set('session.save_handler', 'files');
    }

    private $sessionBeforeSessionStart;

    /**
     * @param string $save_path
     * @param string $name
     */
    public function open($save_path, $name)
    {
        if (!$this->shutdownFunctionRegistered) {
            register_shutdown_function(array($this, 'writeIfSessionChanged'));
            $this->shutdownFunctionRegistered = true;
        }
        $this->sessionBeforeSessionStart = isset($_SESSION) ? $_SESSION : [];
        return parent::open($save_path, $name);
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
        if (null !== $this->logger) {
            $this->logger->debug($_SERVER['REQUEST_URI'].'Enter session read');
        }
        $_SESSION = $this->sessionBeforeSessionStart;
        $diskSession = $this->getSessionStoredOnDisk($session_id);
        if (!$this->lock) {
            $_SESSION = $diskSession;
            session_write_close();
            $_SESSION = $this->sessionBeforeSessionStart;
        }

        if (!$this->lock && !$this->fisrtSessionStart) {
            $finalSession = $_SESSION;
        } else {
            $ret = $this->compareSessions($this->session, $_SESSION, $diskSession);
            $finalSession = $ret['finalSession'];
        }

        if (!$this->lock && $this->fisrtSessionStart) {
            $this->session = $finalSession;
        }
        $_SESSION = $finalSession;
        $this->readCalled = true;
        $this->fisrtSessionStart = false;

        if (null !== $this->logger) {
            $this->logger->debug($_SERVER['REQUEST_URI'].' READ lock : '.var_export($this->lock, true).' --- Session: '.var_export($_SESSION, true));
        }

        return session_encode();
    }

    /**
     * Reads a session from the disk and returns it.
     *
     * @param string $session_id
     *
     * @return mixed
     */
    private function getSessionStoredOnDisk($session_id)
    {
        $data = parent::read($session_id);

        // Unserialize session (trick : session_decode writes in $_SESSION)
        // Due to PHP 7 change of session_decode behaviour (https://bugs.php.net/bug.php?id=73302) we are now using array_map function
        $currentSession = array_map(function($a) {return $a; }, $_SESSION);
        session_decode($data);
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
        if (null !== $this->logger) {
            $this->logger->debug($_SERVER['REQUEST_URI'].'Enter session write');
        }
        if ($this->session === null) {
            return;
        }

        if ($_SESSION === array()) {
            $this->lock = true;
            $this->secureSessionStart();
            session_destroy();
            $this->lock = false;

            return;
        }

        $this->lock = true;
        $this->secureSessionStart();
        session_write_close();
        $this->session = $_SESSION;
        $this->lock = false;
    }

    /**
     * @throws UnregisteredHandlerException
     */
    private function secureSessionStart()
    {
        $this->readCalled = false;
        //We need to '@' the session_start() because we can't send session cookie more then once.
        @session_start();
        if (!$this->readCalled) {
            throw new UnregisteredHandlerException('It seems that the OptimisticSessionHandler has been unregistered.');
        }
    }

    /**
     * @param $oldSession
     * @param $localSession
     * @param $remoteSession
     *
     * @return ["needWrite"=>bool, "finalSession"=>array]
     */
    private function compareSessions($oldSession, $localSession, $remoteSession)
    {
        if ($oldSession === null) {
            $oldSession = [];
        }
        if ($localSession === null) {
            $localSession = [];
        }
        if ($remoteSession === null) {
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

        return ['needWrite' => $needWrite, 'finalSession' => $finalSession];
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
