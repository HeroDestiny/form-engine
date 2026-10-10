<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX form_versions_one_draft ON form_versions (form_id) WHERE is_published = false');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS form_versions_one_draft');
    }
};
