<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Dosen hasil impor memakai password default 12345678 lalu menggantinya sendiri lewat menu profil → Ganti Password. */
class LecturerPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
    }

    public function test_imported_lecturer_uses_default_password_then_changes_it(): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        $csv = "nip,nama,kode_dosen,email,no_wa,peminatan,hak_akses\n1,Dosen Impor,DIM,impor@gmail.com,0811,Data,\n";
        $this->actingAs($admin)->withSession([Period::WORKSPACE_KEY => Period::active()->id])
            ->post(route('admin.imports.preview', 'dosen'), ['file' => UploadedFile::fake()->createWithContent('dosen.csv', $csv)]);
        $this->actingAs($admin)->post(route('admin.imports.commit', 'dosen'), ['token' => Cache::get("import:{$admin->id}:dosen")['token']])->assertSessionHasNoErrors();
        $this->post('/logout');

        $lecturer = User::query()->where('email', 'impor@gmail.com')->firstOrFail();
        $this->assertTrue(Hash::check('12345678', $lecturer->password));

        $this->post(route('login.attempt'), ['email' => 'impor@gmail.com', 'password' => '12345678'])->assertRedirect(route('dosen.dashboard'));
        $this->get(route('dosen.dashboard'))->assertOk()->assertSee('Ganti Password')->assertSee('masih memakai password default');

        DB::table('sessions')->insert(['id' => 'perangkat-lain', 'user_id' => $lecturer->id, 'payload' => '', 'last_activity' => time()]);
        $this->put(route('dosen.password.update'), ['current_password' => 'salah123', 'password' => 'rahasiaku9', 'password_confirmation' => 'rahasiaku9'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('dosen.password.update'), ['current_password' => '12345678', 'password' => 'rahasiaku9', 'password_confirmation' => 'beda12345'])
            ->assertSessionHasErrors('password');
        $this->put(route('dosen.password.update'), ['current_password' => '12345678', 'password' => 'rahasiaku9', 'password_confirmation' => 'rahasiaku9'])
            ->assertRedirect(route('dosen.password.edit'))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('rahasiaku9', $lecturer->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'perangkat-lain']);
        $this->get(route('dosen.dashboard'))->assertOk()->assertDontSee('masih memakai password default');
    }

    public function test_change_password_menu_is_only_for_lecturers(): void
    {
        $student = User::query()->where('email', 'mahasiswa@gmail.com')->first();
        $this->actingAs($student)->get(route('mahasiswa.dashboard'))->assertOk()->assertDontSee('Ganti Password');
        $this->actingAs($student)->get(route('dosen.password.edit'))->assertForbidden();
    }
}
