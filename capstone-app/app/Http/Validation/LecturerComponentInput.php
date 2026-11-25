<?php

namespace App\Http\Validation;

use App\Models\AssessmentComponent;
use App\Support\PeriodContext;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Isian bentuk penilaian dosen (nama, penilai, jenis dokumen, jadwal pengisian opsional). Dipakai kartu "Jadwal pengisian
 * nilai dosen" di halaman periode dan daftar bentuk penilaian draf di halaman Periode baru.
 */
final class LecturerComponentInput
{
    /**
     * @param  bool  $uniqueLabel  cek nama unik di periode konteks (draf periode baru dicek terhadap data master yang disalin)
     * @return array{label: string, roles: list<string>, document_types: list<string>, opens_at: ?string, due_at: ?string}
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validate(array $input, ?AssessmentComponent $ignore = null, bool $uniqueLabel = true): array
    {
        $label = ['required', 'string', 'max:100'];
        if ($uniqueLabel) {
            $label[] = Rule::unique('assessment_components', 'label')->where('period_id', PeriodContext::id())->ignore($ignore);
        }

        $data = Validator::make($input, [
            'label' => $label,
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['distinct', Rule::in(array_keys(AssessmentComponent::LECTURER_ROLES))],
            'document_types' => ['nullable', 'array'],
            'document_types.*' => ['distinct', Rule::in(array_keys(config('capstone.document_types')))],
            'opens_at' => ['nullable', 'date', 'required_with:due_at'],
            'due_at' => ['nullable', 'date', 'required_with:opens_at', 'after:opens_at'],
        ], [
            'label.required' => 'Isi nama bentuk penilaian.',
            'label.unique' => 'Bentuk penilaian dengan nama ini sudah ada.',
            'roles.required' => 'Pilih minimal satu penilai.',
            'opens_at.required_with' => 'Isi waktu dibuka, atau kosongkan keduanya untuk diatur nanti.',
            'due_at.required_with' => 'Isi deadline, atau kosongkan keduanya untuk diatur nanti.',
            'due_at.after' => 'Deadline penilaian harus setelah waktu dibuka.',
        ])->validate();

        return [
            'label' => $data['label'],
            'roles' => AssessmentComponent::orderedLecturerRoles($data['roles']),
            'document_types' => array_values(array_intersect(array_keys(config('capstone.document_types')), $data['document_types'] ?? [])),
            'opens_at' => $data['opens_at'] ?? null,
            'due_at' => $data['due_at'] ?? null,
        ];
    }
}
