<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Services\Bot\BotScript;
use App\Services\ComplaintService;
use App\Services\Whatsapp\WhatsappMessenger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengaduanController extends Controller
{
    public function __construct(private readonly ComplaintService $complaints) {}

    public function index(Request $request)
    {
        $items = $this->visible($request)
            ->with('category:id,name')
            ->orderByDesc('submitted_at')->orderByDesc('id')
            ->get();

        $byStatus = $items->countBy('status');

        return view('pengaduan.index', [
            'complaints' => $items->map(fn (Complaint $c) => $this->present($c))->values(),
            'categories' => ComplaintCategory::where('is_active', true)->orderBy('id')->pluck('name'),
            'stats' => [
                'total' => $items->count(),
                'menunggu' => (int) ($byStatus['Menunggu'] ?? 0),
                'diproses' => (int) ($byStatus['Diproses'] ?? 0),
                'selesai' => (int) ($byStatus['Selesai'] ?? 0),
            ],
            'canUpdate' => $request->user()->hasRole('admin', 'pimpinan'),
        ]);
    }

    /** Simpan status + tanggapan; tanggapan dikirim ke WhatsApp pelapor. */
    public function update(Request $request, Complaint $complaint, WhatsappMessenger $messenger): JsonResponse
    {
        abort_unless($this->visible($request)->whereKey($complaint->id)->exists(), 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['Menunggu', 'Diproses', 'Selesai'])],
            'tanggapan' => ['nullable', 'string', 'max:2000'],
        ], [
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status tidak valid.',
            'tanggapan.max' => 'Tanggapan maksimal 2.000 karakter.',
        ]);

        $response = trim((string) ($validated['tanggapan'] ?? ''));
        $status = $validated['status'];

        $complaint->fill([
            'status' => $status,
            'assigned_to' => $complaint->assigned_to ?: $request->user()->id,
            'response_text' => $response !== '' ? $response : $complaint->response_text,
            'resolved_at' => $status === 'Selesai' ? ($complaint->resolved_at ?? now()) : null,
        ]);

        if ($status !== 'Menunggu' || $response !== '') {
            $complaint->first_response_at ??= now();
        }

        $complaint->save();

        $notified = false;
        $conversation = $complaint->conversation;

        if ($response !== '' && $conversation && $conversation->channel === 'whatsapp') {
            $label = Complaint::STATUS_LABEL_FOR_USER[$status] ?? $status;
            $messenger->queue(
                $conversation,
                "Tanggapan atas pengaduan *{$complaint->ticket_number}* (status: *{$label}*):\n{$response}",
                'petugas',
                $request->user()->id,
                meta: ['codes' => ['PGD-10'], 'complaint_id' => $complaint->id],
            );
            $notified = true;
        }

        return response()->json([
            'complaint' => $this->present($complaint->fresh('category')),
            'notified' => $notified,
        ]);
    }

    /** Petugas tidak melihat pengaduan kategori 3 (pelayanan petugas); itu untuk Admin/Pimpinan. */
    private function visible(Request $request): Builder
    {
        $query = Complaint::query();

        if ($request->user()->hasRole('petugas')) {
            $category = ComplaintCategory::where('name', BotScript::COMPLAINT_CATEGORIES['3'][0])->value('id');
            $query->where(fn ($q) => $q->whereNull('category_id')->orWhere('category_id', '!=', $category ?? 0));
        }

        return $query;
    }

    private function present(Complaint $c): array
    {
        $submitted = $c->submitted_at;

        return [
            'id' => $c->id,
            'tiket' => '#'.$c->ticket_number,
            'nama' => $c->user_name,
            'nomor' => PercakapanController::formatPhone((string) $c->whatsapp_number),
            'kategori' => $c->category?->name ?? 'Tanpa Kategori',
            'tanggal' => $submitted ? ($submitted->isToday() ? 'Hari ini, '.$submitted->format('H:i')
                : ($submitted->isYesterday() ? 'Kemarin, '.$submitted->format('H:i') : $submitted->translatedFormat('d M Y, H:i'))) : '-',
            'isi' => $c->complaint_text,
            'status' => $c->status,
            'tanggapan' => $c->response_text,
            'prioritas' => $c->priority,
            'level' => (int) $c->escalation_level,
            'terlambat' => $this->complaints->isOverdue($c),
            'lampiran' => count($c->attachments ?? []),
            'percakapan_url' => $c->conversation_id ? route('percakapan', ['id' => $c->conversation_id]) : null,
            'update_url' => route('pengaduan.update', $c),
        ];
    }
}
