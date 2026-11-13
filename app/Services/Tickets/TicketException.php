<?php

namespace App\Services\Tickets;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Aksi tiket ditolak; $status = kode HTTP untuk dashboard (409 bentrok, 422 tiket sudah ditutup). */
class TicketException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function takenByOther(?string $name): self
    {
        return new self('Percakapan ini sedang ditangani petugas lain'.($name ? " ({$name})" : '').'.', 409);
    }

    /** Dashboard memanggil via fetch (JSON); form biasa kembali dengan pesan galat. */
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $this->getMessage()], $this->status)
            : back()->with('error', $this->getMessage());
    }

    public static function closed(): self
    {
        return new self('Tiket sudah ditutup.', 422);
    }
}
