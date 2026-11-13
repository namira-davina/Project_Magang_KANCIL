<?php

namespace App\Services\Bot;

/**
 * Satu pesan balasan bot beserta ID pesan/FAQ untuk audit (mis. ['M-00'], ['PSP-001']).
 */
final class BotReply
{
    /**
     * @param  list<string>  $codes
     */
    public function __construct(
        public readonly string $text,
        public readonly array $codes = [],
        public readonly ?int $faqId = null,
        public readonly string $sender = 'bot',
    ) {}
}
