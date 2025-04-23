<?php

namespace DeepSeek;

use DeepSeek\Contracts\ClientContract;
use DeepSeek\Contracts\Models\ResultContract;
use DeepSeek\Enums\Requests\ClientTypes;
use DeepSeek\Enums\Requests\EndpointSuffixes;
use DeepSeek\Resources\Chat;
use DeepSeek\Resources\Resource;
use Generator;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\StreamInterface;
use DeepSeek\Factories\ApiFactory;
use DeepSeek\Enums\Queries\QueryRoles;
use DeepSeek\Enums\Requests\QueryFlags;
use DeepSeek\Enums\Configs\TemperatureValues;
use DeepSeek\Traits\Resources\{HasChat, HasCoder};
use Illuminate\Support\Str;
use RuntimeException;

class DeepSeekClient implements ClientContract
{
    use HasChat, HasCoder;

    /**
     * PSR-18 HTTP client for making requests.
     *
     * @var ClientInterface
     */
    protected ClientInterface $httpClient;

    /**
     * Array to store accumulated queries.
     *
     * @var array
     */
    protected array $queries = [];

    /**
     * The model being used for API requests.
     *
     * @var string|null
     */
    protected ?string $model;

    /**
     * Indicates whether to enable streaming for API responses.
     *
     * @var bool
     */
    protected bool $stream;

    protected float $temperature;

    /**
     * response result contract
     * @var ResultContract
     */
    protected ResultContract $result;

    protected string $requestMethod;

    protected ?string $endpointSuffixes;

    /**
     * Initialize the DeepSeekClient with a PSR-compliant HTTP client.
     *
     * @param ClientInterface $httpClient The HTTP client used for making API requests.
     */
    public function __construct(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->model = null;
        $this->stream = false;
        $this->requestMethod = 'POST';
        $this->endpointSuffixes = EndpointSuffixes::CHAT->value;
        $this->temperature = (float) TemperatureValues::GENERAL_CONVERSATION->value;
    }

    /**
     * Initiates a streaming request using the modified resource method
     * and yields Server-Sent Event data chunks.
     *
     * @return Generator Yields string data parts (JSON chunk or '[DONE]').
     * @throws RuntimeException If the HTTP request fails or the stream cannot be read.
     */
    public function stream(): Generator
    {
        $this->stream = true;
        $requestDataPayload = [
            QueryFlags::MESSAGES->value => $this->queries,
            QueryFlags::MODEL->value    => $this->model,
            QueryFlags::STREAM->value   => $this->stream,
            QueryFlags::TEMPERATURE->value => $this->temperature,
        ];
        $this->queries = [];

        $resource = new Chat($this->httpClient);

        $psr7Response = $resource->sendStreamRequest($requestDataPayload, $this->requestMethod);

        if ($psr7Response->getStatusCode() >= 300) {
            $errorBody = $psr7Response->getBody()->getContents(); // Read error body
            \Log::error('DeepSeek API returned error status in stream', [
                'status_code' => $psr7Response->getStatusCode(), 'error_body' => $errorBody
            ]);
            throw new RuntimeException('DeepSeek API stream request failed: HTTP ' . $psr7Response->getStatusCode());
        }

        $bodyStream = $psr7Response->getBody();

        yield from $this->yieldChunksFromStream($bodyStream);
    }

    public function run(): string
    {
        $requestData = [
            QueryFlags::MESSAGES->value => $this->queries,
            QueryFlags::MODEL->value    => $this->model,
            QueryFlags::STREAM->value   => $this->stream,
            QueryFlags::TEMPERATURE->value   => $this->temperature,
        ];
        // Clear queries after sending
        $this->queries = [];
        $this->setResult((new Resource($this->httpClient, $this->endpointSuffixes))->sendRequest($requestData, $this->requestMethod));
        return $this->getResult()->getContent();
    }

    /**
     * Create a new DeepSeekClient instance with the given API key.
     *
     * @param string $apiKey The API key for authentication.
     * @param string|null $baseUrl The base URL for the API (optional).
     * @param int|null $timeout The timeout duration for requests in seconds (optional).
     * @return self A new instance of the DeepSeekClient.
     */
    public static function build(string $apiKey, ?string $baseUrl = null, ?int $timeout = null, ?string $clientType = null): self
    {
        $clientType = $clientType ?? ClientTypes::GUZZLE->value;

        $httpClient = ApiFactory::build()
            ->setBaseUri($baseUrl)
            ->setTimeout($timeout)
            ->setKey($apiKey)
            ->run($clientType);

        return new self($httpClient);
    }

    /**
     * Add a query to the accumulated queries list.
     *
     * @param string $content
     * @param string|null $role
     * @return self The current instance for method chaining.
     */
    public function query(string $content, ?string $role = "user"): self
    {
        $this->queries[] = $this->buildQuery($content, $role);
        return $this;
    }

    /**
     * get list of available models .
     *
     * @return self The current instance for method chaining.
     */
    public function getModelsList(): self
    {
        $this->endpointSuffixes = EndpointSuffixes::MODELS_LIST->value;
        $this->requestMethod = 'GET';
        return $this;
    }

    /**
     * Set the model to be used for API requests.
     *
     * @param string|null $model The model name (optional).
     * @return self The current instance for method chaining.
     */
    public function withModel(?string $model = null): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Enable or disable streaming for API responses.
     *
     * @param bool $stream Whether to enable streaming (default: true).
     * @return self The current instance for method chaining.
     */
    public function withStream(bool $stream = true): self
    {
        $this->stream = $stream;
        return $this;
    }

    public function setTemperature(float $temperature): self
    {
        $this->temperature = $temperature;
        return $this;
    }

    public function buildQuery(string $content, ?string $role = null): array
    {
        return [
            'role' => $role ?: QueryRoles::USER->value,
            'content' => $content
        ];
    }

    /**
     * set result model
     * @param \DeepseekPhp\Contracts\Models\ResultContract $result
     * @return self The current instance for method chaining.
     */
    public function setResult(ResultContract $result)
    {
        $this->result = $result;
        return $this;
    }

    /**
     * response result model
     * @return \DeepSeek\Contracts\Models\ResultContract
     */
    public function getResult(): ResultContract
    {
        return $this->result;
    }

    private function yieldChunksFromStream(StreamInterface $bodyStream): Generator
    {
        $buffer = '';
        $eventSeparator = "\n\n";

        while (!$bodyStream->eof()) {
            try {
                $chunk = $bodyStream->read(4096);
                if ($chunk === '') {
                    continue;
                }

                $buffer .= $chunk;

                while (($pos = strpos($buffer, $eventSeparator)) !== false) {
                    $messageBlock = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + strlen($eventSeparator));

                    $lines = explode("\n", $messageBlock);
                    foreach ($lines as $line) {
                        if (Str::startsWith($line, 'data:')) {
                            $dataPart = trim(Str::after($line, 'data:'));

                            yield $dataPart;

                            if ($dataPart === '[DONE]') {
                                $bodyStream->close();
                                return;
                            }
                        }
                    }
                }

            } catch (\RuntimeException $e) {
                \Log::error('Error reading from stream.', ['error' => $e->getMessage()]);
                break;
            }
        }

        if (!empty($buffer)) {
            \Log::warning('Stream ended with unprocessed data in buffer.', [
                'buffer_length' => strlen($buffer),
                'buffer_start' => substr($buffer, 0, 100)
            ]);
        }

        if ($bodyStream->isReadable()) {
            $bodyStream->close();
        }
    }
}
