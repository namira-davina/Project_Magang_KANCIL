<?php

namespace App\Services\Llm;

final class LlmAnswer
{
    /**
     * @param  list<string>  $faqCodes  kode FAQ sumber jawaban (sudah divalidasi)
     * @param  list<int>  $faqIds
     */
    public function __construct(
        public readonly bool $confident,
        public readonly string $text = '',
        public readonly array $faqCodes = [],
        public readonly array $faqIds = [],
        public readonly bool $usedUnverified = false,
    ) {}

    public function primaryFaqId(): ?int
    {
        return $this->faqIds[0] ?? null;
    }
}
