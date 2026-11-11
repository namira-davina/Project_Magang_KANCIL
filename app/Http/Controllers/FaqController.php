<?php

namespace App\Http\Controllers;

use App\Http\Requests\FaqRequest;
use App\Models\FaqCategory;
use App\Services\FaqService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FaqController extends Controller
{
    public function __construct(private readonly FaqService $faqs) {}

    // Menampilkan daftar FAQ
    public function index()
    {
        $faqs = DB::table('faqs')
            ->leftJoin('faq_categories', 'faqs.category_id', '=', 'faq_categories.id')
            ->select('faqs.*', 'faq_categories.name as category_name')
            ->orderByDesc('faqs.created_at')
            ->get();

        $categories = FaqCategory::active()->orderBy('name')->get();

        return view('faq.index', compact('faqs', 'categories'));
    }

    // Menampilkan form tambah FAQ
    public function create()
    {
        $categories = FaqCategory::active()->orderBy('name')->get();

        return view('faq.create', compact('categories'));
    }

    // Menyimpan FAQ baru
    public function store(FaqRequest $request)
    {
        $this->faqs->create($request->validated(), Auth::id());

        return redirect()
            ->route('faq.index')
            ->with('success', 'FAQ berhasil ditambahkan. FAQ belum aktif dan perlu diaktifkan setelah pemeriksaan.');
    }

    // Menampilkan form edit FAQ
    public function edit($id)
    {
        $faq = $this->findOrFail($id);

        $categories = FaqCategory::active()->orderBy('name')->get();

        $keywords = DB::table('faq_keywords')
            ->where('faq_id', $id)
            ->pluck('keyword')
            ->implode(', ');

        return view('faq.edit', compact('faq', 'categories', 'keywords'));
    }

    // Memperbarui FAQ
    public function update(FaqRequest $request, $id)
    {
        $this->faqs->update($this->findOrFail($id), $request->validated(), Auth::id());

        return redirect()
            ->route('faq.index')
            ->with('success', 'Data FAQ berhasil diperbarui.');
    }

    // Menghapus FAQ
    public function destroy($id)
    {
        $this->findOrFail($id);
        $this->faqs->delete($id);

        return redirect()
            ->route('faq.index')
            ->with('success', 'FAQ berhasil dihapus.');
    }

    private function findOrFail($id): object
    {
        $faq = DB::table('faqs')->where('id', $id)->first();

        abort_if(! $faq, 404);

        return $faq;
    }
}
