<?php

namespace Mouf\Utils\Session\SessionHandler;

/**
 * Session handler that releases session lock quickly. Usefull for multiple ajax calls on the same page
 *
 * @author Alexandre Chenieux
 */
class  OptimisticSessionHandler extends \SessionHandler
{
    /**
     * Contains the $_SESSION read at the first session_start()
     *
     * @var string
     */
    protected $session = null;

    /**
     * Prevent the session to be closed immediatly
     *
     * @var string
     */
    protected $lock = false;

    /**
     * @var array
     */
    protected $conflictRules = array();

    /**
     * Define the configuration value for "session.save_handler"
     * By default PHP save session in files
     *
     * @return string
     */
    public function __construct() {
        ini_set('session.save_handler', 'files');
        register_shutdown_function(array($this, 'writeIfSessionChanged'));
    }

    /**
     * This function is automatically called after the "open" function
     * Use the PHP default "read" function, then save the data and close the session if the session has not to be locked
     *
     * @param string $session_id
     *
     * @return string
     */
    public function read($session_id)
    {
        $data = parent::read($session_id);
        $this->session = $this->unserialize_session_data($data);
        if(!$this->lock) {
            $_SESSION = $this->session;
            session_write_close();
        }
        return $data;
    }

    /**
     * This function must be called when the session (which is closed) need to be checked and written
     * Compare differences between the saved session and the new content of $_SESSION
     * - if there is no differences, no need to reopen and save the session
     * - else the session is reopen and reread then the sessions are compared and merged
     */
    public function writeIfSessionChanged()
    {
        if($this->session === null) {
            return;
        }

        $currentSession = $_SESSION;
        $oldSession = $this->session;

        if($currentSession === array()) {
            $this->lock = true;
            ob_start();
            session_start();
            ob_clean();
            session_destroy();
            $this->lock = false;
            return;
        }

        $needWrite = !$this->array_compare_recursive($oldSession, $currentSession);
        $needWrite = $needWrite || !$this->array_compare_recursive($currentSession, $oldSession);

        if($needWrite) {
            $this->lock = true;
            //We need @session_start() because we can't send session cookie more then once.
            @session_start();
            $sameOldAndNew = $this->array_compare_recursive($_SESSION, $oldSession);
            $sameOldAndNew = $sameOldAndNew || $this->array_compare_recursive($oldSession, $_SESSION);

            if($sameOldAndNew) {
                $_SESSION = $currentSession;
            } else {
                $keys = array_keys(array_merge($_SESSION, $currentSession, $oldSession));

                foreach ($keys as $key) {
                    $base = isset($oldSession[$key])?:null;
                    $mine = isset($currentSession[$key])?:null;
                    $theirs = isset($_SESSION[$key])?:null;
                    if ($base != $mine && $base != $theirs && $mine != $theirs) {
                        foreach($this->conflictRules as $regex => $conflictRule){
                            if(preg_match($regex, $key)){
                                if ($conflictRule == 1) {
                                    $_SESSION[$key] = $mine;
                                } elseif ($conflictRule == -1) {
                                    $_SESSION[$key] = $theirs;
                                } elseif ($conflictRule == 0){
                                    throw new \Exception('Your session conflicts with a session change in another process on key "'.$key.'"');
                                }
                            }
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
     * Convert the serialized string which represent the session to an array.
     *
     * @param string $serialized_string
     *
     * @return array
     */
    public function unserialize_session_data($serialized_string)
    {
        $variables = array();
        $a = preg_split( "/(\w+)\|/", $serialized_string, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );

        for( $i = 0; $i<count($a); $i = $i+2 )
        {
            if(isset($a[$i+1]))
            {
                $variables[$a[$i]] = unserialize( $a[$i+1] );
            }
        }
        return($variables);
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
        foreach($array1 as $key => $value)
        {
            if(is_array($value))
            {
                if(!isset($array2[$key]) || !is_array($array2[$key]))
                {
                    return false;
                }
                else
                {
                    if(!$this->array_compare_recursive($value, $array2[$key])) {
                        return false;
                    }
                }
            }
            elseif(!isset($array2[$key]) || $array2[$key] != $value)
            {
                return false;
            }
        }
        return true;
    }
}