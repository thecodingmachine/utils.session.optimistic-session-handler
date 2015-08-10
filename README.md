# OptimisticSessionHandler
Session handler that **releases session lock quickly**. Usefull for multiple ajax calls on the same page

## Why do we need this session handler?
It improves performances in case of projects with multiple and long requests.
Several requests using the user session can be concurrently executed by the server since the session are not locked for a long time.

## How does it work?
The session is openned and read when you call `session_start()` to fill the global variable `$_SESSION`.
But the session is closed immediatly after.
At the end of your script PHP, the `$_SESSION` is compared with the old session. If the session has been modified in your script,
the handler re-opens a session and compare the new session with your changes. The merged session is saved..

## Using the session handler
It's extremely easy to use.
Just declare a new instance :

    $handler = new OptimisticSessionHandler();

And save it as your default session handler :

    session_set_save_handler($handler, true);

Then you can start the session as usual

    session_start();

Then the `$_SESSION` array is accessible.

### Destroying the session
**Warning:** The session can't be destroyed by `session_destroy()` (It will throw an error). To destroy the session empty the `$_SESSION` array.

    $_SESSION = array();
