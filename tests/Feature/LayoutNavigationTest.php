<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LayoutNavigationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createTestUser(string $roleCode, string $email, bool $mustChange = false): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();

        return User::create([
            'role_id' => $role->id,
            'name' => 'Tester ' . ucfirst($roleCode),
            'email' => $email,
            'password' => Hash::make('Password123456!'),
            'is_active' => true,
            'must_change_password' => $mustChange,
        ]);
    }

    public function test_login_page_renders_accessible_inputs_without_password_value(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Masuk');
        $response->assertSee('Alamat Email');
        $response->assertSee('Kata Sandi');
        $response->assertSee('type="email"', false);
        $response->assertSee('type="password"', false);
        // Ensure no hardcoded password value attribute exists
        $response->assertDontSee('name="password" value=', false);
        // Ensure local build asset or Vite dev server client is referenced
        $hasViteAssets = str_contains($response->getContent(), '/build/assets/app-') || str_contains($response->getContent(), '@vite/client');
        $this->assertTrue($hasViteAssets, 'Asset Vite tidak ditemukan pada login page.');
        $response->assertDontSee('cdn.jsdelivr.net', false);
        $response->assertDontSee('fonts.bunny.net', false);
    }

    public function test_login_validation_error_renders_aria_invalid_and_preserves_no_password_value(): void
    {
        $response = $this->from(route('login'))->followingRedirects()->post(route('login'), [
            'email' => 'invalid-email',
            'password' => '',
        ]);

        $response->assertStatus(200);
        $response->assertSee('aria-invalid="true"', false);
        $response->assertSee('invalid-feedback', false);
        $response->assertSee('Format email tidak valid.');
        // Password must not have a value attribute
        $response->assertDontSee('name="password" value=', false);
    }

    public function test_admin_dashboard_renders_role_layout_and_neutral_empty_state(): void
    {
        $admin = $this->createTestUser('admin', 'admin.ui@example.test');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Dashboard Admin');
        $response->assertSee('Tester Admin');
        $response->assertSee('badge-role-admin', false);
        $response->assertSee('Informasi Operasional Belum Tersedia');
        // Check future menu items are non-interactive with "Belum tersedia"
        $response->assertSee('simkos-badge-unavailable', false);
        $response->assertSee('Belum tersedia');
        $response->assertDontSee('href="#"', false);
        // Ensure logout POST form is present
        $response->assertSee(route('logout'));
        // Ensure local build assets or Vite dev server assets are loaded
        $hasViteAssets = str_contains($response->getContent(), '/build/assets/app-') || str_contains($response->getContent(), '@vite/client');
        $this->assertTrue($hasViteAssets, 'Asset Vite tidak ditemukan pada layout.');
    }

    public function test_owner_dashboard_renders_role_layout_and_neutral_empty_state(): void
    {
        $owner = $this->createTestUser('owner', 'owner.ui@example.test');

        $response = $this->actingAs($owner)->get(route('owner.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Dashboard Pemilik');
        $response->assertSee('Tester Owner');
        $response->assertSee('badge-role-owner', false);
        $response->assertSee('Informasi Pemantauan Belum Tersedia');
        $response->assertSee('Belum tersedia');
        $response->assertDontSee('href="#"', false);
        $response->assertSee(route('logout'));
    }

    public function test_resident_portal_renders_role_layout_and_neutral_empty_state(): void
    {
        $resident = $this->createTestUser('resident', 'resident.ui@example.test');

        $response = $this->actingAs($resident)->get(route('resident.portal'));

        $response->assertStatus(200);
        $response->assertSee('Portal Penghuni');
        $response->assertSee('Tester Resident');
        $response->assertSee('badge-role-resident', false);
        $response->assertSee('Informasi Layanan Mandiri Belum Tersedia');
        $response->assertSee('Belum tersedia');
        $response->assertDontSee('href="#"', false);
        $response->assertSee(route('logout'));
    }

    public function test_user_with_temporary_password_sees_restricted_navigation(): void
    {
        $user = $this->createTestUser('admin', 'temp.admin@example.test', true);

        $response = $this->actingAs($user)->get(route('password.change'));

        $response->assertStatus(200);
        $response->assertSee('Password Sementara');
        $response->assertSee('Akses dibatasi sampai Anda mengganti password');
        $response->assertSee('Ganti Password');
        $response->assertSee(route('logout'));
        // Must NOT see normal admin menu items like Kamar, Tagihan, etc.
        $response->assertDontSee('Operasional Kos');
        $response->assertDontSee('Keuangan');
        $response->assertDontSee('Layanan & Sistem');
    }
}
