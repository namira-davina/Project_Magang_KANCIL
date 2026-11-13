<?php

namespace App\Services\Llm;

final class LlmResponse
{
    public function __construct(
        public readonly string $content,
        public readonly ?string $model = null,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
    ) {}
}
