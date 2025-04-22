<?php

namespace DeepSeek\Contracts;

use Generator;

interface ClientContract
{
    public function stream(): Generator;
    public function run(): string;
    public static function build(string $apiKey, ?string $baseUrl = null, ?int $timeout = null): self;
    public function query(string $content, ?string $role = "user"): self;
    public function getModelsList(): self;
    public function withModel(?string $model = null): self;
    public function withStream(bool $stream = true): self;
    public function buildQuery(string $content, ?string $role = null): array;
}
