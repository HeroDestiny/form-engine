#!/bin/sh
set -eu
test "$PGHOST" = restore-db
test "$PGDATABASE" = form_engine_restore_check
case "$BACKUP_FILE" in *[!a-zA-Z0-9._-]*|'') echo 'Invalid backup filename' >&2; exit 1;; esac
archive="/backups/$BACKUP_FILE"
# Compare only the digest; manifests may originate from another absolute path.
expected=$(cut -d ' ' -f 1 "$archive.sha256")
actual=$(sha256sum "$archive" | cut -d ' ' -f 1)
test "$expected" = "$actual"
pg_restore --exit-on-error --single-transaction --no-owner --no-privileges --dbname="$PGDATABASE" "$archive"
psql --no-psqlrc --set=ON_ERROR_STOP=1 <<'SQL'
SELECT json_build_object(
  'tenants', (SELECT count(*) FROM tenants),
  'users', (SELECT count(*) FROM users),
  'forms', (SELECT count(*) FROM forms),
  'submissions', (SELECT count(*) FROM form_submissions),
  'audit_logs', (SELECT count(*) FROM audit_logs),
  'migrations', (SELECT count(*) FROM migrations));
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgrelid = 'audit_logs'::regclass AND NOT tgisinternal AND tgenabled = 'O') THEN
    RAISE EXCEPTION 'Audit immutability trigger missing';
  END IF;
END $$;
BEGIN;
DO $$ DECLARE probe_id bigint; error_message text; blocked boolean; BEGIN
  INSERT INTO audit_logs (action, entity_type, created_at, updated_at)
    VALUES ('RESTORE_INTERNAL_PROBE', 'operation', NOW(), NOW()) RETURNING id INTO probe_id;
  blocked := false;
  BEGIN
    UPDATE audit_logs SET action = 'MUTATION_PROBE' WHERE id = probe_id;
  EXCEPTION WHEN raise_exception THEN
    GET STACKED DIAGNOSTICS error_message = MESSAGE_TEXT;
    IF error_message <> 'Audit logs are immutable' THEN RAISE; END IF;
    blocked := true;
  END;
  IF NOT blocked THEN RAISE EXCEPTION 'Audit update was allowed'; END IF;
  blocked := false;
  BEGIN
    DELETE FROM audit_logs WHERE id = probe_id;
  EXCEPTION WHEN raise_exception THEN
    GET STACKED DIAGNOSTICS error_message = MESSAGE_TEXT;
    IF error_message <> 'Audit logs are immutable' THEN RAISE; END IF;
    blocked := true;
  END;
  IF NOT blocked THEN RAISE EXCEPTION 'Audit delete was allowed'; END IF;
END $$;
ROLLBACK;
SQL
echo 'Restauração verificada no banco descartável.'
