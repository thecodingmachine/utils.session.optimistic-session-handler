<?php

namespace Mouf\Utils\Session\SessionHandler;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use PHPUnit\Framework\TestCase;

class OptimisticSessionHandlerTest extends TestCase
{
    public function testSimpleChange()
    {
        global $root_url;

        $client = new Client(['base_uri' => 'http://localhost' . $root_url, 'cookies' => true]);

        // First request to start the session:
        $response = $client->get('tests/fixtures/set_value.php?a=1');
        $body = (string)$response->getBody();

        $this->assertEmpty($body);

        $response = $client->get('tests/fixtures/get_values.php');
        $body = (string)$response->getBody();

        $this->assertStringContainsString('a=1', $body);
    }

    public function testTwoSimultaneousNonConflictingChanges()
    {
        global $root_url;

        $client = new Client(['base_uri' => 'http://localhost' . $root_url, 'cookies' => true]);

        // First request to start the session:
        $response = $client->get('tests/fixtures/set_value.php?a=1');
        $body = (string)$response->getBody();

        $this->assertEmpty($body);

        // Initiate each request but do not block
        $promises = [
            'a' => $client->getAsync('tests/fixtures/set_value.php?a=42&waitBeforeQuit=1'),
            'b' => $client->getAsync('tests/fixtures/set_value.php?b=24&waitBeforeQuit=1'),
        ];

        // Wait on all of the requests to complete.
        $results = Promise\unwrap($promises);

        $this->assertEmpty((string)$results['a']->getBody());
        $this->assertEmpty((string)$results['b']->getBody());

        $response = $client->get('tests/fixtures/get_values.php');
        $body = (string)$response->getBody();

        $this->assertStringContainsString('a=42', $body);
        $this->assertStringContainsString('b=24', $body);
    }

    public function testTwoSessionStarts()
    {
        global $root_url;

        $client = new Client(['base_uri' => 'http://localhost' . $root_url, 'cookies' => true]);

        // First request to start the session:
        $response = $client->get('tests/fixtures/set_value.php?a=1');
        $body = (string)$response->getBody();

        $this->assertEmpty($body);

        // Perform 2 session starts :
        $response = $client->get('tests/fixtures/double_session_start.php?a=42');
        $body = (string)$response->getBody();

        $this->assertEmpty($body);

        $response = $client->get('tests/fixtures/get_values.php');
        $body = (string)$response->getBody();

        $this->assertStringContainsString('a=42', $body);
    }

    public function testTwoSimultaneousConflictingChanges()
    {
        global $root_url;

        $client = new Client(['base_uri' => 'http://localhost' . $root_url, 'cookies' => true]);

        // First request to start the session:
        $response = $client->get('tests/fixtures/set_value.php?a=1');
        $body = (string)$response->getBody();

        $this->assertEmpty($body);

        // Initiate each request but do not block
        $promises = [
            'a' => $client->getAsync('tests/fixtures/set_value.php?a=42&waitBeforeQuit=1'),
            'b' => $client->getAsync('tests/fixtures/set_value.php?a=24&waitBeforeQuit=2'),
        ];

        // Wait on all of the requests to complete.
        $results = Promise\unwrap($promises);

        $this->assertEmpty((string)$results['a']->getBody());
        $this->assertStringContainsString('SessionConflictException', (string)$results['b']->getBody());
    }

    public function testUnregisterSesssionHandler()
    {
        global $root_url;

        $client = new Client(['base_uri' => 'http://localhost' . $root_url, 'cookies' => true]);

        // First request to start the session:
        $response = $client->get('tests/fixtures/start-unregister-and-restart.php');
        $body = (string)$response->getBody();

        $this->assertStringContainsString('UnregisteredHandlerException', $body);
    }

    public function testReadConsistency()
    {
        global $root_url;

        $client = new Client(['base_uri' => 'http://localhost' . $root_url, 'cookies' => true]);

        // First request to start the session:
        $response = $client->get('tests/fixtures/set_value.php?a=1');
        $body = (string)$response->getBody();
        $this->assertEmpty($body);

        // Initiate each request but do not block
        $promises = [
            'firstRead'  => $client->getAsync('tests/fixtures/get_values.php?waitBeforeQuit=2'),
            'secondRead' => $client->getAsync('tests/fixtures/get_values.php?waitBeforeStart=1'),
        ];

        // Wait on all of the requests to complete.
        $results = Promise\unwrap($promises);
        $this->assertStringContainsString('a=1', (string)$results['firstRead']->getBody());
        $this->assertStringContainsString('a=1', (string)$results['secondRead']->getBody());
    }
}
