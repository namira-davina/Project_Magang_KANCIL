<?php

namespace App\Services\Bot;

use App\Models\Conversation;
use App\Models\EscalationLog;

/**
 * Mencatat log eskalasi dengan kode Matriks Eskalasi (A1–A4, B0, B1, C1–C6).
 */
class EscalationRecorder
{
    private const TYPE = [
        'A1' => 'faq_not_found',
        'A2' => 'user_request',
        'A3' => 'uncertain_answer',
        'A4' => 'user_request',
        'B0' => 'complaint',
        'B1' => 'complaint',
    ];

    public function record(
        Conversation $conversation,
        string $code,
        ?string $reason = null,
        ?int $faqId = null,
        ?string $menuPath = null,
        ?string $priority = null,
    ): EscalationLog {
        return EscalationLog::create([
            'conversation_id' => $conversation->id,
            'escalation_code' => $code,
            'escalation_type' => self::TYPE[$code] ?? 'special_case',
            'priority' => $priority ?? (BotScript::PRIORITY[$code] ?? null),
            'reason' => $reason === null ? null : mb_substr($reason, 0, 1000),
            'faq_id' => $faqId,
            'menu_path' => $menuPath,
            // B0 bukan eskalasi (pengaduan terputus), langsung ditutup.
            'status' => $code === 'B0' ? 'Selesai' : 'Menunggu',
            'escalated_at' => now(),
            'completed_at' => $code === 'B0' ? now() : null,
        ]);
    }
}
