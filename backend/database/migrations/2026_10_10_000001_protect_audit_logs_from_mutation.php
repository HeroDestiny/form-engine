<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION reject_audit_log_mutation() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Audit logs are immutable'; END; $$");
            DB::unprepared('CREATE TRIGGER audit_logs_immutable BEFORE UPDATE OR DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION reject_audit_log_mutation()');
        } else {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                DB::unprepared("CREATE TRIGGER audit_logs_immutable_{$operation} BEFORE {$operation} ON audit_logs BEGIN SELECT RAISE(ABORT, 'Audit logs are immutable'); END");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_immutable ON audit_logs');
            DB::unprepared('DROP FUNCTION IF EXISTS reject_audit_log_mutation()');
        } else {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_immutable_UPDATE');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_immutable_DELETE');
        }
    }
};
