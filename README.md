[![Latest Stable Version](https://poser.pugx.org/mouf/utils.session.optimistic-session-handler/v/stable)](https://packagist.org/packages/mouf/utils.session.optimistic-session-handler)
[![Latest Unstable Version](https://poser.pugx.org/mouf/utils.session.optimistic-session-handler/v/unstable)](https://packagist.org/packages/mouf/utils.session.optimistic-session-handler)
[![License](https://poser.pugx.org/mouf/utils.session.optimistic-session-handler/license)](https://packagist.org/packages/mouf/utils.session.optimistic-session-handler)
[![Build Status](https://travis-ci.org/thecodingmachine/utils.session.optimistic-session-handler.svg?branch=1.0)](https://travis-ci.org/thecodingmachine/utils.session.optimistic-session-handler)


# OptimisticSessionHandler
File-based session handler that **releases session lock quickly**. Useful to speed up multiple Ajax calls on the same page.

## Why do we need this session handler?
It improves performances in case of projects with multiple and long Ajax requests.
Several requests using the user session can be concurrently executed by the server since the session is not locked for a long time.

By default, PHP writes session files on the disk. When you execute `session_start`, the session file is opened, and a lock is 
put on the file. If another process tries to perform a `session_start`, the process will wait until the lock on the session file is released.
This is a security feature of PHP (2 processes cannot modify the same session at the same time), but this is dreadful for performances,
as PHP requests sharing the same session must be run sequentially.

This package offers a way around this problem. It assumes that *everything is going to be alright* (hence the "optimistic" name),
and let several processes access the session at the same time. If two processes modify the session at the same time, it will
try to merge the 2 results. If it fails to do so, it will throw an exception.

## How does it work?
This session handler modifies the default behaviour of PHP session handling.
Sessions are still written to disk (like PHP does by default).
The session is opened and read when you call `session_start()` to fill the global variable `$_SESSION`.
But the session is closed immediately after.
At the end of your PHP script, the `$_SESSION` is compared with the old session. If the session has been modified in your script,
the handler re-opens a session and compare the new session with your changes. The merged session is saved.

*Note:* if you use an alternative session handler (like APC or Memcache), do not use this session handler. It is designed to be
used with file based sessions.

## Using the session handler
It's extremely easy to use.
Just declare a new instance :

    $handler = new OptimisticSessionHandler();

And save it as your default session handler :

    session_set_save_handler($handler, true);

Then you can start the session as usual

    session_start();

Then the `$_SESSION` array is accessible.

You can configure rules for managing conflicts. Just add element to the class parameter $conflictRules.
The possible rules are:

* IGNORE => Don't use the current change.
* OVERWRITE => Use the current change.
* FAIL => Throw exception.

So you can just declare a new instance like this:

    $handler = new OptimisticSessionHandler(array("key_to_override" => OptimisticSessionHandler::OVERRIDE));

### Destroying the session
**Warning:** The session can't be destroyed by `session_destroy()` (It will throw an error). To destroy the session, you must empty the `$_SESSION` array.

    $_SESSION = array();

If you want more information about this package you can go on [OptimisticSessionHandler: A New Way To Think PHP Sessions](http://www.thecodingmachine.com/optimisticsessionhandler-a-new-way-to-think-php-sessions/)