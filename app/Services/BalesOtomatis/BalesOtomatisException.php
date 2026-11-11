<?php

namespace App\Services\BalesOtomatis;

use RuntimeException;

class BalesOtomatisException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $apiCode = null, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
