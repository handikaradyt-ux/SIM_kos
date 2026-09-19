<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        RateLimiter::clear('admin.auth.test@example.test|127.0.0.1');
    }

    private function createUser(string $roleCode, string $email, string $password = 'Password123456!', bool $isActive = true, bool $mustChange = false): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();

        return User::create([
            'role_id' => $role->id,
            'name' => 'User ' . ucfirst($roleCode),
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => $isActive,
            'must_change_password' => $mustChange,
        ]);
    }

    // =========================================================================
    // TC-01: Multi-Role Login & Credentials Validation
    // =========================================================================

    public function test_tc01_admin_user_can_login_with_valid_credentials_and_redirects_to_admin_dashboard(): void
    {
        $admin = $this->createUser('admin', 'admin.login@example.test', 'AdminPassword123!');

        session()->start();
        $sessionIdBefore = session()->getId();

        $response = $this->post('/login', [
            'email' => 'admin.login@example.test',
            'password' => 'AdminPassword123!',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        // Verify session ID regeneration after login
        $this->assertNotEmpty($sessionIdBefore);
        $this->assertNotEmpty(session()->getId());
        $this->assertNotEquals($sessionIdBefore, session()->getId());

        // Verify audit log for login exists without storing passwords
        $log = ActivityLog::where('module', 'auth')
            ->where('action', 'login')
            ->where('actor_id', $admin->id)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('password', $log->changes['after'] ?? []);
    }

    public function test_tc01_owner_user_can_login_with_valid_credentials_and_redirects_to_owner_dashboard(): void
    {
        $owner = $this->createUser('owner', 'owner.login@example.test', 'OwnerPassword123!');

        $response = $this->post('/login', [
            'email' => 'owner.login@example.test',
            'password' => 'OwnerPassword123!',
        ]);

        $response->assertRedirect(route('owner.dashboard'));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_tc01_resident_with_permanent_password_can_login_and_redirects_to_portal(): void
    {
        $residentUser = $this->createUser('resident', 'resident.login@example.test', 'ResidentPass123!', true, false);

        Resident::create([
            'user_id' => $residentUser->id,
            'name' => $residentUser->name,
            'phone' => '081234567891',
            'origin_address' => 'Kota Asal',
        ]);

        $response = $this->post('/login', [
            'email' => 'resident.login@example.test',
            'password' => 'ResidentPass123!',
        ]);

        $response->assertRedirect(route('resident.portal'));
        $this->assertAuthenticatedAs($residentUser);
    }

    public function test_tc01_login_fails_with_invalid_password_returns_generic_error_and_leaves_no_authenticated_session(): void
    {
        $this->createUser('admin', 'admin.fail@example.test', 'ValidPassword123!');

        $response = $this->post('/login', [
            'email' => 'admin.fail@example.test',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_tc01_inactive_user_cannot_login_and_receives_generic_error_without_authenticated_session(): void
    {
        $this->createUser('admin', 'admin.inactive@example.test', 'ValidPassword123!', false);

        $response = $this->post('/login', [
            'email' => 'admin.inactive@example.test',
            'password' => 'ValidPassword123!',
        ]);

        // Generic error message returned without revealing account existence or active status
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    // =========================================================================
    // TC-02: Logout Session Invalidation & Rate Limiting
    // =========================================================================

    public function test_tc02_logout_invalidates_session_regenerates_csrf_token_and_blocks_back_access(): void
    {
        $admin = $this->createUser('admin', 'admin.logout@example.test');

        $this->actingAs($admin);
        $this->assertAuthenticated();

        // Seed session with custom data, capture CSRF token and session ID before logout
        session()->put('custom_auth_payload', 'sensitive_data_123');
        $csrfTokenBefore = session()->token();
        $sessionIdBefore = session()->getId();

        $response = $this->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();

        // Session data is thoroughly cleared, CSRF token is regenerated, and session ID changes
        $this->assertFalse(session()->has('custom_auth_payload'));
        $this->assertNull(session()->get('custom_auth_payload'));
        $this->assertNotEmpty(session()->token());
        $this->assertNotEquals($csrfTokenBefore, session()->token());
        $this->assertNotEmpty(session()->getId());
        $this->assertNotEquals($sessionIdBefore, session()->getId());

        // Subsequent access to protected dashboard is redirected to login
        $followUp = $this->get('/admin/dashboard');
        $followUp->assertRedirect(route('login'));
    }

    public function test_tc02_login_is_throttled_after_five_failed_attempts_in_one_minute(): void
    {
        $email = 'throttle.test@example.test';
        $this->createUser('admin', $email, 'RealPassword123!');

        // 5 consecutive failed attempts
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post('/login', [
                'email' => $email,
                'password' => 'BadPassword',
            ]);
            $response->assertSessionHasErrors(['email']);
        }

        // 6th attempt within the same minute is blocked by throttle
        $response6 = $this->post('/login', [
            'email' => $email,
            'password' => 'RealPassword123!',
        ]);

        $response6->assertSessionHasErrors(['email']);
        $errors = session('errors')->get('email');
        $this->assertStringContainsString('Terlalu banyak percobaan login', $errors[0]);
        $this->assertGuest();
    }

    // =========================================================================
    // TC-03: Server-side Authorization & Role Protection
    // =========================================================================

    public function test_tc03_owner_cannot_access_admin_dashboard_or_mutation_route_and_receives_403(): void
    {
        $owner = $this->createUser('owner', 'owner.auth@example.test');
        $this->actingAs($owner);

        // Attempt to access admin dashboard
        $response1 = $this->get('/admin/dashboard');
        $response1->assertStatus(403);

        // Attempt to execute admin mutation directly via test route
        $response2 = $this->post('/_test/admin-mutation');
        $response2->assertStatus(403);
    }

    public function test_tc03_resident_cannot_access_admin_dashboard_or_mutation_route_and_receives_403(): void
    {
        $resident = $this->createUser('resident', 'resident.auth@example.test');
        $this->actingAs($resident);

        $response1 = $this->get('/admin/dashboard');
        $response1->assertStatus(403);

        $response2 = $this->post('/_test/admin-mutation');
        $response2->assertStatus(403);
    }

    public function test_tc03_admin_can_access_admin_dashboard_and_admin_mutation_route(): void
    {
        $admin = $this->createUser('admin', 'admin.ok@example.test');
        $this->actingAs($admin);

        $response1 = $this->get('/admin/dashboard');
        $response1->assertStatus(200);

        $response2 = $this->post('/_test/admin-mutation');
        $response2->assertStatus(200);
        $response2->assertJson(['status' => 'mutation_executed']);
    }

    // =========================================================================
    // TC-04: Resident Data Ownership Policy (Foundation Scope)
    // =========================================================================

    public function test_tc04_resident_can_view_own_profile_via_policy(): void
    {
        $userA = $this->createUser('resident', 'resident.a@example.test');
        $residentA = Resident::create([
            'user_id' => $userA->id,
            'name' => 'Penghuni A',
            'phone' => '0811111111',
            'origin_address' => 'Kota A',
        ]);

        $this->actingAs($userA);

        $response = $this->get("/_test/resident-profile/{$residentA->id}");
        $response->assertStatus(200);
        $response->assertJson(['resident' => 'Penghuni A']);
    }

    public function test_tc04_resident_cannot_view_other_resident_profile_and_receives_403(): void
    {
        $userA = $this->createUser('resident', 'resident.a2@example.test');
        Resident::create([
            'user_id' => $userA->id,
            'name' => 'Penghuni A',
            'phone' => '0811111112',
            'origin_address' => 'Kota A',
        ]);

        $userB = $this->createUser('resident', 'resident.b@example.test');
        $residentB = Resident::create([
            'user_id' => $userB->id,
            'name' => 'Penghuni B',
            'phone' => '0822222222',
            'origin_address' => 'Kota B',
        ]);

        // Acting as Resident A, attempt to view Resident B's data
        $this->actingAs($userA);

        $response = $this->get("/_test/resident-profile/{$residentB->id}");
        $response->assertStatus(403);
    }

    public function test_tc04_owner_can_view_any_resident_profile_as_readonly(): void
    {
        $owner = $this->createUser('owner', 'owner.view@example.test');
        $userB = $this->createUser('resident', 'resident.b2@example.test');
        $residentB = Resident::create([
            'user_id' => $userB->id,
            'name' => 'Penghuni B',
            'phone' => '0822222223',
            'origin_address' => 'Kota B',
        ]);

        $this->actingAs($owner);

        $response = $this->get("/_test/resident-profile/{$residentB->id}");
        $response->assertStatus(200);
        $response->assertJson(['resident' => 'Penghuni B']);
    }

    public function test_tc04_user_without_resident_profile_does_not_trigger_500_error(): void
    {
        // Resident role user without profile record yet
        $userWithoutProfile = $this->createUser('resident', 'no.profile@example.test');
        $otherUser = $this->createUser('resident', 'other.res@example.test');
        $otherResident = Resident::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Resident',
            'phone' => '0833333333',
            'origin_address' => 'Kota C',
        ]);

        $this->actingAs($userWithoutProfile);

        // Accessing other resident profile correctly yields 403, NOT error 500
        $response = $this->get("/_test/resident-profile/{$otherResident->id}");
        $response->assertStatus(403);
    }

    // =========================================================================
    // TC-05: Deactivation of Active Session, Temporary Password & Audit
    // =========================================================================

    public function test_tc05_active_session_is_immediately_terminated_when_user_is_deactivated_in_database(): void
    {
        $admin = $this->createUser('admin', 'admin.active.test@example.test');
        $this->actingAs($admin);

        // Verify active user can access dashboard
        $this->get('/admin/dashboard')->assertStatus(200);

        // Admin account is deactivated while session is active
        $admin->is_active = false;
        $admin->save();

        // Very next request is intercepted by EnsureAccountIsActive middleware
        $response = $this->get('/admin/dashboard');
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_tc05_user_with_temporary_password_is_forced_to_change_password_route(): void
    {
        $user = $this->createUser('resident', 'temp.user@example.test', 'TempPass123456!', true, true);
        $this->actingAs($user);

        // Attempting to access portal redirects to password.change
        $response = $this->get('/portal');
        $response->assertRedirect(route('password.change'));

        // Accessing password change form is permitted (no infinite redirect loop)
        $formResponse = $this->get('/password/change');
        $formResponse->assertStatus(200);

        // Logout is still permitted while in temporary password state
        $logoutResponse = $this->post('/logout');
        $logoutResponse->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_tc05_change_password_fails_if_current_password_is_wrong(): void
    {
        $user = $this->createUser('resident', 'change.wrong.cur@example.test', 'OldPassword1234!', true, true);
        $this->actingAs($user);

        $response = $this->post('/password/change', [
            'current_password' => 'IncorrectOldPassword!',
            'password' => 'NewStrongPassword123!',
            'password_confirmation' => 'NewStrongPassword123!',
        ]);

        $response->assertSessionHasErrors(['current_password']);
        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('OldPassword1234!', $user->fresh()->password));
    }

    public function test_tc05_change_password_fails_if_new_password_less_than_twelve_chars_or_confirmation_mismatches(): void
    {
        $user = $this->createUser('resident', 'change.short@example.test', 'OldPassword1234!', true, true);
        $this->actingAs($user);

        // 1. Password too short (< 12 chars)
        $resShort = $this->post('/password/change', [
            'current_password' => 'OldPassword1234!',
            'password' => 'Short1!',
            'password_confirmation' => 'Short1!',
        ]);
        $resShort->assertSessionHasErrors(['password']);

        // 2. Confirmation mismatch
        $resMismatch = $this->post('/password/change', [
            'current_password' => 'OldPassword1234!',
            'password' => 'LongEnoughPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);
        $resMismatch->assertSessionHasErrors(['password']);
    }

    public function test_tc05_change_password_succeeds_resets_flag_regenerates_session_and_records_audit_without_passwords(): void
    {
        $user = $this->createUser('resident', 'change.ok@example.test', 'OldPassword1234!', true, true);
        $this->actingAs($user);

        session()->start();
        $sessionIdBefore = session()->getId();

        $response = $this->post('/password/change', [
            'current_password' => 'OldPassword1234!',
            'password' => 'NewBrandPassword2026!',
            'password_confirmation' => 'NewBrandPassword2026!',
        ]);

        $response->assertRedirect(route('resident.portal'));

        // Verify session ID regeneration after successful password change
        $this->assertNotEmpty($sessionIdBefore);
        $this->assertNotEmpty(session()->getId());
        $this->assertNotEquals($sessionIdBefore, session()->getId());

        $freshUser = $user->fresh();
        $this->assertFalse($freshUser->must_change_password);
        $this->assertTrue(Hash::check('NewBrandPassword2026!', $freshUser->password));

        // Verify audit log exists with no passwords or tokens
        $log = ActivityLog::where('module', 'users')
            ->where('action', 'password_change')
            ->where('actor_id', $user->id)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(['must_change_password' => true], $log->changes['before']);
        $this->assertSame(['must_change_password' => false], $log->changes['after']);
        $this->assertArrayNotHasKey('password', $log->changes['before']);
        $this->assertArrayNotHasKey('password', $log->changes['after']);
    }

    public function test_tc05_password_change_rolls_back_if_audit_fails(): void
    {
        $user = $this->createUser('resident', 'change.rollback@example.test', 'OriginalPassword123!', true, true);
        $this->actingAs($user);

        // Mock AuditService to fail during password change transaction
        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')
            ->willThrowException(new \RuntimeException('Simulated Audit Failure'));

        $this->app->instance(AuditService::class, $mockAudit);

        $exceptionCaught = false;

        $this->withoutExceptionHandling();

        try {
            $this->post('/password/change', [
                'current_password' => 'OriginalPassword123!',
                'password' => 'NewUnsavedPassword123!',
                'password_confirmation' => 'NewUnsavedPassword123!',
            ]);
        } catch (\RuntimeException $e) {
            $exceptionCaught = true;
            $this->assertSame('Simulated Audit Failure', $e->getMessage());
        }

        $this->assertTrue($exceptionCaught, 'Expected audit failure exception to be thrown from transaction');

        // Verify atomic rollback: password remains original, flag remains true
        $freshUser = $user->fresh();
        $this->assertTrue($freshUser->must_change_password);
        $this->assertTrue(Hash::check('OriginalPassword123!', $freshUser->password));
        $this->assertFalse(Hash::check('NewUnsavedPassword123!', $freshUser->password));
    }

    // =========================================================================
    // Asymmetric Audit Failure Handling (Login vs Logout)
    // =========================================================================

    public function test_login_leaves_no_session_if_login_audit_recording_fails(): void
    {
        $this->createUser('admin', 'admin.auditfail@example.test', 'AdminPassword123!');

        // Mock AuditService to fail when login audit is recorded
        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')
            ->willThrowException(new \RuntimeException('Database error during login audit'));

        $this->app->instance(AuditService::class, $mockAudit);

        $response = $this->post('/login', [
            'email' => 'admin.auditfail@example.test',
            'password' => 'AdminPassword123!',
        ]);

        $response->assertSessionHasErrors(['email']);

        // CRITICAL: User MUST NOT remain authenticated if login audit fails
        $this->assertGuest();
    }

    public function test_logout_always_terminates_session_even_if_logout_audit_recording_fails(): void
    {
        $admin = $this->createUser('admin', 'admin.logoutfail@example.test');
        $this->actingAs($admin);
        $this->assertAuthenticated();

        // Mock AuditService to fail when logout audit is recorded
        $mockAudit = $this->createMock(AuditService::class);
        $mockAudit->method('log')
            ->willThrowException(new \RuntimeException('Database error during logout audit'));

        $this->app->instance(AuditService::class, $mockAudit);

        $response = $this->post('/logout');

        // CRITICAL: User session MUST BE terminated regardless of audit failure
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // =========================================================================
    // UserSeeder: Local Config Validation & Idempotency Tests
    // =========================================================================

    public function test_seeder_throws_exception_when_password_config_is_empty_for_unseeded_account(): void
    {
        // Ensure demo admin does not exist
        User::where('email', 'admin@example.test')->delete();

        // Set empty config for admin password
        config(['auth.demo_passwords.admin' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Konfigurasi password demo untuk admin belum diatur atau kosong (DEMO_ADMIN_PASSWORD)');

        (new UserSeeder)->run();

        // Verify no partial account created
        $this->assertDatabaseMissing('users', ['email' => 'admin@example.test']);
    }

    public function test_seeder_is_idempotent_and_does_not_modify_existing_accounts_on_subsequent_runs(): void
    {
        // 1. Configure valid demo passwords
        config([
            'auth.demo_passwords.admin' => 'ValidAdminPass123!',
            'auth.demo_passwords.owner' => 'ValidOwnerPass123!',
            'auth.demo_passwords.resident' => 'ValidResidentPass123!',
        ]);

        // Clean any existing demo accounts for this test
        Resident::whereHas('user', fn ($q) => $q->where('email', 'resident@example.test'))->delete();
        User::whereIn('email', ['admin@example.test', 'owner@example.test', 'resident@example.test'])->delete();

        // 2. Initial run
        (new UserSeeder)->run();

        $adminBefore = User::where('email', 'admin@example.test')->firstOrFail();
        $adminPasswordHash = $adminBefore->password;
        $adminRoleId = $adminBefore->role_id;

        // Custom modify existing account to verify it's never overwritten
        $adminBefore->update([
            'name' => 'Modified Admin Name',
            'is_active' => false,
        ]);

        // 3. Second run with altered config
        config([
            'auth.demo_passwords.admin' => 'AlteredConfigPass999!',
        ]);

        (new UserSeeder)->run();

        $adminAfter = $adminBefore->fresh();

        // Existing account MUST NOT be modified or overwritten
        $this->assertSame('Modified Admin Name', $adminAfter->name);
        $this->assertFalse($adminAfter->is_active);
        $this->assertSame($adminPasswordHash, $adminAfter->password);
        $this->assertSame($adminRoleId, $adminAfter->role_id);
    }
}
