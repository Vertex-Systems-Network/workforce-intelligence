<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Validates the installed Block D lifecycle and Media Library schema on a running WorkIntel instance. */
class DataLifecycleMediaDoctor extends Command
{
    protected $signature = 'workintel:block-d-doctor';
    protected $description = 'Validate Data Lifecycle, Trash Center and Media Library installation.';

    /** Checks required tables, columns and permissions and returns a non-zero status on any defect. */
    public function handle(): int
    {
        $errors = [];
        foreach (['media_folders', 'media_assets', 'media_tags', 'media_asset_tag', 'media_usages', 'media_collections', 'media_asset_collection', 'media_favorites', 'media_asset_versions', 'data_lifecycle_events'] as $table) {
            if (! Schema::hasTable($table)) $errors[] = "Missing table: {$table}";
        }
        foreach (['clients', 'projects', 'tasks'] as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) $errors[] = "Missing {$table}.deleted_at";
        }
        if (! Schema::hasColumn('users', 'avatar_media_id')) $errors[] = 'Missing users.avatar_media_id';
        if (Schema::hasTable('permissions')) {
            foreach (['media.view', 'media.manage', 'trash.view', 'trash.restore', 'trash.purge'] as $slug) {
                if (! DB::table('permissions')->where('slug', $slug)->exists()) $errors[] = "Missing permission: {$slug}";
            }
        }

        $probe = 'media/.doctor-probe-'.bin2hex(random_bytes(4));
        try {
            $disk = Storage::disk('local');
            $writable = $disk->put($probe, 'ok') && $disk->exists($probe);
            $disk->delete($probe);
            if (! $writable) $errors[] = 'Private Media Library storage is not writable.';
        } catch (\Throwable $exception) {
            $errors[] = 'Private Media Library storage probe failed: '.$exception->getMessage();
        }

        $appLimit = max(1, (int) config('workintel.media.max_file_mb', 100)) * 1024 * 1024;
        $phpUpload = $this->iniBytes((string) ini_get('upload_max_filesize'));
        $phpPost = $this->iniBytes((string) ini_get('post_max_size'));
        $effective = min(array_filter([$appLimit, $phpUpload ?: $appLimit, $phpPost ?: $appLimit]));
        $this->line('Media upload limits: app '.round($appLimit / 1048576).' MB · PHP file '.round($phpUpload / 1048576).' MB · PHP post '.round($phpPost / 1048576).' MB · effective '.round($effective / 1048576).' MB');
        if ($errors) {
            foreach ($errors as $error) $this->error($error);
            return self::FAILURE;
        }
        $this->info('Block D Data Lifecycle + Media: PASS');
        return self::SUCCESS;
    }
    /** Convert a PHP shorthand byte value such as 64M or 1G into bytes for diagnostics. */
    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') return 0;
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        return (int) match ($unit) { 'g' => $number * 1024 * 1024 * 1024, 'm' => $number * 1024 * 1024, 'k' => $number * 1024, default => $number };
    }

}
