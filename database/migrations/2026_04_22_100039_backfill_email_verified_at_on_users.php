<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL'
        );
    }

    public function down(): void
    {
        // No-op: we don't un-backfill.
    }
};
