<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Kolom Kelas di Data & akun mahasiswa: tampil di tabel, diubah admin, dan diimpor (nim + kelas saja). */
class StudentClassTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
        $this->admin = User::query()->where('role', 'admin')->first();
        $this->withSession([Period::WORKSPACE_KEY => Period::active()->id]);
    }

    public function test_admin_sees_edits_and_imports_student_class(): void
    {
        $student = User::query()->where('email', 'mahasiswa@gmail.com')->first();
        $student->forceFill(['class_name' => 'SI-47-02'])->save();
        $page = $this->actingAs($this->admin)->get(route('admin.students.index'))->assertOk()
            ->assertSee('<th>Kelas</th>', false)->assertSee('SI-47-02')->assertSee('name="class_name"', false);
        // Aksi bertumpuk: Ubah di atas Nonaktifkan
        $this->assertStringContainsString('flex flex-col items-end', $page->getContent());

        $data = ['_student' => (string) $student->id, 'nim' => $student->nim, 'name' => $student->name, 'email' => $student->email,
            'phone' => '081234567890', 'sks_passed' => 128, 'origin_region' => 'Jawa Barat'];
        $classes = Period::active()->registrationRule?->classes ?? [];
        if ($classes !== []) {
            $this->actingAs($this->admin)->put(route('admin.students.update', $student), $data + ['class_name' => 'KELAS-ASING'])
                ->assertSessionHasErrors('class_name');
        }
        $target = $classes[2] ?? 'SI-47-03';
        $this->actingAs($this->admin)->put(route('admin.students.update', $student), $data + ['class_name' => $target])->assertSessionHasNoErrors();
        $this->assertSame($target, $student->fresh()->class_name);

        // Impor cukup nim + kelas: kelas diperbarui, data lain tidak tertimpa
        $csv = "nim,kelas\n{$student->nim},si-47-int\n";
        $this->actingAs($this->admin)->post(route('admin.imports.preview', 'mahasiswa'), ['file' => UploadedFile::fake()->createWithContent('kelas.csv', $csv)])
            ->assertRedirect(route('admin.imports.show', 'mahasiswa'));
        $token = Cache::get("import:{$this->admin->id}:mahasiswa")['token'];
        $this->actingAs($this->admin)->post(route('admin.imports.commit', 'mahasiswa'), ['token' => $token])->assertSessionHasNoErrors();
        $this->assertSame('SI-47-INT', $student->fresh()->class_name);
        $this->assertSame($student->name, $student->fresh()->name);
    }
}
