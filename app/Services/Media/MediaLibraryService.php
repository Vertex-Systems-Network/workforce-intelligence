<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use App\Models\MediaAssetVersion;
use App\Models\MediaCollection;
use App\Models\MediaFolder;
use App\Models\MediaRendition;
use App\Models\MediaUploadSession;
use App\Models\MediaTag;
use App\Models\MediaUsage;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Security\UploadSecurityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Provides workspace media-library storage, metadata and usage operations. */
class MediaLibraryService
{
    /** Create the media service with production upload inspection. */
    public function __construct(private readonly UploadSecurityService $uploadSecurity) {}
    /** Extensions that are never accepted as ordinary media-library uploads. */
    public const BLOCKED_EXTENSIONS = ['exe', 'com', 'scr', 'msi', 'bat', 'cmd', 'ps1', 'vbs', 'js', 'jar', 'sh'];

    /** Stores one validated private asset or reuses an existing checksum duplicate. */
    public function upload(Workspace $workspace, User $user, UploadedFile $file, ?int $folderId = null, array $attributes = []): array
    {
        abort_unless($file->isValid(), 422, 'The uploaded file is invalid.');
        $maxBytes = max(1, (int) config('workintel.media.max_file_mb', 100)) * 1024 * 1024;
        abort_if((int) $file->getSize() > $maxBytes, 422, 'The uploaded file exceeds the workspace media size limit.');

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        abort_if(in_array($extension, self::BLOCKED_EXTENSIONS, true), 422, 'This executable file type is not allowed in Media Library.');

        $inspection = $this->uploadSecurity->inspect($file);
        $realPath = (string) $inspection['path'];
        $detectedMime = (string) $inspection['mime'];
        $scan = (array) $inspection['scan'];
        $checksum = hash_file('sha256', $realPath);
        abort_unless(is_string($checksum) && $checksum !== '', 422, 'The upload checksum could not be calculated safely.');
        $duplicate = MediaAsset::query()
            ->where('workspace_id', $workspace->id)
            ->where('checksum_sha256', $checksum)
            ->whereNull('deleted_at')
            ->first();
        if ($duplicate) return ['asset' => $duplicate->load(['folder', 'tags', 'collections']), 'duplicate' => true];

        $folder = $folderId ? MediaFolder::query()->where('workspace_id', $workspace->id)->findOrFail($folderId) : null;
        $uuid = (string) Str::uuid();
        $safeExtension = preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'bin';
        $quarantined = ($scan['status'] ?? null) === 'infected';
        $path = ($quarantined ? 'quarantine/' : 'media/').$workspace->id.'/'.now()->format('Y/m').'/'.$uuid.'.'.$safeExtension;
        $disk = Storage::disk('local');
        $directory = dirname($path);
        if (! $disk->directoryExists($directory)) abort_unless($disk->makeDirectory($directory), 500, 'The media storage directory could not be prepared.');
        $stored = $disk->putFileAs($directory, $file, basename($path));
        abort_unless($stored, 500, 'The media file could not be stored.');

        [$width, $height] = $this->imageDimensions($realPath, $detectedMime);
        $baseName = trim((string) ($attributes['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)));
        $baseName = Str::limit($baseName !== '' ? $baseName : 'Untitled media', 180, '');

        try {
            $asset = MediaAsset::create([
                'uuid' => $uuid,
                'workspace_id' => $workspace->id,
                'folder_id' => $folder?->id,
                'uploaded_by' => $user->id,
                'name' => $baseName,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'disk' => 'local',
                'path' => $path,
                'mime_type' => $detectedMime,
                'extension' => $safeExtension,
                'size_bytes' => (int) $file->getSize(),
                'checksum_sha256' => $checksum,
                'width' => $width,
                'height' => $height,
                'alt_text' => isset($attributes['alt_text']) ? trim((string) $attributes['alt_text']) ?: null : null,
                'caption' => isset($attributes['caption']) ? trim((string) $attributes['caption']) ?: null : null,
                'visibility' => 'private',
                'status' => $quarantined ? 'quarantined' : 'ready',
                'metadata' => ['source' => $attributes['source'] ?? 'upload', 'security_scan' => $scan, 'browser_mime' => $file->getClientMimeType()],
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        $this->snapshotVersion($asset, $user->id, 'Initial DAM version');
        return ['asset' => $asset->load(['folder', 'tags', 'collections']), 'duplicate' => false];
    }

    /** Updates editable DAM metadata, collection placement and immutable version history. */
    public function update(MediaAsset $asset, array $data, WorkspaceMember $actor): MediaAsset
    {
        abort_unless((int) $asset->workspace_id === (int) $actor->workspace_id, 404);
        if (array_key_exists('folder_id', $data) && $data['folder_id']) {
            MediaFolder::query()->where('workspace_id', $asset->workspace_id)->findOrFail((int) $data['folder_id']);
        }
        if (array_key_exists('collection_ids', $data)) {
            $requested = collect((array) $data['collection_ids'])->map(fn ($id) => (int) $id)->filter()->unique()->values();
            $valid = MediaCollection::query()->where('workspace_id', $asset->workspace_id)->whereIn('id', $requested)->pluck('id');
            abort_if($valid->count() !== $requested->count(), 422, 'One or more media collections are unavailable in this workspace.');
        }

        $asset->update(collect($data)->only(['name','folder_id','alt_text','caption','copyright_owner','license_type','license_reference','license_expires_at','usage_restrictions','rights_review_at','focal_x','focal_y'])->map(fn ($value) => is_string($value) ? trim($value) ?: null : $value)->all());
        if (array_key_exists('tags', $data)) $this->syncTags($asset, (array) $data['tags']);
        if (array_key_exists('collection_ids', $data)) $asset->collections()->sync(collect((array) $data['collection_ids'])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all());
        $this->snapshotVersion($asset->fresh(['tags']), $actor->user_id, 'Metadata updated');
        return $asset->fresh(['folder', 'tags', 'collections'])->loadCount('usages');
    }

    /** Create one immutable DAM snapshot and inherit or capture the current binary revision. */
    public function snapshotVersion(MediaAsset $asset, ?int $actorUserId, string $reason): MediaAssetVersion
    {
        $asset->loadMissing('tags');
        $latest = MediaAssetVersion::query()->where('media_asset_id', $asset->id)->latest('version_number')->first();
        if ($latest && ! $latest->binary_available) $latest = $this->ensureVersionBinary($asset, $latest);
        $tags = $asset->tags->pluck('name')->values()->all();
        $payload = [
            'name' => $asset->name,
            'folder_id' => $asset->folder_id,
            'alt_text' => $asset->alt_text,
            'caption' => $asset->caption,
            'copyright_owner' => $asset->copyright_owner,
            'license_type' => $asset->license_type,
            'license_reference' => $asset->license_reference,
            'license_expires_at' => $asset->license_expires_at?->toDateString(),
            'usage_restrictions' => $asset->usage_restrictions,
            'rights_review_at' => $asset->rights_review_at?->toDateString(),
            'focal_x' => $asset->focal_x,
            'focal_y' => $asset->focal_y,
            'tags' => $tags,
        ];
        $sameMetadata = $latest && $latest->name === $payload['name'] && (int) ($latest->folder_id ?? 0) === (int) ($payload['folder_id'] ?? 0)
            && $latest->alt_text === $payload['alt_text'] && $latest->caption === $payload['caption'] && $latest->copyright_owner === $payload['copyright_owner']
            && $latest->license_type === $payload['license_type'] && $latest->license_reference === $payload['license_reference']
            && optional($latest->license_expires_at)->toDateString() === $payload['license_expires_at'] && $latest->usage_restrictions === $payload['usage_restrictions']
            && optional($latest->rights_review_at)->toDateString() === $payload['rights_review_at'] && (int) ($latest->focal_x ?? -1) === (int) ($payload['focal_x'] ?? -1)
            && (int) ($latest->focal_y ?? -1) === (int) ($payload['focal_y'] ?? -1) && ($latest->tags ?? []) === $tags;
        $sameBinary = $latest && $latest->binary_available && hash_equals((string) $latest->checksum_sha256, (string) $asset->checksum_sha256);
        if ($sameMetadata && $sameBinary) return $latest;

        $version = MediaAssetVersion::create([
            'workspace_id' => $asset->workspace_id,
            'media_asset_id' => $asset->id,
            'version_number' => ($latest?->version_number ?? 0) + 1,
            ...$payload,
            ...($sameBinary ? $this->versionBinaryFields($latest) : []),
            'metadata' => ['reason' => $reason],
            'created_by' => $actorUserId,
        ]);
        if (! $sameBinary) $version = $this->ensureVersionBinary($asset, $version);
        return $version;
    }

    /** Replace an asset's binary while preserving its stable ID, public URL and immutable prior version. */
    public function replaceBinary(MediaAsset $asset, UploadedFile $file, WorkspaceMember $actor): MediaAsset
    {
        abort_unless((int) $asset->workspace_id === (int) $actor->workspace_id, 404);
        abort_if($asset->trashed(), 422, 'Restore this media asset before replacing its file.');
        abort_unless($file->isValid(), 422, 'The replacement file is invalid.');
        $maxBytes = max(1, (int) config('workintel.media.max_file_mb', 100)) * 1024 * 1024;
        abort_if((int) $file->getSize() > $maxBytes, 422, 'The replacement file exceeds the workspace media size limit.');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        abort_if(in_array($extension, self::BLOCKED_EXTENSIONS, true), 422, 'This executable file type is not allowed in Media Library.');
        $inspection = $this->uploadSecurity->inspect($file);
        $realPath = (string) $inspection['path'];
        $detectedMime = (string) $inspection['mime'];
        $scan = (array) $inspection['scan'];
        $checksum = hash_file('sha256', $realPath);
        abort_unless(is_string($checksum) && $checksum !== '', 422, 'The replacement checksum could not be calculated safely.');
        $latest = MediaAssetVersion::query()->where('media_asset_id', $asset->id)->latest('version_number')->first();
        if ($latest) $this->ensureVersionBinary($asset, $latest);
        else $this->snapshotVersion($asset, $actor->user_id, 'Pre-replacement snapshot');

        $safeExtension = preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'bin';
        $quarantined = ($scan['status'] ?? null) === 'infected';
        $newPath = ($quarantined ? 'quarantine/' : 'media/').$asset->workspace_id.'/'.now()->format('Y/m').'/'.$asset->uuid.'-'.Str::lower(Str::random(10)).'.'.$safeExtension;
        $disk = Storage::disk('local');$directory = dirname($newPath);
        if (! $disk->directoryExists($directory)) abort_unless($disk->makeDirectory($directory), 500, 'The media storage directory could not be prepared.');
        abort_unless($disk->putFileAs($directory, $file, basename($newPath)), 500, 'The replacement file could not be stored.');
        [$width, $height] = $this->imageDimensions($realPath, $detectedMime);
        $oldDisk = $asset->disk;$oldPath = $asset->path;
        try {
            DB::transaction(function () use ($asset, $file, $newPath, $safeExtension, $detectedMime, $checksum, $width, $height, $quarantined, $scan, $actor): void {
                $asset->update([
                    'original_name' => Str::limit($file->getClientOriginalName(), 255, ''), 'disk' => 'local', 'path' => $newPath,
                    'mime_type' => $detectedMime, 'extension' => $safeExtension, 'size_bytes' => (int) $file->getSize(), 'checksum_sha256' => $checksum,
                    'width' => $width, 'height' => $height, 'duration_ms' => null, 'status' => $quarantined ? 'quarantined' : 'ready',
                    'metadata' => array_merge((array) $asset->metadata, ['security_scan'=>$scan,'browser_mime'=>$file->getClientMimeType(),'binary_replaced_at'=>now()->toIso8601String()]),
                ]);
                $this->snapshotVersion($asset->fresh(['tags']), $actor->user_id, 'Binary replaced');
                $this->clearRenditions($asset);
            });
        } catch (\Throwable $exception) { $disk->delete($newPath);throw $exception; }
        if ($oldPath !== $newPath && Storage::disk($oldDisk)->exists($oldPath)) Storage::disk($oldDisk)->delete($oldPath);
        return $asset->fresh(['folder','tags','collections'])->loadCount(['usages','versions','renditions']);
    }

    /** Restore a prior binary and metadata snapshot by creating a new current version rather than rewriting history. */
    public function restoreVersion(MediaAsset $asset, MediaAssetVersion $version, WorkspaceMember $actor): MediaAsset
    {
        abort_unless((int) $asset->workspace_id === (int) $actor->workspace_id && (int) $version->media_asset_id === (int) $asset->id, 404);
        abort_if($asset->trashed(), 422, 'Restore this asset from Trash before restoring a historical version.');
        $currentLatest = MediaAssetVersion::query()->where('media_asset_id',$asset->id)->latest('version_number')->first();
        if (! $version->binary_available && $currentLatest && (int)$currentLatest->id === (int)$version->id) $version = $this->ensureVersionBinary($asset, $version);
        abort_unless($version->binary_available && $version->binary_path && Storage::disk($version->binary_disk ?: 'local')->exists($version->binary_path), 422, 'This historical version does not have a restorable binary snapshot.');
        abort_unless(($version->binary_status ?: 'ready') === 'ready', 423, 'A quarantined historical binary cannot be restored.');
        $latest = MediaAssetVersion::query()->where('media_asset_id', $asset->id)->latest('version_number')->first();
        if ($latest) $this->ensureVersionBinary($asset, $latest);
        $extension = $version->extension ?: $asset->extension ?: 'bin';
        $newPath = 'media/'.$asset->workspace_id.'/'.now()->format('Y/m').'/'.$asset->uuid.'-restore-'.Str::lower(Str::random(8)).'.'.$extension;
        $source = Storage::disk($version->binary_disk ?: 'local');$target = Storage::disk('local');$directory=dirname($newPath);
        if (! $target->directoryExists($directory)) abort_unless($target->makeDirectory($directory), 500, 'The media storage directory could not be prepared.');
        $stream=$source->readStream($version->binary_path);abort_unless(is_resource($stream),500,'The historical binary could not be read.');
        try { abort_unless($target->writeStream($newPath,$stream),500,'The historical binary could not be restored.'); } finally { if(is_resource($stream))fclose($stream); }
        $oldDisk=$asset->disk;$oldPath=$asset->path;
        try {
            DB::transaction(function () use ($asset,$version,$newPath,$actor): void {
                $folderId=$version->folder_id && MediaFolder::query()->where('workspace_id',$asset->workspace_id)->whereKey($version->folder_id)->exists() ? $version->folder_id : null;
                $asset->update([
                    'name'=>$version->name,'folder_id'=>$folderId,'alt_text'=>$version->alt_text,'caption'=>$version->caption,'copyright_owner'=>$version->copyright_owner,
                    'license_type'=>$version->license_type,'license_reference'=>$version->license_reference,'license_expires_at'=>$version->license_expires_at,'usage_restrictions'=>$version->usage_restrictions,
                    'rights_review_at'=>$version->rights_review_at,'focal_x'=>$version->focal_x,'focal_y'=>$version->focal_y,'disk'=>'local','path'=>$newPath,
                    'original_name'=>$version->original_name ?: $asset->original_name,'mime_type'=>$version->mime_type ?: $asset->mime_type,'extension'=>$version->extension ?: $asset->extension,
                    'size_bytes'=>$version->size_bytes ?? $asset->size_bytes,'checksum_sha256'=>$version->checksum_sha256 ?: $asset->checksum_sha256,'width'=>$version->width,'height'=>$version->height,
                    'duration_ms'=>$version->duration_ms,'status'=>$version->binary_status ?: 'ready','metadata'=>array_merge((array)$asset->metadata,['restored_from_version'=>$version->version_number,'restored_at'=>now()->toIso8601String()]),
                ]);
                $this->syncTags($asset, $version->tags ?? []);
                $this->snapshotVersion($asset->fresh(['tags']),$actor->user_id,'Restored from version '.$version->version_number);
                $this->clearRenditions($asset);
            });
        } catch (\Throwable $exception) { $target->delete($newPath);throw $exception; }
        if($oldPath!==$newPath&&Storage::disk($oldDisk)->exists($oldPath))Storage::disk($oldDisk)->delete($oldPath);
        return $asset->fresh(['folder','tags','collections'])->loadCount(['usages','versions','renditions']);
    }

    /** Generate or reuse one private raster rendition using focal-point-aware contain/cover transforms. */
    public function generateRendition(MediaAsset $asset, WorkspaceMember $actor, array $data): MediaRendition
    {
        abort_unless((int)$asset->workspace_id===(int)$actor->workspace_id,404);abort_unless($asset->category()==='image'&&$asset->status==='ready',422,'Renditions require a ready raster image asset.');
        abort_unless(function_exists('imagecreatefromstring')&&function_exists('imagecopyresampled'),503,'PHP GD is required to generate image renditions on this server.');
        $width=max(32,min(4096,(int)($data['width']??640)));$height=max(32,min(4096,(int)($data['height']??640)));$fit=in_array(($data['fit']??'contain'),['contain','cover'],true)?$data['fit']:'contain';
        $format=in_array(($data['format']??'webp'),['jpeg','png','webp'],true)?$data['format']:'webp';$quality=max(40,min(95,(int)($data['quality']??82)));
        if($format==='webp')abort_unless(function_exists('imagewebp'),503,'This PHP GD build does not support WebP output.');
        $spec=['width'=>$width,'height'=>$height,'fit'=>$fit,'format'=>$format,'quality'=>$quality,'checksum'=>$asset->checksum_sha256,'focal_x'=>$asset->focal_x??50,'focal_y'=>$asset->focal_y??50];$hash=sha1(json_encode($spec));
        $existing=MediaRendition::query()->where('media_asset_id',$asset->id)->where('spec_hash',$hash)->first();if($existing&&Storage::disk($existing->disk)->exists($existing->path))return $existing;
        $bytes=Storage::disk($asset->disk)->get($asset->path);$source=@imagecreatefromstring($bytes);abort_unless($source,422,'This image format cannot be transformed by the server.');
        $sw=imagesx($source);$sh=imagesy($source);$sx=0;$sy=0;$cropW=$sw;$cropH=$sh;$outW=$width;$outH=$height;
        if($fit==='contain'){$scale=min($width/$sw,$height/$sh,1);$outW=max(1,(int)round($sw*$scale));$outH=max(1,(int)round($sh*$scale));}
        else{$targetRatio=$width/$height;$sourceRatio=$sw/$sh;if($sourceRatio>$targetRatio){$cropW=(int)round($sh*$targetRatio);$focus=($asset->focal_x??50)/100;$sx=max(0,min($sw-$cropW,(int)round($focus*$sw-$cropW/2)));}else{$cropH=(int)round($sw/$targetRatio);$focus=($asset->focal_y??50)/100;$sy=max(0,min($sh-$cropH,(int)round($focus*$sh-$cropH/2)));}}
        $canvas=imagecreatetruecolor($outW,$outH);abort_unless($canvas,500,'The rendition canvas could not be created.');if(in_array($format,['png','webp'],true)){imagealphablending($canvas,false);imagesavealpha($canvas,true);$transparent=imagecolorallocatealpha($canvas,0,0,0,127);imagefill($canvas,0,0,$transparent);}imagecopyresampled($canvas,$source,0,0,$sx,$sy,$outW,$outH,$cropW,$cropH);
        $ext=$format==='jpeg'?'jpg':$format;$mime=$format==='jpeg'?'image/jpeg':'image/'.$format;$temp=tempnam(sys_get_temp_dir(),'wi-rendition-');abort_unless($temp!==false,500,'A temporary rendition file could not be created.');
        try{$written=match($format){'jpeg'=>imagejpeg($canvas,$temp,$quality),'png'=>imagepng($canvas,$temp,max(0,min(9,(int)round((100-$quality)/11)))),'webp'=>imagewebp($canvas,$temp,$quality)};abort_unless($written,500,'The rendition encoder failed.');$path='media-renditions/'.$asset->workspace_id.'/'.$asset->uuid.'/'.$hash.'.'.$ext;$disk=Storage::disk('local');$directory=dirname($path);if(!$disk->directoryExists($directory))$disk->makeDirectory($directory);abort_unless($disk->put($path,file_get_contents($temp)),500,'The rendition file could not be stored.');$checksum=hash_file('sha256',$temp);$latest=MediaAssetVersion::query()->where('media_asset_id',$asset->id)->latest('version_number')->first();return MediaRendition::updateOrCreate(['media_asset_id'=>$asset->id,'spec_hash'=>$hash],['workspace_id'=>$asset->workspace_id,'media_asset_version_id'=>$latest?->id,'fit'=>$fit,'width'=>$outW,'height'=>$outH,'format'=>$format,'quality'=>$quality,'disk'=>'local','path'=>$path,'mime_type'=>$mime,'size_bytes'=>filesize($temp)?:0,'checksum_sha256'=>$checksum,'status'=>'ready','metadata'=>$spec,'created_by'=>$actor->user_id]);}finally{imagedestroy($source);imagedestroy($canvas);if(is_string($temp)&&file_exists($temp))@unlink($temp);}
    }

    /** Initiate or resume one server-tracked chunk upload session. */
    public function initiateUpload(Workspace $workspace, User $user, array $data): MediaUploadSession
    {
        $this->cleanupExpiredUploads($workspace->id,$user->id);$max=max(1,(int)config('workintel.media.max_file_mb',100))*1024*1024;$size=(int)$data['size_bytes'];abort_if($size<1||$size>$max,422,'The file exceeds the workspace media size limit.');
        $folderId=!empty($data['folder_id'])?(int)$data['folder_id']:null;if($folderId)MediaFolder::query()->where('workspace_id',$workspace->id)->findOrFail($folderId);
        $extension=strtolower(pathinfo((string)$data['original_name'],PATHINFO_EXTENSION));abort_if(in_array($extension,self::BLOCKED_EXTENSIONS,true),422,'This executable file type is not allowed in Media Library.');
        $requested=(int)($data['chunk_size_bytes']??5*1024*1024);$chunk=max(512*1024,min(8*1024*1024,$requested));$total=(int)ceil($size/$chunk);
        return MediaUploadSession::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'user_id'=>$user->id,'folder_id'=>$folderId,'original_name'=>Str::limit((string)$data['original_name'],255,''),'mime_type'=>Str::limit((string)($data['mime_type']??''),160,'')?:null,'extension'=>Str::limit($extension,20,''),'size_bytes'=>$size,'chunk_size_bytes'=>$chunk,'total_chunks'=>$total,'received_chunks'=>[],'status'=>'active','expires_at'=>now()->addHours(24),'metadata'=>['source'=>'browser-resumable']]);
    }

    /** Store one validated upload chunk and return durable progress for browser resume. */
    public function storeUploadChunk(MediaUploadSession $session, UploadedFile $chunk, int $index, ?string $expectedChecksum=null): MediaUploadSession
    {
        abort_unless($session->status==='active'&&$session->expires_at?->isFuture(),410,'This upload session has expired.');abort_if($index<0||$index>=$session->total_chunks,422,'Invalid upload chunk index.');abort_unless($chunk->isValid(),422,'The upload chunk is invalid.');
        $expectedBytes=min((int)$session->chunk_size_bytes,max(0,(int)$session->size_bytes-($index*(int)$session->chunk_size_bytes)));abort_unless((int)$chunk->getSize()===$expectedBytes,422,'The upload chunk size does not match the negotiated byte range.');$path=$chunk->getPathname();abort_unless(is_readable($path),422,'The upload chunk could not be read.');$checksum=hash_file('sha256',$path);if($expectedChecksum)abort_unless(hash_equals(strtolower($expectedChecksum),strtolower((string)$checksum)),422,'Upload chunk checksum mismatch.');
        $disk=Storage::disk('local');$directory=$this->uploadChunkDirectory($session);if(!$disk->directoryExists($directory))$disk->makeDirectory($directory);abort_unless($disk->putFileAs($directory,$chunk,$index.'.part'),500,'The upload chunk could not be stored.');$received=collect($session->received_chunks??[])->map(fn($value)=>(int)$value)->push($index)->unique()->sort()->values()->all();$session->update(['received_chunks'=>$received]);return $session->fresh();
    }

    /** Assemble a complete resumable upload and pass it through the normal security, checksum and dedupe pipeline. */
    public function completeUpload(MediaUploadSession $session, Workspace $workspace, User $user): array
    {
        abort_unless((int)$session->workspace_id===(int)$workspace->id&&(int)$session->user_id===(int)$user->id,404);abort_unless($session->status==='active'&&$session->expires_at?->isFuture(),410,'This upload session has expired.');$received=collect($session->received_chunks??[])->map(fn($value)=>(int)$value)->unique();abort_unless($received->count()===$session->total_chunks,422,'Upload is incomplete. Resume the missing chunks before completing.');
        $disk=Storage::disk('local');$directory=$this->uploadChunkDirectory($session);$assembly='media-upload-sessions/'.$workspace->id.'/'.$session->uuid.'/assembled.bin';$absolute=$disk->path($assembly);if(!is_dir(dirname($absolute)))@mkdir(dirname($absolute),0775,true);$out=fopen($absolute,'wb');abort_unless(is_resource($out),500,'The upload assembly file could not be created.');
        try{for($i=0;$i<$session->total_chunks;$i++){$chunkPath=$directory.'/'.$i.'.part';abort_unless($disk->exists($chunkPath),422,'A required upload chunk is missing.');$in=$disk->readStream($chunkPath);abort_unless(is_resource($in),500,'An upload chunk could not be read.');stream_copy_to_stream($in,$out);fclose($in);}}finally{fclose($out);}abort_unless((int)filesize($absolute)===(int)$session->size_bytes,422,'The assembled upload size does not match the declared file size.');
        $uploaded=new UploadedFile($absolute,$session->original_name,$session->mime_type?:null,UPLOAD_ERR_OK,true);try{$result=$this->upload($workspace,$user,$uploaded,$session->folder_id,['source'=>'resumable-upload']);$session->update(['status'=>'completed']);$this->deleteUploadSessionFiles($session);return $result;}catch(\Throwable $exception){if(file_exists($absolute))@unlink($absolute);throw $exception;}
    }

    /** Cancel one resumable upload and remove all temporary chunks immediately. */
    public function cancelUpload(MediaUploadSession $session): void { $this->deleteUploadSessionFiles($session);$session->update(['status'=>'canceled']); }

    /** Ensure only the latest version may capture the current binary; older metadata-only history stays immutable. */
    public function ensureVersionBinary(MediaAsset $asset, MediaAssetVersion $version): MediaAssetVersion
    {
        if ($version->binary_available && $version->binary_path && Storage::disk($version->binary_disk ?: 'local')->exists($version->binary_path)) return $version;
        abort_unless((int) $version->media_asset_id === (int) $asset->id, 404);
        $latestId = MediaAssetVersion::query()->where('media_asset_id', $asset->id)->latest('version_number')->value('id');
        abort_unless((int) $latestId === (int) $version->id, 422, 'This older metadata-only version cannot be assigned the current binary.');
        abort_unless(Storage::disk($asset->disk)->exists($asset->path), 422, 'The current media binary is unavailable for version capture.');
        $extension=$asset->extension?:'bin';$path='media-versions/'.$asset->workspace_id.'/'.$asset->uuid.'/v'.$version->version_number.'.'.$extension;$source=Storage::disk($asset->disk);$target=Storage::disk('local');$directory=dirname($path);if(!$target->directoryExists($directory))$target->makeDirectory($directory);$stream=$source->readStream($asset->path);abort_unless(is_resource($stream),500,'The current media binary could not be read.');try{abort_unless($target->writeStream($path,$stream),500,'The immutable version binary could not be stored.');}finally{if(is_resource($stream))fclose($stream);}
        $version->update(['binary_disk'=>'local','binary_path'=>$path,'original_name'=>$asset->original_name,'mime_type'=>$asset->mime_type,'extension'=>$asset->extension,'size_bytes'=>$asset->size_bytes,'checksum_sha256'=>$asset->checksum_sha256,'width'=>$asset->width,'height'=>$asset->height,'duration_ms'=>$asset->duration_ms,'binary_available'=>true,'binary_status'=>$asset->status]);return $version->fresh();
    }

    /** Delete generated renditions whenever the current binary changes. */
    public function clearRenditions(MediaAsset $asset): void { foreach($asset->renditions()->get() as $rendition){if(Storage::disk($rendition->disk)->exists($rendition->path))Storage::disk($rendition->disk)->delete($rendition->path);$rendition->delete();} }

    /** Return reusable binary columns from an already-captured immutable version. */
    private function versionBinaryFields(MediaAssetVersion $version): array { return collect($version->toArray())->only(['binary_disk','binary_path','original_name','mime_type','extension','size_bytes','checksum_sha256','width','height','duration_ms','binary_available','binary_status'])->all(); }
    /** Return the private storage directory for one resumable session. */
    private function uploadChunkDirectory(MediaUploadSession $session): string { return 'media-upload-sessions/'.$session->workspace_id.'/'.$session->uuid.'/chunks'; }
    /** Remove every temporary file belonging to one upload session. */
    private function deleteUploadSessionFiles(MediaUploadSession $session): void { Storage::disk('local')->deleteDirectory('media-upload-sessions/'.$session->workspace_id.'/'.$session->uuid); }
    /** Prune abandoned resumable-upload bytes and age out old terminal session records. */
    public function pruneUploadSessions(int $limit=500): int
    {
        $count=0;MediaUploadSession::query()->where('status','active')->where('expires_at','<',now())->orderBy('id')->limit(max(1,min(2000,$limit)))->get()->each(function(MediaUploadSession $session) use (&$count):void{$this->deleteUploadSessionFiles($session);$session->update(['status'=>'expired']);$count++;});MediaUploadSession::query()->whereIn('status',['completed','canceled','expired'])->where('updated_at','<',now()->subDays(7))->delete();return $count;
    }

    /** Opportunistically expire abandoned upload sessions without requiring a high-frequency scheduler. */
    private function cleanupExpiredUploads(int $workspaceId,int $userId): void { MediaUploadSession::query()->where('workspace_id',$workspaceId)->where('user_id',$userId)->where('status','active')->where('expires_at','<',now())->limit(50)->get()->each(function(MediaUploadSession $session):void{$this->deleteUploadSessionFiles($session);$session->update(['status'=>'expired']);}); }

    /** Creates a unique workspace media folder after validating its parent. */
    public function createFolder(Workspace $workspace, WorkspaceMember $actor, string $name, ?int $parentId = null): MediaFolder
    {
        $name = trim($name);
        abort_if($name === '', 422, 'Folder name is required.');
        if ($parentId) MediaFolder::query()->where('workspace_id', $workspace->id)->findOrFail($parentId);
        $exists = MediaFolder::query()->where('workspace_id', $workspace->id)->where('parent_id', $parentId)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists();
        abort_if($exists, 422, 'A folder with this name already exists here.');
        return MediaFolder::create([
            'workspace_id' => $workspace->id,
            'parent_id' => $parentId,
            'name' => Str::limit($name, 120, ''),
            'slug' => Str::slug($name) ?: 'folder-'.Str::lower(Str::random(6)),
            'created_by' => $actor->user_id,
        ]);
    }

    /** Renames or moves a workspace folder while preventing self-parenting. */
    public function updateFolder(MediaFolder $folder, WorkspaceMember $actor, array $data): MediaFolder
    {
        abort_unless((int) $folder->workspace_id === (int) $actor->workspace_id, 404);
        $name = trim((string) ($data['name'] ?? $folder->name));
        $parentId = array_key_exists('parent_id', $data) ? ($data['parent_id'] ? (int) $data['parent_id'] : null) : $folder->parent_id;
        abort_if($parentId === $folder->id, 422, 'A folder cannot be its own parent.');
        if ($parentId) {
            $parent = MediaFolder::query()->where('workspace_id', $folder->workspace_id)->findOrFail($parentId);
            abort_if($this->isDescendant($parent, $folder->id), 422, 'A folder cannot be moved inside one of its descendants.');
        }
        $exists = MediaFolder::query()->where('workspace_id', $folder->workspace_id)->where('parent_id', $parentId)->where('id', '!=', $folder->id)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists();
        abort_if($exists, 422, 'A folder with this name already exists here.');
        $folder->update(['name' => Str::limit($name, 120, ''), 'slug' => Str::slug($name) ?: $folder->slug, 'parent_id' => $parentId]);
        return $folder->fresh();
    }

    /** Moves an empty folder to Trash after verifying that it contains no active assets or folders. */
    public function trashFolder(MediaFolder $folder, WorkspaceMember $actor): void
    {
        abort_unless((int) $folder->workspace_id === (int) $actor->workspace_id, 404);
        abort_if($folder->assets()->exists() || $folder->children()->exists(), 422, 'Move or delete the contents of this folder before trashing it.');
        $folder->delete();
    }

    /** Assigns normalized workspace tags to an asset. */
    public function syncTags(MediaAsset $asset, array $names): void
    {
        $ids = collect($names)->map(fn ($name) => trim((string) $name))->filter()->unique(fn ($name) => mb_strtolower($name))->take(20)->map(function (string $name) use ($asset) {
            $tag = MediaTag::firstOrCreate(['workspace_id' => $asset->workspace_id, 'name' => Str::limit($name, 80, '')]);
            return $tag->id;
        });
        $asset->tags()->sync($ids->all());
    }

    /** Registers one active usage so in-use media cannot be accidentally trashed or purged. */
    public function registerUsage(MediaAsset $asset, string $resourceType, ?int $resourceId, ?string $field, ?string $label, ?int $actorUserId): MediaUsage
    {
        return MediaUsage::firstOrCreate([
            'workspace_id' => $asset->workspace_id,
            'media_asset_id' => $asset->id,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'field' => $field,
        ], ['label' => $label, 'created_by' => $actorUserId]);
    }

    /** Replaces the current user's avatar with one image asset from the active workspace. */
    public function setAvatar(User $user, WorkspaceMember $member, MediaAsset $asset, ?int $actorUserId = null): MediaAsset
    {
        abort_unless((int) $asset->workspace_id === (int) $member->workspace_id, 404);
        abort_unless($asset->category() === 'image', 422, 'Profile photos must use an image asset.');
        abort_unless($asset->status === 'ready', 422, 'A quarantined or unavailable asset cannot be used as a profile photo.');
        abort_if($asset->trashed(), 422, 'A trashed media asset cannot be used as a profile photo.');

        return DB::transaction(function () use ($user, $member, $asset, $actorUserId) {
            $previousId = $user->avatar_media_id;
            MediaUsage::query()->where('resource_type', 'user')->where('resource_id', $user->id)->where('field', 'avatar')->delete();
            $asset->update(['visibility' => 'public']);
            $this->registerUsage($asset, 'user', $user->id, 'avatar', trim($user->first_name.' '.$user->last_name).' profile photo', $actorUserId ?? $user->id);
            $user->update(['avatar_media_id' => $asset->id, 'avatar_url' => '/api/v1/media/public/'.$asset->uuid]);
            if ($previousId && $previousId !== $asset->id) $this->revertUnusedPublicAsset((int) $previousId);
            return $asset->fresh();
        });
    }

    /** Removes the current user's media-backed avatar and releases its usage record. */
    public function clearAvatar(User $user): void
    {
        DB::transaction(function () use ($user) {
            $previousId = $user->avatar_media_id;
            MediaUsage::query()->where('resource_type', 'user')->where('resource_id', $user->id)->where('field', 'avatar')->delete();
            $user->update(['avatar_media_id' => null, 'avatar_url' => null]);
            if ($previousId) $this->revertUnusedPublicAsset((int) $previousId);
        });
    }

    /** Permanently removes a trashed media asset plus its private current, version and rendition binaries. */
    public function purge(MediaAsset $asset): void
    {
        abort_unless($asset->trashed(), 422, 'Only media in Trash can be permanently deleted.');
        abort_if($asset->usages()->exists(), 409, 'This media asset is still in use. Remove its usages before permanent deletion.');
        if (Storage::disk($asset->disk)->exists($asset->path)) Storage::disk($asset->disk)->delete($asset->path);
        foreach ($asset->versions()->get() as $version) if ($version->binary_path && Storage::disk($version->binary_disk ?: 'local')->exists($version->binary_path)) Storage::disk($version->binary_disk ?: 'local')->delete($version->binary_path);
        $this->clearRenditions($asset);
        $asset->forceDelete();
    }

    /** Returns image pixel dimensions when the uploaded MIME type is an image. */
    private function imageDimensions(string $path, string $mime): array
    {
        if (! str_starts_with(strtolower($mime), 'image/')) return [null, null];
        $info = @getimagesize($path);
        return $info ? [(int) $info[0], (int) $info[1]] : [null, null];
    }

    /** Returns true when the candidate folder lives below the supplied ancestor ID. */
    private function isDescendant(MediaFolder $candidate, int $ancestorId): bool
    {
        $seen = [];
        while ($candidate->parent_id) {
            if ((int) $candidate->parent_id === $ancestorId) return true;
            if (isset($seen[$candidate->parent_id])) return true;
            $seen[$candidate->parent_id] = true;
            $candidate = MediaFolder::withTrashed()->find($candidate->parent_id);
            if (! $candidate) break;
        }
        return false;
    }

    /** Returns an old public avatar asset to private visibility when it no longer has usages. */
    private function revertUnusedPublicAsset(int $assetId): void
    {
        $asset = MediaAsset::withTrashed()->find($assetId);
        if ($asset && ! $asset->usages()->exists() && $asset->visibility === 'public') $asset->update(['visibility' => 'private']);
    }
}
