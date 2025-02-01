<?php

use DeepSeek\DeepSeekClient;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

beforeEach(function () {
    $this->mockHandler = new MockHandler();
    $this->httpClient = new HttpClient(['handler' => HandlerStack::create($this->mockHandler)]);
    $this->client = new DeepSeekClient('test-api-key', $this->httpClient);
});

it('can set API key', function () {
    expect($this->client->getApiKey())->toBe('test-api-key');
});

it('makes a GET request successfully', function () {
    $this->mockHandler->append(new \GuzzleHttp\Psr7\Response(200, [], '{"data": "success"}'));

    $response = $this->client->get('/endpoint');
    expect($response)->toBeArray();
    expect($response['data'])->toBe('success');
});
