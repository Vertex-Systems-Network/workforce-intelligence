#!/usr/bin/env bash
set -euo pipefail

cycles="${WORKINTEL_SEED_STRESS_CYCLES:-12}"
db_path="${DB_DATABASE:-database/database.sqlite}"
log_dir="storage/logs/seed-stress"
mkdir -p "$log_dir" "$(dirname "$db_path")"

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
