#!/bin/sh
set -eu
umask 077
partial=$(mktemp "/backups/form-engine-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXX")
archive="$partial.dump"
trap 'rm -f "$partial" "$archive.sha256.partial"' EXIT HUP INT TERM
pg_dump --format=custom --no-owner --no-privileges --file="$partial"
pg_restore --list "$partial" >/dev/null
mv "$partial" "$archive"
sha256sum "$archive" > "$archive.sha256.partial"
mv "$archive.sha256.partial" "$archive.sha256"
printf 'Backup concluído: %s\n' "$archive"
