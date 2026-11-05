<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\PrerequisiteCourse;
use App\Models\Team;
use App\Models\User;
use App\Notifications\TeamRegistrationDecision;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** F21 — registrasi akun ketua + tim dalam satu formulir; akun anggota aktif setelah tim diterima admin. */
class TeamRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private PrerequisiteCourse $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withSession([Period::WORKSPACE_KEY => Period::active()->id]);
        $this->withoutVite();
        Storage::fake('local');
        $this->course = Period::active()->registrationRule->courses()->firstOrFail();
    }

    /** Logout pada tes menghapus sesi, jadi Dashboard Periode admin dibuka ulang. */
    private function asAdmin(): static
    {
        return $this->actingAs(User::query()->where('role', 'admin')->first())->withSession([Period::WORKSPACE_KEY => Period::active()->id]);
    }

    private function person(string $nim, string $name, string $region, array $extra = []): array
    {
        return array_merge([
            'nim' => $nim, 'name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@student.test',
            'specialization' => '', 'sks_passed' => 120, 'grades' => [$this->course->id => 'AB'],
            'origin_region' => $region, 'khs' => UploadedFile::fake()->create('khs.pdf', 100, 'application/pdf'),
        ], $extra);
    }

    private function form(array $leader = [], array $member = []): array
    {
        return [
            'name' => 'Tim Registrasi',
            'leader' => $this->person('1030001', 'Ketua Baru', 'Bandung, Jawa Barat', $leader + ['password' => 'rahasia123', 'password_confirmation' => 'rahasia123', 'specialization' => 'Enterprise Resource Planning']),
            'members' => [$this->person('1030002', 'Anggota Baru', 'Medan, Sumatera Utara', $member)],
        ];
    }

    public function test_register_page_offers_dropdowns_from_period_settings(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertSee('Kosongkan jika belum ada peminatan.')
            ->assertSee('<option value="Enterprise Resource Planning"', false)
            ->assertSee('Indeks nilai '.$this->course->code)
            ->assertSee('<option value="Belum Lulus"', false);
        $this->get(route('login'))->assertSee(route('register'));
    }

    public function test_leader_registers_account_and_team_then_members_activate_after_approval(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->form())->assertSessionHasNoErrors()->assertRedirect(route('register.success'));
        $this->get(route('register.success'))->assertOk()->assertSee('Tim Anda berhasil diajukan')->assertSee('Tim Registrasi');
        $this->get(route('register.success'))->assertRedirect(route('register')); // hanya sekali setelah kirim
        $this->assertGuest();

        $leader = User::query()->where('nim', '1030001')->sole();
        $member = User::query()->where('nim', '1030002')->sole();
        $this->assertSame('mahasiswa', $member->role);
        $this->assertSame('Enterprise Resource Planning', $leader->specialization);
        $this->assertNull($member->specialization, 'Peminatan opsional');
        $this->assertSame([$this->course->code => 'AB'], $member->course_grades);
        $this->assertContains($this->course->code, $member->passed_courses);
        $this->assertTrue($member->awaitingActivation());

        $team = $leader->teamFor(Period::active());
        $this->assertSame('menunggu', $team->status);
        $this->assertSame($leader->id, $team->leader_id);
        $this->assertSame($team->id, $member->teamFor(Period::active())?->id);
        $this->assertNotNull($team->members()->where('user_id', $member->id)->value('khs_path'));

        // Ketua dapat masuk dengan password yang dibuatnya; anggota belum punya password
        $this->post(route('login.attempt'), ['email' => $leader->email, 'password' => 'rahasia123'])->assertRedirect(route('mahasiswa.dashboard'));
        $this->post(route('logout'));
        $this->post(route('login.attempt'), ['email' => $member->email, 'password' => 'apa-saja'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Email keputusan: anggota mendapat tombol "Atur password", ketua hanya pemberitahuan diterima
        $this->asAdmin()->post(route('admin.teams.decide', $team), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $setupUrl = null;
        Notification::assertSentTo($member, TeamRegistrationDecision::class, function (TeamRegistrationDecision $n) use ($member, &$setupUrl) {
            $mail = $n->toMail($member);
            $setupUrl = $mail->actionUrl;

            return $n->approved && $mail->actionText === 'Atur password' && str_contains($mail->subject, 'diterima');
        });
        Notification::assertSentTo($leader, TeamRegistrationDecision::class, fn (TeamRegistrationDecision $n) => $n->approved && $n->toMail($leader)->actionText === null);

        // Email mencantumkan nama & NIM anggota lain (bukan penerima itu sendiri)
        Notification::assertSentTo($member, TeamRegistrationDecision::class, function (TeamRegistrationDecision $n) use ($member) {
            $lines = $n->toMail($member)->introLines;

            return in_array('• Ketua Baru — NIM 1030001 (Ketua)', $lines, true) && ! collect($lines)->contains(fn ($l) => str_contains($l, '1030002'));
        });
        Notification::assertSentTo($leader, TeamRegistrationDecision::class,
            fn (TeamRegistrationDecision $n) => in_array('• Anggota Baru — NIM 1030002', $n->toMail($leader)->introLines, true));
        $this->post(route('logout'));

        // Tautan Signed URL: tanpa kedaluwarsa, ditolak bila diubah, dan hanya berlaku sampai password diatur
        $this->travel(90)->days();
        $this->get($setupUrl.'x')->assertForbidden();
        $this->get($setupUrl)->assertOk()->assertSee($member->email);
        $action = \Illuminate\Support\Facades\URL::signedRoute('password.setup.store', ['user' => $member->id]);
        $this->post($action, ['password' => 'anggota123', 'password_confirmation' => 'anggota123'])->assertRedirect(route('login'));
        $this->get($setupUrl)->assertRedirect(route('login'));

        $this->post(route('login.attempt'), ['email' => $member->email, 'password' => 'anggota123'])->assertRedirect(route('mahasiswa.dashboard'));
        $this->assertAuthenticatedAs($member->fresh());
    }

    public function test_setup_link_works_in_a_browser_signed_in_as_admin(): void
    {
        Notification::fake();
        $this->post(route('register.store'), $this->form())->assertSessionHasNoErrors();
        $member = User::query()->where('nim', '1030002')->sole();
        $url = \App\Notifications\AccountActivation::url($member);
        $action = \Illuminate\Support\Facades\URL::signedRoute('password.setup.store', ['user' => $member->id]);

        // Tidak dialihkan ke Kelola Periode: halaman atur password tampil dengan identitas anggota
        $this->asAdmin()->get($url)->assertOk()
            ->assertSee('Atur password Anda')->assertSee('Anggota Baru')->assertSee('1030002')->assertSee($member->email)
            ->assertSee('Konfirmasi Password Baru')->assertSee('Browser ini sedang masuk sebagai');

        // Menyimpan password mengeluarkan sesi admin lalu menuju halaman masuk
        $this->asAdmin()->post($action, ['password' => 'anggota123', 'password_confirmation' => 'anggota123'])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertFalse($member->fresh()->awaitingActivation());
    }

    public function test_rejection_is_emailed_to_every_member(): void
    {
        Notification::fake();
        $this->post(route('register.store'), $this->form())->assertSessionHasNoErrors();
        $team = Team::query()->where('name', 'Tim Registrasi')->sole();

        $this->asAdmin()->post(route('admin.teams.decide', $team), ['decision' => 'reject', 'notes' => 'KHS buram'])->assertSessionHasNoErrors();
        foreach ($team->activeUsers as $user) {
            Notification::assertSentTo($user, TeamRegistrationDecision::class,
                fn (TeamRegistrationDecision $n) => ! $n->approved && $n->toMail($user)->actionText === null
                    && in_array('Alasan: KHS buram', $n->toMail($user)->introLines, true)
                    && in_array('Silakan lakukan registrasi ulang.', $n->toMail($user)->introLines, true));
        }

        // Tim dibubarkan: akun hasil registrasi, keanggotaan, dan KHS dihapus; data pendaftar tersisa sebagai log admin
        $team->refresh();
        $this->assertSame('ditolak', $team->status);
        $this->assertSame(0, $team->members()->count());
        $this->assertSame(['1030001', '1030002'], array_column($team->archived_members, 'nim'));
        $this->assertFalse(User::query()->whereIn('nim', ['1030001', '1030002'])->exists());
        $this->assertSame([], Storage::disk('local')->allFiles("registrations/{$team->id}"));
        $this->asAdmin()->get(route('admin.teams.index', ['tim' => $team->id]))->assertOk()->assertSee('Ketua Baru')->assertSee('log registrasi');

        // Seperti belum pernah mendaftar: formulir yang sama dapat dikirim ulang (nama tim boleh sama)
        $this->post(route('logout'));
        $this->post(route('register.store'), $this->form())->assertSessionHasNoErrors()->assertRedirect(route('register.success'));
        $this->assertSame('menunggu', User::query()->where('nim', '1030001')->sole()->teamFor(Period::active())->status);
    }

    public function test_registration_rejects_existing_account_failing_grade_and_unknown_specialization(): void
    {
        $existing = User::query()->where('email', 'mahasiswa14@gmail.com')->sole();

        // NIM yang sudah punya akun harus masuk dulu, bukan registrasi publik
        $this->post(route('register.store'), $this->form(['nim' => $existing->nim, 'email' => $existing->email]))->assertSessionHasErrors('leader.nim');

        // Indeks E pada mata kuliah prasyarat = belum lulus
        $this->post(route('register.store'), $this->form([], ['grades' => [$this->course->id => 'E']]))
            ->assertSessionHasErrors("members.0.grades.{$this->course->id}");

        // Indeks nilai hanya dari pilihan dropdown; peminatan hanya dari daftar periode
        $this->post(route('register.store'), $this->form(['grades' => [$this->course->id => 'Lulus']], ['specialization' => 'Peminatan Fiktif']))
            ->assertSessionHasErrors(["leader.grades.{$this->course->id}", 'members.0.specialization']);

        $this->assertGuest();
        $this->assertFalse(Team::query()->where('name', 'Tim Registrasi')->exists());
        $this->assertFalse(User::query()->where('nim', '1030001')->exists());
    }

    public function test_member_matched_by_nim_keeps_admin_specialization_and_admin_can_set_it(): void
    {
        $mate = User::query()->where('email', 'mahasiswa10@gmail.com')->sole();
        $mate->memberships()->delete();

        $this->post(route('register.store'), $this->form([], ['nim' => $mate->nim, 'name' => $mate->name, 'email' => $mate->email]))->assertSessionHasNoErrors();
        $this->assertSame('Enterprise Data Management', $mate->fresh()->specialization);
        $this->assertSame(1, User::query()->where('nim', $mate->nim)->count(), 'Tidak membuat akun ganda');

        // NIM terdaftar dengan email berbeda ditolak
        $this->post(route('logout'));
        $other = User::query()->where('email', 'mahasiswa11@gmail.com')->sole();
        $this->post(route('register.store'), $this->form(['nim' => '1030009', 'email' => 'lain@student.test'], ['nim' => $other->nim, 'email' => 'palsu@student.test']))
            ->assertSessionHasErrors(['members.0.nim' => "NIM {$other->nim} sudah terdaftar dengan email lain."])->assertSessionDoesntHaveErrors('members.0.email');
        // Email terdaftar dengan NIM berbeda → pesan di field email
        $this->post(route('register.store'), $this->form(['nim' => '1030009', 'email' => 'lain@student.test'], ['nim' => '1039999', 'email' => $other->email]))
            ->assertSessionHasErrors(['members.0.email' => 'Email ini sudah terdaftar dengan NIM lain.'])->assertSessionDoesntHaveErrors('members.0.nim');

        // Admin mengisi peminatan mahasiswa yang belum punya dari detail tim
        $newMember = User::query()->where('nim', '1030001')->sole();
        $this->asAdmin()->put(route('admin.students.specialization', $newMember), ['specialization' => 'System Architecture and Governance'])->assertSessionHasNoErrors();
        $this->assertSame('System Architecture and Governance', $newMember->fresh()->specialization);
        $this->asAdmin()->put(route('admin.students.specialization', $newMember), ['specialization' => 'Fiktif'])->assertSessionHasErrors('specialization');
    }

    public function test_students_only_view_their_team_while_admin_edits_and_deletes_it(): void
    {
        Notification::fake();
        $this->post(route('register.store'), $this->form())->assertSessionHasNoErrors();
        $team = Team::query()->where('name', 'Tim Registrasi')->sole();
        $this->asAdmin()->post(route('admin.teams.decide', $team), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $leader = User::query()->where('nim', '1030001')->sole();
        $this->post(route('logout'));

        // Sudah bertim: tombol Registrasi Tim tetap tampil tetapi nonaktif, tidak ada edit tim, dan pengajuan baru ditolak
        $this->actingAs($leader)->get(route('mahasiswa.team.show'))->assertOk()->assertSee('Tim Registrasi')
            ->assertSee('title="Tim Anda sudah terdaftar"', false)->assertDontSee('Edit</button>', false);
        $this->actingAs($leader)->post(route('mahasiswa.team.store'), ['name' => 'Ganti'])->assertSessionHasErrors('team');
        $this->post(route('logout'));

        // Admin mengubah tim yang sudah diterima: nama & anggota; status tetap
        $replacement = User::query()->where('email', 'mahasiswa10@gmail.com')->sole();
        $replacement->memberships()->delete();
        $this->asAdmin()->put(route('admin.teams.update', $team), ['_team' => (string) $team->id, 'name' => 'Tim Diubah', 'members' => [$leader->id, $replacement->id, '']])
            ->assertSessionHasNoErrors();
        $team->refresh();
        $this->assertSame(['Tim Diubah', 'diterima', $leader->id], [$team->name, $team->status, $team->leader_id]);
        $this->assertEqualsCanonicalizing([$leader->id, $replacement->id], $team->members()->pluck('user_id')->all());

        // Mahasiswa yang sudah bertim di tim lain tidak bisa dimasukkan
        $taken = User::query()->where('email', 'mahasiswa11@gmail.com')->sole();
        $this->asAdmin()->put(route('admin.teams.update', $team), ['_team' => (string) $team->id, 'name' => 'Tim Diubah', 'members' => [$leader->id, $taken->id]])
            ->assertSessionHasErrors('members.1');

        $this->asAdmin()->delete(route('admin.teams.destroy', $team))->assertRedirect(route('admin.teams.index'));
        $this->assertModelMissing($team);
        $this->assertNull($leader->fresh()->teamFor(Period::active()));
        $this->post(route('logout'));

        // Tim terhapus admin → akun anggota tetap tersimpan, dan ketua mendaftar lagi sendiri dari Tim Saya
        // (identitas ketua dari akunnya, anggota di-invite dari mahasiswa yang belum bertim — termasuk anggota lama)
        $oldMember = User::query()->where('nim', '1030002')->sole();
        $this->actingAs($leader)->get(route('mahasiswa.team.show'))->assertOk()->assertSee('Registrasi tim')
            ->assertSee('value="1030001" readonly', false)->assertDontSee('name="leader[password]"', false)
            ->assertSee('x-data="studentPicker"', false)->assertSee('"nim":"1030002"', false);
        $form = $this->form();
        unset($form['leader']['nim'], $form['leader']['name'], $form['leader']['email'], $form['leader']['password'], $form['leader']['password_confirmation']);
        $member = fn (User $u) => ['user_id' => $u->id] + collect($this->person('x', 'x', 'Medan, Sumatera Utara'))->except(['nim', 'name', 'email'])->all();
        $form['members'] = [$member($oldMember), $member($replacement)];
        $this->actingAs($leader)->post(route('mahasiswa.team.store'), $form)->assertSessionHasNoErrors()->assertRedirect(route('mahasiswa.team.show'));
        $again = $leader->fresh()->teamFor(Period::active());
        $this->assertSame(['menunggu', $leader->id], [$again->status, $again->leader_id]);
        $this->assertEqualsCanonicalizing([$leader->id, $oldMember->id, $replacement->id], $again->members()->pluck('user_id')->all());
        $this->assertSame('1030002', $oldMember->fresh()->nim, 'Identitas anggota diambil dari akunnya');
        $this->assertFalse((bool) $again->members()->where('user_id', $leader->id)->value('account_created'), 'Akun ketua bukan akun baru');

        // Hanya mahasiswa yang belum bertim: ketua sendiri atau mahasiswa bertim tidak dapat di-invite
        $this->asAdmin()->delete(route('admin.teams.destroy', $again));
        $this->post(route('logout'));
        $form['members'] = [$member($leader)];
        $this->actingAs($leader)->post(route('mahasiswa.team.store'), $form)->assertSessionHasErrors('members.0.user_id');
        $form['members'] = [$member(User::query()->where('email', 'mahasiswa11@gmail.com')->sole())];
        $this->actingAs($leader)->post(route('mahasiswa.team.store'), $form)->assertSessionHasErrors('members.0.user_id');
    }
}
