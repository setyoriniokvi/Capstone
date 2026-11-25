<?php

namespace App\Http\Validation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Isian milestone (form milestone di halaman periode, juga milestone draf di halaman Periode baru). */
final class MilestoneInput
{
    /**
     * Validasi & rapikan isian milestone; hasilnya siap disimpan ke tabel milestones.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validate(array $input): array
    {
        // Jenis "dokumen" → kind dokumen; jenis "lainnya" → tahapan non-dokumen (registrasi tim, sidang, dsb.)
        if (($input['category'] ?? null) === 'dokumen') {
            $input['kind'] = 'dokumen';
        }
        $data = Validator::make($input, [
            'category' => ['required', Rule::in(array_keys(config('capstone.milestone_categories')))],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['required', Rule::in(array_keys(config('capstone.milestone_kinds'))), Rule::when(($input['category'] ?? null) === 'lainnya', [Rule::notIn(['dokumen'])])],
            'document_type' => ['nullable', 'required_if:kind,dokumen', Rule::in(array_keys(config('capstone.document_types')))],
            'starts_at' => ['required', 'date'],
            'due_at' => ['required', 'date', 'after:starts_at'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
            'allowed_types' => ['nullable', 'required_if:kind,dokumen', 'array'],
            'allowed_types.*' => [Rule::in(array_keys(config('capstone.document_file_types')))],
        ], [
            'allowed_types.required_if' => 'Pilih minimal satu tipe file untuk milestone pengumpulan dokumen.',
            'document_type.required_if' => 'Pilih jenis dokumen untuk milestone pengumpulan dokumen.',
            'due_at.after' => 'Deadline harus setelah waktu mulai.',
        ])->validate();

        unset($data['category']);
        $isDocument = $data['kind'] === 'dokumen';
        $data['document_type'] = $isDocument ? $data['document_type'] : null;
        $data['allowed_types'] = $isDocument ? array_values($data['allowed_types'] ?? []) : null;
        $data['sort_order'] ??= 0;

        return $data;
    }
}
