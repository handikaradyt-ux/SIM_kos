<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (! $this->app) {
            $this->refreshApplication();
        }

        $this->verifyTestDatabaseGuard();

        parent::setUp();
    }

    /**
     * Safety guard ensuring tests ONLY execute on MySQL sim_kos_test.
     */
    protected function verifyTestDatabaseGuard(): void
    {
        $defaultConnection = config('database.default');
        $driver = config("database.connections.{$defaultConnection}.driver");

        try {
            $databaseName = DB::select('SELECT DATABASE() as db')[0]->db ?? null;
        } catch (\Throwable $e) {
            throw new RuntimeException("SAFETY GUARD TRIGGERED: Cannot execute SELECT DATABASE(): " . $e->getMessage());
        }

        if ($driver !== 'mysql' || $databaseName !== 'sim_kos_test') {
            throw new RuntimeException(
                "SAFETY GUARD TRIGGERED: Test database connection must be MySQL 'sim_kos_test'. " .
                "Current driver is '{$driver}', database is '{$databaseName}'. Execution aborted to protect application/dev data!"
            );
        }
    }

    /**
     * Set the currently logged in user for the application,
     * including valid session signature for the application's security middleware.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string|null  $guard
     * @return $this
     */
    public function actingAs($user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if ($user instanceof \App\Models\User) {
            $sessionKey = 'auth_session_hash_' . $user->id;
            $this->withSession([
                $sessionKey => $user->getSessionSignature(),
            ]);
        }

        return $this;
    }
}
