<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F1 — login email + password. */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
    }

    public function test_each_role_logs_in_to_its_dashboard(): void
    {
        foreach (['admin@gmail.com' => 'admin.dashboard', 'mahasiswa@gmail.com' => 'mahasiswa.dashboard', 'dosen@gmail.com' => 'dosen.dashboard'] as $email => $route) {
            $this->post(route('login.attempt'), ['email' => $email, 'password' => 'password'])->assertRedirect(route($route));
            $this->assertAuthenticatedAs(User::query()->where('email', $email)->first());
            $this->post(route('logout'));
        }
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->post(route('login.attempt'), ['email' => 'mahasiswa@gmail.com', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_account_is_rejected(): void
    {
        User::query()->where('email', 'mahasiswa@gmail.com')->update(['is_active' => false]);

        $this->post(route('login.attempt'), ['email' => 'mahasiswa@gmail.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_demo_accounts_listed_only_when_dev_login_enabled(): void
    {
        config(['capstone.dev_login' => true]);
        $this->get('/login')->assertOk()->assertSee('mahasiswa@gmail.com');

        config(['capstone.dev_login' => false]);
        $this->get('/login')->assertOk()->assertDontSee('mahasiswa@gmail.com');
    }
}
