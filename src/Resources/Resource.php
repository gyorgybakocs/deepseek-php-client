<?php

declare(strict_types=1);

namespace DeepseekPhp\Resources;

use DeepseekPhp\Contracts\Resources\ResourceContract;
use DeepseekPhp\Enums\Configs\DefaultConfigs;
use DeepseekPhp\Enums\Models;
use DeepseekPhp\Enums\Data\DataTypes;
use DeepseekPhp\Enums\Requests\EndpointSuffixes;
use DeepseekPhp\Enums\Requests\QueryFlags;
use DeepseekPhp\Traits\Queries\HasQueryParams;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\ClientInterface;

class Resource implements ResourceContract
{
    use HasQueryParams;

    /**
     * HTTP client for making requests.
     *
     * @var ClientInterface
     */
    protected ClientInterface $client;

    /**
     * Initialize the Resource with a Guzzle HTTP client.
     *
     * @param ClientInterface $client The HTTP client instance for making requests.
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Send a request to the API endpoint.
     *
     * This method sends a POST request to the API endpoint, including the query data
     * and custom headers, and returns the response body as a string.
     *
     * @param array $requestData The data to send in the request.
     * @return string The response body.
     *
     * @throws \RuntimeException If the request fails.
     */
    public function sendRequest(array $requestData): string
    {
        try {
            $response = $this->client->post($this->getEndpointSuffix(), [
                'json' => $this->resolveHeaders($requestData),
            ]);

            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Deepseek API request failed: " . $e->getMessage());
        }
    }

    /**
     * Merge request data with default headers.
     *
     * This method merges the given query data with custom headers that are
     * prepared for the request.
     *
     * @param array $requestData The data to send in the request.
     * @return array The merged request data with default headers.
     */
    protected function resolveHeaders(array $requestData): array
    {
        return array_merge($requestData, $this->prepareCustomHeaderParams($requestData));
    }

    /**
     * Prepare the custom headers for the request.
     *
     * This method loops through the query parameters and applies the appropriate
     * type conversion before returning the final headers.
     *
     * @param array $query The data to send in the request.
     * @return array The custom headers for the request.
     */
    public function prepareCustomHeaderParams(array $query): array
    {
        $headers = [];
        $params = $this->getAllowedQueryParamsList();

        // Loop through the parameters and apply the conversion logic dynamically
        foreach ($params as $key => $type) {
            $headers[$key] = $this->getQueryParam($query, $key, $this->getDefaultForKey($key), $type);
        }

        return $headers;
    }

    /**
     * Get the endpoint suffix for the resource.
     *
     * This method returns the endpoint suffix that is used in the API URL.
     *
     * @return string The endpoint suffix.
     */
    public function getEndpointSuffix(): string
    {
        return EndpointSuffixes::CHAT->value;
    }

    /**
     * Get the model associated with the resource.
     *
     * This method returns the default model value associated with the resource.
     *
     * @return string The default model value.
     */
    public function getDefaultModel(): string
    {
        return Models::CHAT->value;
    }

    /**
     * Check if stream is enabled or not.
     *
     * This method checks whether the streaming option is enabled based on the
     * default configuration.
     *
     * @return bool True if streaming is enabled, false otherwise.
     */
    public function getDefaultStream(): bool
    {
        return DefaultConfigs::STREAM->value === 'true';
    }

    /**
     * Get the list of query parameters and their corresponding types for conversion.
     *
     * This method returns an array of query keys and their associated data types
     * for use in preparing the custom headers.
     *
     * @return array An associative array of query keys and their data types.
     */
    protected function getAllowedQueryParamsList(): array
    {
        return [
            QueryFlags::MODEL->value => DataTypes::STRING->value,
            QueryFlags::STREAM->value => DataTypes::BOOL->value,
        ];
    }
}
