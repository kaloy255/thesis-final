<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'email')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('email')->nullable()->unique();
            });
        }

        // Backfill for existing records to avoid lockouts.
        // Users can later update to real addresses; must remain @chcc.edu.ph.
        if (Schema::hasColumn('users', 'email')) {
            DB::table('users')
                ->whereNull('email')
                ->orWhere('email', '')
                ->orderBy('id')
                ->chunkById(500, function ($users) {
                    foreach ($users as $user) {
                        $email = null;
                        if (property_exists($user, 'id_number') && $user->id_number !== null) {
                            $email = $user->id_number . '@chcc.edu.ph';
                        }

                        DB::table('users')
                            ->where('id', $user->id)
                            ->update([
                                'email' => $email ?? ('user' . $user->id . '@chcc.edu.ph'),
                            ]);
                    }
                });
        }

        // Enforce NOT NULL when supported without doctrine/dbal.
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN email SET NOT NULL');
        }
        // SQLite column alterations are intentionally skipped here.
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'email')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['email']);
                $table->dropColumn('email');
            });
        }
    }
};

