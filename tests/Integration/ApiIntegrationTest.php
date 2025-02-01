<?php

namespace Tests\Integration;

use DeepSeek\DeepSeekClient;
use DeepSeek\Tests\Helpers\TestCase;

beforeEach(function () {
    // Load environment variables
    if (!getenv('DEEPSEEK_API_KEY')) {
        throw new \Exception('DEEPSEEK_API_KEY environment variable is not set.');
    }

    $this->client = new DeepSeekClient(getenv('DEEPSEEK_API_KEY'));
});

it('fetches data from the API', function () {
    $response = $this->client->get('/some-endpoint');
    expect($response)->toBeArray();
    expect($response)->toHaveKey('data');
});
