<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FaqController extends Controller
{
    // Menampilkan daftar FAQ
    public function index()
    {
        $faqs = DB::table('faqs')
            ->leftJoin('faq_categories', 'faqs.category_id', '=', 'faq_categories.id')
            ->select('faqs.*', 'faq_categories.name as category_name')
            ->orderByDesc('faqs.created_at')
            ->get();

        $categories = DB::table('faq_categories')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('faq.index', compact('faqs', 'categories'));
    }

    // Menampilkan form tambah FAQ
    public function create()
    {
        $categories = DB::table('faq_categories')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('faq.create', compact('categories'));
    }

    // Menyimpan FAQ baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kategori' => ['required', 'exists:faq_categories,id'],
            'status' => ['required', 'in:draft,perlu_verifikasi,terverifikasi'],
            'pertanyaan' => ['required', 'string', 'max:255'],
            'kata_kunci' => ['required', 'string'],
            'jawaban' => ['required', 'string', 'max:1000'],
            'tautan' => ['nullable', 'url', 'max:255'],
        ], [
            'kategori.required' => 'Kategori layanan wajib dipilih.',
            'pertanyaan.required' => 'Pertanyaan wajib diisi.',
            'kata_kunci.required' => 'Kata kunci wajib diisi.',
            'jawaban.required' => 'Jawaban wajib diisi.',
            'tautan.url' => 'Tautan rujukan harus berupa URL yang valid.',
        ]);

        $faqId = DB::transaction(function () use ($validated) {
            $isVerified = $validated['status'] === 'terverifikasi';

            $faqId = DB::table('faqs')->insertGetId([
                'category_id' => $validated['kategori'],
                'question' => $validated['pertanyaan'],
                'answer' => $validated['jawaban'],
                'source' => $validated['tautan'] ?? null,
                'source_url' => $validated['tautan'] ?? null,
                'verification_status' => $validated['status'],
                'verified_by' => $isVerified ? Auth::id() : null,
                'verified_at' => $isVerified ? now() : null,
                // FAQ baru tidak langsung dipublikasikan.
                'is_active' => 0,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $keywords = collect(explode(',', $validated['kata_kunci']))
                ->map(fn ($keyword) => trim($keyword))
                ->filter()
                ->unique()
                ->values();

            foreach ($keywords as $keyword) {
                DB::table('faq_keywords')->insert([
                    'faq_id' => $faqId,
                    'keyword' => $keyword,
                    'created_at' => now(),
                ]);
            }

            return $faqId;
        });

        return redirect()
            ->route('faq.index')
            ->with('success', 'FAQ berhasil ditambahkan. FAQ belum aktif dan perlu diaktifkan setelah pemeriksaan.');
    }

    // Menampilkan form edit FAQ
    public function edit($id)
    {
        $faq = DB::table('faqs')->where('id', $id)->first();

        abort_if(!$faq, 404);

        $categories = DB::table('faq_categories')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $keywords = DB::table('faq_keywords')
            ->where('faq_id', $id)
            ->pluck('keyword')
            ->implode(', ');

        return view('faq.edit', compact('faq', 'categories', 'keywords'));
    }

    // Memperbarui FAQ
    public function update(Request $request, $id)
    {
        $faq = DB::table('faqs')->where('id', $id)->first();

        abort_if(!$faq, 404);

        $validated = $request->validate([
            'kategori' => ['required', 'exists:faq_categories,id'],
            'status' => ['required', 'in:draft,perlu_verifikasi,terverifikasi'],
            'pertanyaan' => ['required', 'string', 'max:255'],
            'kata_kunci' => ['required', 'string'],
            'jawaban' => ['required', 'string', 'max:1000'],
            'tautan' => ['nullable', 'url', 'max:255'],
        ]);

        DB::transaction(function () use ($validated, $id, $faq) {
            $isVerified = $validated['status'] === 'terverifikasi';

            DB::table('faqs')->where('id', $id)->update([
                'category_id' => $validated['kategori'],
                'question' => $validated['pertanyaan'],
                'answer' => $validated['jawaban'],
                'source' => $validated['tautan'] ?? null,
                'source_url' => $validated['tautan'] ?? null,
                'verification_status' => $validated['status'],
                'verified_by' => $isVerified ? Auth::id() : null,
                'verified_at' => $isVerified ? ($faq->verified_at ?? now()) : null,
                // Jika belum terverifikasi, FAQ otomatis tidak aktif.
                'is_active' => $isVerified ? $faq->is_active : 0,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);

            DB::table('faq_keywords')->where('faq_id', $id)->delete();

            $keywords = collect(explode(',', $validated['kata_kunci']))
                ->map(fn ($keyword) => trim($keyword))
                ->filter()
                ->unique()
                ->values();

            foreach ($keywords as $keyword) {
                DB::table('faq_keywords')->insert([
                    'faq_id' => $id,
                    'keyword' => $keyword,
                    'created_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('faq.index')
            ->with('success', 'Data FAQ berhasil diperbarui.');
    }

    // Menghapus FAQ
    public function destroy($id)
    {
        $faq = DB::table('faqs')->where('id', $id)->first();

        abort_if(!$faq, 404);

        DB::transaction(function () use ($id) {
            // Lepaskan relasi pesan sebelum FAQ dihapus.
            DB::table('messages')
                ->where('faq_id', $id)
                ->update(['faq_id' => null]);

            DB::table('faq_keywords')
                ->where('faq_id', $id)
                ->delete();

            DB::table('faqs')
                ->where('id', $id)
                ->delete();
        });

        return redirect()
            ->route('faq.index')
            ->with('success', 'FAQ berhasil dihapus.');
    }
}
