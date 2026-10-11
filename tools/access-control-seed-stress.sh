#!/usr/bin/env bash
set -euo pipefail

# Issue #70 / RB-005: source safety is independent of Runner execution authority.
# This script is destructive ONLY to its dedicated, initially absent scratch SQLite file.
fail_preflight() {
  printf '[seed-stress] Refused unsafe diagnostic target: %s\n' "$1" >&2
  exit 2
}

cycles="${WORKINTEL_SEED_STRESS_CYCLES:-12}"
log_dir="storage/logs/seed-stress"
db_dir="storage/framework/testing"
safe_db="$db_dir/seed-stress.sqlite"
db_path="${DB_DATABASE:-}"

# Reject before creating a directory, opening a file or invoking php/artisan.
[[ "${WORKINTEL_SEED_STRESS_ALLOW_DESTRUCTIVE:-}" == "1" ]] || fail_preflight "explicit local opt-in is required"
[[ "${APP_ENV:-}" == "testing" ]] || fail_preflight "APP_ENV must equal testing"
[[ "${DB_CONNECTION:-}" == "sqlite" ]] || fail_preflight "DB_CONNECTION must equal sqlite"
[[ -z "${DB_URL:-}" && -z "${DATABASE_URL:-}" ]] || fail_preflight "database URL overrides are forbidden"
[[ "$db_path" == "$safe_db" ]] || fail_preflight "DB_DATABASE must be the dedicated scratch path $safe_db"
[[ -f artisan && -f tools/access-control-seed-state.php ]] || fail_preflight "run only from the WorkIntel repository root"
[[ ! -f bootstrap/cache/config.php ]] || fail_preflight "cached Laravel configuration can override the isolated test database"
[[ "$cycles" =~ ^[1-9][0-9]*$ ]] || fail_preflight "cycle count must be a positive decimal integer"
(( ${#cycles} <= 2 )) || fail_preflight "cycle count cannot exceed 12"
(( 10#$cycles <= 12 )) || fail_preflight "cycle count cannot exceed 12"

for component in storage storage/logs storage/framework "$log_dir" "$db_dir" "$safe_db"; do
  [[ ! -L "$component" ]] || fail_preflight "symlinked diagnostic path component is forbidden: $component"
done
[[ ! -e "$safe_db" ]] || fail_preflight "dedicated scratch database already exists; refusing overwrite"

umask 077
mkdir -p "$log_dir" "$db_dir"
# Prevent .env/.env.testing URL keys from overriding our explicit isolated SQLite target.
DB_URL=''
DATABASE_URL=''
export DB_CONNECTION DB_DATABASE APP_ENV DB_URL DATABASE_URL

echo "Running ${cycles} fresh SQLite seed cycles against ${db_path}."

for cycle in $(seq 1 "$cycles"); do
  log_file="${log_dir}/cycle-${cycle}.log"
  state_file="${log_dir}/cycle-${cycle}-state.json"
  failure_state_file="${log_dir}/cycle-${cycle}-failure-state.json"
  : > "$db_path"

  echo "[seed-stress] cycle ${cycle}/${cycles}"
  if ! php artisan migrate:fresh --seed --force >"$log_file" 2>&1; then
    echo "[seed-stress] FAILURE in cycle ${cycle}; raw artisan output follows:"
    cat "$log_file"
    echo "[seed-stress] exact AccessControl/SQLite state at failure:"
    php tools/access-control-seed-state.php >"$failure_state_file" || true
    cat "$failure_state_file" || true
    exit 1
  fi

  if ! php tools/access-control-seed-state.php --assert >"$state_file"; then
    echo "[seed-stress] post-seed invariant FAILURE in cycle ${cycle}:"
    cat "$state_file"
    exit 1
  fi
done

echo "[seed-stress] PASS: ${cycles}/${cycles} fresh SQLite seed cycles satisfied owner/coordinator role and SQLite integrity invariants."
