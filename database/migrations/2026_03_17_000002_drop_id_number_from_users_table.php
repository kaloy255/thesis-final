<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'id_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('id_number');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'id_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->integer('id_number')->unique()->after('id');
            });
        }
    }
};

