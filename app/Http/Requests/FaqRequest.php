<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kategori' => ['required', 'exists:faq_categories,id'],
            'status' => ['required', 'in:draft,perlu_verifikasi,terverifikasi'],
            'pertanyaan' => ['required', 'string', 'max:255'],
            'kata_kunci' => ['required', 'string'],
            'jawaban' => ['required', 'string', 'max:1600'],
            'aktif' => ['nullable', 'boolean'],
            'tautan' => ['nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'kategori.required' => 'Kategori layanan wajib dipilih.',
            'pertanyaan.required' => 'Pertanyaan wajib diisi.',
            'kata_kunci.required' => 'Kata kunci wajib diisi.',
            'jawaban.required' => 'Jawaban wajib diisi.',
            'tautan.url' => 'Tautan rujukan harus berupa URL yang valid.',
        ];
    }
}
