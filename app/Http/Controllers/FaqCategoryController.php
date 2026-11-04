<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FaqCategoryController extends Controller
{
    public function index()
    {
        $categories = DB::table('faq_categories')
            ->leftJoin('faqs', 'faq_categories.id', '=', 'faqs.category_id')
            ->select(
                'faq_categories.id',
                'faq_categories.name',
                'faq_categories.description',
                'faq_categories.is_active',
                DB::raw('COUNT(faqs.id) as total_faq')
            )
            ->groupBy(
                'faq_categories.id',
                'faq_categories.name',
                'faq_categories.description',
                'faq_categories.is_active'
            )
            ->orderBy('faq_categories.id')
            ->get();

        return view('kategori-faq.index', compact('categories'));
    }

    public function create()
    {
        return view('kategori-faq.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'namaKategori' => [
                'required',
                'string',
                'max:100',
                'unique:faq_categories,name',
            ],
            'deskripsiKategori' => [
                'nullable',
                'string',
            ],
            'statusKategori' => [
                'required',
                Rule::in(['Aktif', 'Nonaktif']),
            ],
        ], [
            'namaKategori.required' => 'Nama kategori wajib diisi.',
            'namaKategori.unique' => 'Nama kategori sudah digunakan.',
            'namaKategori.max' => 'Nama kategori maksimal 100 karakter.',
            'statusKategori.required' => 'Status kategori wajib dipilih.',
        ]);

        DB::table('faq_categories')->insert([
            'name' => $validated['namaKategori'],
            'description' => $validated['deskripsiKategori'] ?? null,
            'is_active' => $validated['statusKategori'] === 'Aktif' ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('kategori-faq.index')
            ->with('success', 'Kategori FAQ berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $category = DB::table('faq_categories')
            ->where('id', $id)
            ->first();

        abort_if(!$category, 404);

        $validated = $request->validate([
            'namaKategori' => [
                'required',
                'string',
                'max:100',
                Rule::unique('faq_categories', 'name')->ignore($id),
            ],
        ], [
            'namaKategori.required' => 'Nama kategori wajib diisi.',
            'namaKategori.unique' => 'Nama kategori sudah digunakan.',
            'namaKategori.max' => 'Nama kategori maksimal 100 karakter.',
        ]);

        DB::table('faq_categories')
            ->where('id', $id)
            ->update([
                'name' => $validated['namaKategori'],
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('kategori-faq.index')
            ->with('success', 'Nama kategori berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $category = DB::table('faq_categories')
            ->where('id', $id)
            ->first();

        abort_if(!$category, 404);

        DB::table('faq_categories')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('kategori-faq.index')
            ->with('success', 'Kategori FAQ berhasil dihapus.');
    }
}
