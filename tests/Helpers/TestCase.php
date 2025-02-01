<?php

namespace DeepSeek\Tests\Helpers;

use DeepSeek\DeepSeekClient;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

class TestCase
{
    protected function createMockedClient(array $responses)
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        return new HttpClient(['handler' => $handlerStack]);
    }

    protected function createDeepSeekClient($apiKey, $httpClient = null)
    {
        return new DeepSeekClient($apiKey, $httpClient);
    }
}
