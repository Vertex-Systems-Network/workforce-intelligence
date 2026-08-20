<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\MediaCollection;
use App\Models\MediaAssetVersion;
use App\Models\MediaRendition;
use App\Models\MediaUploadSession;
use App\Models\MediaFavorite;
use App\Models\MediaFolder;
use App\Models\MediaTag;
use App\Services\Media\MediaLibraryService;
use App\Services\Lifecycle\DataLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Provides workspace Media Library browsing, upload, metadata and avatar endpoints. */
class MediaController extends Controller
{
    /** Injects the shared media-library domain service. */
    public function __construct(private readonly MediaLibraryService $media, private readonly DataLifecycleService $lifecycle) {}

    /** Returns paginated DAM assets with folders, collections, favorites and operational summary counts. */
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        $data = $request->validate([
            'search' => 'nullable|string|max:180',
            'category' => ['nullable', Rule::in(['image', 'video', 'audio', 'document', 'other'])],
            'folder_id' => 'nullable|integer|min:1',
            'tag_id' => 'nullable|integer|min:1',
            'collection_id' => 'nullable|integer|min:1',
            'favorite' => 'nullable|boolean',
            'recent' => 'nullable|boolean',
            'trash' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:10|max:100',
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name', 'size'])],
            'rights' => ['nullable', Rule::in(['attention','expired','expiring','review','unclassified','clear'])],
        ]);

        $query = ($data['trash'] ?? false) ? MediaAsset::onlyTrashed() : MediaAsset::query();
        $query->where('workspace_id', $workspace->id)
            ->with(['folder:id,name', 'tags:id,name,color', 'collections' => function ($collectionQuery) use ($member): void {
                if (! $member->hasPermission('media.manage')) $collectionQuery->where(function ($scope) use ($member): void { $scope->where('visibility','workspace')->orWhere('created_by',$member->user_id)->orWhereHas('members',fn($q)=>$q->where('workspace_members.id',$member->id)); });
            }])
            ->withCount(['usages', 'versions', 'renditions'])
            ->withExists(['favorites as is_favorite' => fn ($favorite) => $favorite->where('workspace_member_id', $member->id)]);
        if ($search = trim((string) ($data['search'] ?? ''))) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('original_name', 'like', '%'.$search.'%')->orWhere('alt_text', 'like', '%'.$search.'%')->orWhere('caption', 'like', '%'.$search.'%')->orWhereHas('tags', fn ($tag) => $tag->where('name', 'like', '%'.$search.'%')));
        }
        if (! empty($data['folder_id'])) $query->where('folder_id', (int) $data['folder_id']);
        if (! empty($data['tag_id'])) $query->whereHas('tags', fn ($q) => $q->where('media_tags.id', (int) $data['tag_id']));
        if (! empty($data['collection_id'])) {
            $collectionId=(int)$data['collection_id'];abort_unless($this->collectionQuery((int)$workspace->id,$member)->whereKey($collectionId)->exists(),404);
            $query->whereHas('collections', fn ($q) => $q->where('media_collections.id', $collectionId));
        }
        if (! empty($data['favorite'])) $query->whereHas('favorites', fn ($q) => $q->where('workspace_member_id', $member->id));
        if (! empty($data['recent'])) $query->where('updated_at', '>=', now()->subDays(30));
        if (! empty($data['category'])) {
            $category = $data['category'];
            $query->where(function ($q) use ($category) {
                if ($category === 'image') $q->where('mime_type', 'like', 'image/%');
                elseif ($category === 'video') $q->where('mime_type', 'like', 'video/%');
                elseif ($category === 'audio') $q->where('mime_type', 'like', 'audio/%');
                elseif ($category === 'document') $q->where(fn ($sub) => $sub->where('mime_type', 'like', 'text/%')->orWhere('mime_type', 'like', '%pdf%')->orWhere('mime_type', 'like', '%document%')->orWhere('mime_type', 'like', '%sheet%')->orWhere('mime_type', 'like', '%presentation%'));
                else $q->where(fn ($sub) => $sub->whereNull('mime_type')->orWhereRaw("mime_type NOT LIKE 'image/%' AND mime_type NOT LIKE 'video/%' AND mime_type NOT LIKE 'audio/%' AND mime_type NOT LIKE 'text/%' AND mime_type NOT LIKE '%pdf%' AND mime_type NOT LIKE '%document%' AND mime_type NOT LIKE '%sheet%' AND mime_type NOT LIKE '%presentation%'") );
            });
        }

        if (! empty($data['rights'])) {
            $today=now()->toDateString();$soon=now()->addDays(30)->toDateString();$rights=$data['rights'];
            $query->where(function($q) use($rights,$today,$soon):void{
                if($rights==='expired')$q->whereNotNull('license_expires_at')->whereDate('license_expires_at','<',$today);
                elseif($rights==='expiring')$q->whereBetween('license_expires_at',[$today,$soon]);
                elseif($rights==='review')$q->whereNotNull('rights_review_at')->whereDate('rights_review_at','<=',$today);
                elseif($rights==='unclassified')$q->whereNull('copyright_owner')->whereNull('license_type')->whereNull('license_reference');
                elseif($rights==='attention')$q->where(fn($a)=>$a->whereDate('license_expires_at','<',$soon)->orWhereDate('rights_review_at','<=',$today)->orWhere(fn($u)=>$u->whereNull('copyright_owner')->whereNull('license_type')->whereNull('license_reference')));
                elseif($rights==='clear')$q->where(fn($a)=>$a->whereNull('license_expires_at')->orWhereDate('license_expires_at','>',$soon))->where(fn($a)=>$a->whereNull('rights_review_at')->orWhereDate('rights_review_at','>',$today))->where(fn($a)=>$a->whereNotNull('copyright_owner')->orWhereNotNull('license_type')->orWhereNotNull('license_reference'));
            });
        }

        match ($data['sort'] ?? 'newest') {
            'oldest' => $query->oldest('created_at'),
            'name' => $query->orderBy('name')->orderBy('id'),
            'size' => $query->orderByDesc('size_bytes')->orderByDesc('id'),
            default => $query->latest('updated_at')->latest('id'),
        };

        $paginator = $query->paginate((int) ($data['per_page'] ?? 36));
        $folders = MediaFolder::query()->where('workspace_id', $workspace->id)->withCount(['assets', 'children'])->orderBy('sort_order')->orderBy('name')->get();
        $tags = MediaTag::query()->where('workspace_id', $workspace->id)->withCount('assets')->orderBy('name')->get();
        $collectionQuery = $this->collectionQuery((int)$workspace->id,$member)->withCount('assets')->orderBy('name');if($member->hasPermission('media.manage'))$collectionQuery->with(['members:id']);$collections=$collectionQuery->get()->map(function(MediaCollection $collection) use($member){$collection->setAttribute('shared_member_ids',$member->hasPermission('media.manage')?$collection->members->pluck('id')->values()->all():[]);unset($collection->members);return $collection;});
        $activeQuery = MediaAsset::query()->where('workspace_id', $workspace->id);
        return response()->json([
            'data' => collect($paginator->items())->map(fn (MediaAsset $asset) => $this->payload($asset))->all(),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
            'folders' => $folders,
            'tags' => $tags,
            'collections' => $collections,
            'summary' => [
                'active' => (clone $activeQuery)->count(),
                'trash' => MediaAsset::onlyTrashed()->where('workspace_id', $workspace->id)->count(),
                'bytes' => (int) (clone $activeQuery)->sum('size_bytes'),
                'favorites' => MediaFavorite::query()->where('workspace_id', $workspace->id)->where('workspace_member_id', $member->id)->whereHas('asset', fn ($asset) => $asset->whereNull('deleted_at'))->count(),
                'recent' => (clone $activeQuery)->where('updated_at', '>=', now()->subDays(30))->count(),
                'collections' => $collections->count(),
                'rights_attention' => (clone $activeQuery)->where(fn($q)=>$q->whereDate('license_expires_at','<',now()->addDays(30)->toDateString())->orWhereDate('rights_review_at','<=',now()->toDateString())->orWhere(fn($u)=>$u->whereNull('copyright_owner')->whereNull('license_type')->whereNull('license_reference')))->count(),
            ],
        ]);
    }

    /** Uploads one or more media files, isolating per-file failures so one bad asset does not discard successful uploads. */
    public function store(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $request->validate(['folder_id' => 'nullable|integer|min:1']);
        $files = array_values(array_filter((array) $request->file('files', [])));
        if ($request->hasFile('file')) $files[] = $request->file('file');
        abort_if(count($files) > 20, 422, 'Upload at most 20 files at a time.');
        abort_unless(count($files) > 0, 422, 'No files reached Media Library. Check PHP upload_max_filesize/post_max_size and the browser-selected files.');

        $rows = [];
        $failures = [];
        foreach ($files as $file) {
            try {
                $result = $this->media->upload($workspace, $request->user(), $file, $request->integer('folder_id') ?: null);
                $rows[] = ['asset' => $this->payload($result['asset']), 'duplicate' => $result['duplicate']];
            } catch (\Throwable $exception) {
                $status = method_exists($exception, 'getStatusCode') ? (int) $exception->getStatusCode() : 500;
                if ($status >= 500) report($exception);
                $failures[] = ['name' => method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : 'upload', 'message' => $exception->getMessage() ?: 'The file could not be uploaded.'];
            }
        }
        $processed = count($rows);
        $message = $processed.' media file'.($processed === 1 ? '' : 's').' processed.';
        if ($failures) $message .= ' '.count($failures).' failed.';
        return response()->json(['data' => $rows, 'failures' => $failures, 'message' => $message, 'limits' => $this->uploadLimits()], $rows ? ($failures ? 207 : 201) : 422);
    }

    /** Returns non-secret upload limits and storage health used by the high-level Media Library uploader. */
    public function capabilities(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $disk = Storage::disk('local');
        $probe = 'media/'.$workspace->id.'/.upload-probe-'.bin2hex(random_bytes(4));
        $writable = false;
        try {
            $writable = $disk->put($probe, 'ok') && $disk->exists($probe);
            $disk->delete($probe);
        } catch (\Throwable) {
            $writable = false;
        }
        return response()->json(['data' => array_merge($this->uploadLimits(), ['disk'=>'local','writable'=>$writable,'max_files_per_request'=>20,'resumable_uploads'=>true,'chunk_size_bytes'=>5*1024*1024,'renditions_available'=>function_exists('imagecreatefromstring')])]);
    }

    /** Normalize application and PHP upload limits into byte values without exposing php.ini paths. */
    private function uploadLimits(): array
    {
        return [
            'max_file_mb' => max(1, (int) config('workintel.media.max_file_mb', 100)),
            'php_upload_max_bytes' => $this->iniBytes((string) ini_get('upload_max_filesize')),
            'php_post_max_bytes' => $this->iniBytes((string) ini_get('post_max_size')),
        ];
    }

    /** Convert a PHP shorthand byte value such as 64M or 1G to bytes. */
    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') return 0;
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        return (int) match ($unit) { 'g' => $number * 1024 * 1024 * 1024, 'm' => $number * 1024 * 1024, 'k' => $number * 1024, default => $number };
    }

    /** Updates editable asset metadata and tag assignments. */
    public function update(Request $request, MediaAsset $asset): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $asset->workspace_id === (int) $workspace->id, 404);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:180',
            'folder_id' => 'nullable|integer|min:1',
            'alt_text' => 'nullable|string|max:300',
            'caption' => 'nullable|string|max:2000',
            'copyright_owner' => 'nullable|string|max:180',
            'license_type' => 'nullable|string|max:80',
            'license_reference' => 'nullable|string|max:255',
            'license_expires_at' => 'nullable|date',
            'usage_restrictions' => 'nullable|string|max:4000',
            'rights_review_at' => 'nullable|date',
            'focal_x' => 'nullable|integer|min:0|max:100',
            'focal_y' => 'nullable|integer|min:0|max:100',
            'collection_ids' => 'sometimes|array|max:30',
            'collection_ids.*' => 'integer|min:1',
            'tags' => 'sometimes|array|max:20',
            'tags.*' => 'string|max:80',
        ]);
        return response()->json(['data' => $this->payload($this->media->update($asset, $data, $request->attributes->get('workspaceMember'))), 'message' => 'Media details saved.']);
    }

    /** Streams one private media asset inline after workspace authorization. */
    public function content(Request $request, MediaAsset $asset): BinaryFileResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $asset->workspace_id === (int) $workspace->id, 404);
        abort_unless($asset->status === 'ready', 423, 'This media asset is quarantined or unavailable.');
        abort_unless(Storage::disk($asset->disk)->exists($asset->path), 404);
        return response()->file(Storage::disk($asset->disk)->path($asset->path), [
            'Content-Type' => $asset->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($asset->original_name).'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** Downloads one private media asset using its original filename. */
    public function download(Request $request, MediaAsset $asset)
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $asset->workspace_id === (int) $workspace->id, 404);
        abort_unless($asset->status === 'ready', 423, 'This media asset is quarantined or unavailable.');
        abort_unless(Storage::disk($asset->disk)->exists($asset->path), 404);
        return Storage::disk($asset->disk)->download($asset->path, $asset->original_name, ['Content-Type' => $asset->mime_type ?: 'application/octet-stream']);
    }

    /** Streams only explicitly public non-trashed media by unguessable UUID without exposing storage paths. */
    public function publicContent(string $uuid): BinaryFileResponse
    {
        $asset = MediaAsset::query()->where('uuid', $uuid)->where('visibility', 'public')->where('status', 'ready')->firstOrFail();
        abort_unless(Storage::disk($asset->disk)->exists($asset->path), 404);
        return response()->file(Storage::disk($asset->disk)->path($asset->path), [
            'Content-Type' => $asset->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** Marks one active media asset as a personal favorite for the current workspace member. */
    public function favorite(Request $request, MediaAsset $asset): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        abort_unless((int) $asset->workspace_id === (int) $workspace->id && ! $asset->trashed(), 404);
        MediaFavorite::firstOrCreate(['workspace_id' => $workspace->id, 'workspace_member_id' => $member->id, 'media_asset_id' => $asset->id]);
        return response()->json(['message' => 'Media added to Favorites.']);
    }

    /** Removes one member-specific favorite without changing the asset itself. */
    public function unfavorite(Request $request, MediaAsset $asset): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        abort_unless((int) $asset->workspace_id === (int) $workspace->id, 404);
        MediaFavorite::query()->where('workspace_id', $workspace->id)->where('workspace_member_id', $member->id)->where('media_asset_id', $asset->id)->delete();
        return response()->json(['message' => 'Media removed from Favorites.']);
    }

    /** Returns active resource usages so operators can understand where an asset is referenced. */
    public function usages(Request $request, MediaAsset $asset): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $asset->workspace_id === (int) $workspace->id, 404);
        $rows = $asset->usages()->latest('updated_at')->get(['id','resource_type','resource_id','field','label','created_at','updated_at']);
        return response()->json(['data' => $rows]);
    }

    /** Returns immutable DAM metadata versions for audit and future restore workflows. */
    public function versions(Request $request, MediaAsset $asset): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $asset->workspace_id === (int) $workspace->id, 404);
        $rows = $asset->versions()->with(['creator:id,first_name,last_name','folder:id,name'])->limit(100)->get()->map(fn ($version) => [
            'id'=>$version->id,'version_number'=>$version->version_number,'name'=>$version->name,'alt_text'=>$version->alt_text,'caption'=>$version->caption,
            'copyright_owner'=>$version->copyright_owner,'license_type'=>$version->license_type,'license_reference'=>$version->license_reference,'license_expires_at'=>$version->license_expires_at?->toDateString(),'usage_restrictions'=>$version->usage_restrictions,'rights_review_at'=>$version->rights_review_at?->toDateString(),
            'focal_x'=>$version->focal_x,'focal_y'=>$version->focal_y,'tags'=>$version->tags??[],'folder'=>$version->folder?['id'=>$version->folder->id,'name'=>$version->folder->name]:null,
            'binary_available'=>(bool)$version->binary_available,'binary_status'=>$version->binary_status,'size_bytes'=>$version->size_bytes,'mime_type'=>$version->mime_type,'checksum_sha256'=>$version->checksum_sha256,
            'download_url'=>$version->binary_available&&($version->binary_status?:'ready')==='ready'?'/api/v1/media/'.$asset->id.'/versions/'.$version->id.'/download':null,
            'reason'=>$version->metadata['reason']??null,'creator'=>$version->creator?trim($version->creator->first_name.' '.$version->creator->last_name):null,'created_at'=>$version->created_at?->toIso8601String(),
        ])->values();
        return response()->json(['data' => $rows]);
    }

    /** Creates one reusable DAM collection that can span physical folders. */
    public function storeCollection(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        $data = $request->validate(['name'=>'required|string|max:120','description'=>'nullable|string|max:1000','visibility'=>['nullable',Rule::in(['workspace','restricted'])],'member_ids'=>'nullable|array|max:100','member_ids.*'=>'integer|min:1']);
        $name = trim($data['name']);
        abort_if(MediaCollection::query()->where('workspace_id',$workspace->id)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists(), 422, 'A collection with this name already exists.');
        $collection = MediaCollection::create(['workspace_id'=>$workspace->id,'name'=>$name,'description'=>trim((string)($data['description']??''))?:null,'visibility'=>$data['visibility']??'workspace','created_by'=>$member->user_id]);
        $this->syncCollectionMembers($collection,$workspace->id,(array)($data['member_ids']??[]));
        return response()->json(['data'=>$this->collectionPayload($collection->fresh(), $request->attributes->get('workspaceMember')),'message'=>'Media collection created.'], 201);
    }

    /** Renames or describes one workspace DAM collection. */
    public function updateCollection(Request $request, MediaCollection $collection): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int)$collection->workspace_id===(int)$workspace->id,404);
        $data=$request->validate(['name'=>'sometimes|required|string|max:120','description'=>'nullable|string|max:1000','visibility'=>['nullable',Rule::in(['workspace','restricted'])],'member_ids'=>'nullable|array|max:100','member_ids.*'=>'integer|min:1']);
        if(isset($data['name'])){$name=trim($data['name']);abort_if(MediaCollection::query()->where('workspace_id',$workspace->id)->where('id','!=',$collection->id)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists(),422,'A collection with this name already exists.');$data['name']=$name;}
        if(array_key_exists('description',$data))$data['description']=trim((string)$data['description'])?:null;
        $members=(array)($data['member_ids']??[]);unset($data['member_ids']);$collection->update($data);if($request->has('member_ids'))$this->syncCollectionMembers($collection,$workspace->id,$members);
        return response()->json(['data'=>$this->collectionPayload($collection->fresh(), $request->attributes->get('workspaceMember')),'message'=>'Media collection saved.']);
    }

    /** Deletes one collection container while leaving its assets untouched. */
    public function destroyCollection(Request $request, MediaCollection $collection): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');
        abort_unless((int)$collection->workspace_id===(int)$workspace->id,404);
        $collection->delete();
        return response()->json(['message'=>'Media collection deleted.']);
    }

    /** Returns minimal member choices for restricted DAM collection sharing. */
    public function collectionMembers(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$rows=\App\Models\WorkspaceMember::query()->with('user:id,first_name,last_name')->where('workspace_id',$workspace->id)->where('status','active')->orderBy('id')->get()->map(fn($member)=>['id'=>$member->id,'name'=>trim(($member->user?->first_name??'').' '.($member->user?->last_name??''))?:'Member #'.$member->id]);return response()->json(['data'=>$rows]);
    }

    /** Replaces an asset binary while keeping its stable media identity and full prior version. */
    public function replace(Request $request, MediaAsset $asset): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$asset->workspace_id===(int)$workspace->id,404);$request->validate(['file'=>'required|file']);$updated=$this->media->replaceBinary($asset,$request->file('file'),$request->attributes->get('workspaceMember'));return response()->json(['data'=>$this->payload($updated),'message'=>'Media file replaced and the previous binary was preserved in version history.']);
    }

    /** Restores one immutable binary/metadata version as a new current version. */
    public function restoreVersion(Request $request, MediaAsset $asset, MediaAssetVersion $version): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$asset->workspace_id===(int)$workspace->id&&(int)$version->media_asset_id===(int)$asset->id,404);$updated=$this->media->restoreVersion($asset,$version,$request->attributes->get('workspaceMember'));return response()->json(['data'=>$this->payload($updated),'message'=>'Historical media version restored as a new current version.']);
    }

    /** Downloads a preserved historical binary without exposing its private version-store path. */
    public function downloadVersion(Request $request, MediaAsset $asset, MediaAssetVersion $version)
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$asset->workspace_id===(int)$workspace->id&&(int)$version->media_asset_id===(int)$asset->id,404);abort_unless($version->binary_available&&$version->binary_path,404,'This version has no preserved binary.');abort_unless(($version->binary_status?:'ready')==='ready',423,'Quarantined historical binaries cannot be downloaded.');$disk=Storage::disk($version->binary_disk?:'local');abort_unless($disk->exists($version->binary_path),404);return $disk->download($version->binary_path,$version->original_name?:$asset->original_name,['Content-Type'=>$version->mime_type?:'application/octet-stream']);
    }

    /** Returns generated renditions for the current asset binary. */
    public function renditions(Request $request, MediaAsset $asset): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$asset->workspace_id===(int)$workspace->id,404);return response()->json(['data'=>$asset->renditions()->latest('created_at')->get()->map(fn(MediaRendition $row)=>$this->renditionPayload($row))->values()]);
    }

    /** Generates or reuses one focal-point-aware private image rendition. */
    public function storeRendition(Request $request, MediaAsset $asset): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$asset->workspace_id===(int)$workspace->id,404);$data=$request->validate(['width'=>'required|integer|min:32|max:4096','height'=>'required|integer|min:32|max:4096','fit'=>['nullable',Rule::in(['contain','cover'])],'format'=>['nullable',Rule::in(['jpeg','png','webp'])],'quality'=>'nullable|integer|min:40|max:95']);$row=$this->media->generateRendition($asset,$request->attributes->get('workspaceMember'),$data);return response()->json(['data'=>$this->renditionPayload($row),'message'=>'Image rendition ready.'],201);
    }

    /** Streams one generated rendition after the same workspace permission check as its source asset. */
    public function renditionContent(Request $request, MediaAsset $asset, MediaRendition $rendition): BinaryFileResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$asset->workspace_id===(int)$workspace->id&&(int)$rendition->media_asset_id===(int)$asset->id,404);$disk=Storage::disk($rendition->disk);abort_unless($disk->exists($rendition->path),404);return response()->file($disk->path($rendition->path),['Content-Type'=>$rendition->mime_type,'Content-Disposition'=>'inline','Cache-Control'=>'private, max-age=86400']);
    }

    /** Initiates a durable resumable upload session that survives request/PHP post-size boundaries. */
    public function initiateUpload(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$data=$request->validate(['original_name'=>'required|string|max:255','mime_type'=>'nullable|string|max:160','size_bytes'=>'required|integer|min:1','folder_id'=>'nullable|integer|min:1','chunk_size_bytes'=>'nullable|integer|min:524288|max:8388608']);$session=$this->media->initiateUpload($workspace,$request->user(),$data);return response()->json(['data'=>$this->uploadSessionPayload($session)],201);
    }

    /** Returns resumable upload progress so the same browser can continue missing chunks after interruption. */
    public function uploadSession(Request $request, MediaUploadSession $uploadSession): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$uploadSession->workspace_id===(int)$workspace->id&&(int)$uploadSession->user_id===(int)$request->user()->id,404);return response()->json(['data'=>$this->uploadSessionPayload($uploadSession)]);
    }

    /** Stores one chunk in a resumable upload session. */
    public function uploadChunk(Request $request, MediaUploadSession $uploadSession, int $index): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$uploadSession->workspace_id===(int)$workspace->id&&(int)$uploadSession->user_id===(int)$request->user()->id,404);$request->validate(['chunk'=>'required|file','checksum_sha256'=>'nullable|string|size:64']);$session=$this->media->storeUploadChunk($uploadSession,$request->file('chunk'),$index,$request->input('checksum_sha256'));return response()->json(['data'=>$this->uploadSessionPayload($session)]);
    }

    /** Completes an upload session and returns the same asset contract as direct Media Library uploads. */
    public function completeUpload(Request $request, MediaUploadSession $uploadSession): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$uploadSession->workspace_id===(int)$workspace->id&&(int)$uploadSession->user_id===(int)$request->user()->id,404);$result=$this->media->completeUpload($uploadSession,$workspace,$request->user());return response()->json(['data'=>[['asset'=>$this->payload($result['asset']),'duplicate'=>$result['duplicate']]],'failures'=>[],'message'=>'Resumable media upload completed.'],201);
    }

    /** Cancels one resumable upload and deletes temporary chunks. */
    public function cancelUpload(Request $request, MediaUploadSession $uploadSession): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$uploadSession->workspace_id===(int)$workspace->id&&(int)$uploadSession->user_id===(int)$request->user()->id,404);$this->media->cancelUpload($uploadSession);return response()->json(['message'=>'Resumable upload canceled.']);
    }

    /** Applies one bounded DAM operation to many assets while isolating per-asset failures. */
    public function bulk(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$member=$request->attributes->get('workspaceMember');$data=$request->validate(['action'=>['required',Rule::in(['favorite','unfavorite','move_folder','add_collection','remove_collection','trash','restore'])],'asset_ids'=>'required|array|min:1|max:100','asset_ids.*'=>'integer|min:1','folder_id'=>'nullable|integer|min:1','collection_id'=>'nullable|integer|min:1']);$processed=[];$failures=[];
        if(in_array($data['action'],['move_folder'],true)&&!empty($data['folder_id']))MediaFolder::query()->where('workspace_id',$workspace->id)->findOrFail((int)$data['folder_id']);
        $collection=null;if(in_array($data['action'],['add_collection','remove_collection'],true))$collection=MediaCollection::query()->where('workspace_id',$workspace->id)->findOrFail((int)($data['collection_id']??0));
        foreach(array_values(array_unique(array_map('intval',$data['asset_ids']))) as $id){try{if($data['action']==='trash')$this->lifecycle->trash($workspace,$member,'media',$id);elseif($data['action']==='restore'){$member->hasPermission('trash.restore')||abort(403,'Trash restore permission is required.');$this->lifecycle->restore($workspace,$member,'media',$id);}else{$asset=MediaAsset::query()->where('workspace_id',$workspace->id)->findOrFail($id);match($data['action']){'favorite'=>MediaFavorite::firstOrCreate(['workspace_id'=>$workspace->id,'workspace_member_id'=>$member->id,'media_asset_id'=>$id]),'unfavorite'=>MediaFavorite::query()->where('workspace_member_id',$member->id)->where('media_asset_id',$id)->delete(),'move_folder'=>$this->media->update($asset,['folder_id'=>$data['folder_id']??null],$member),'add_collection'=>$asset->collections()->syncWithoutDetaching([$collection->id]),'remove_collection'=>$asset->collections()->detach($collection->id),default=>null};}$processed[]=$id;}catch(\Throwable $exception){$failures[]=['id'=>$id,'message'=>$exception->getMessage()?:'Bulk media action failed.'];}}
        return response()->json(['processed'=>$processed,'failures'=>$failures,'message'=>count($processed).' media asset(s) updated.'], $processed?($failures?207:200):422);
    }

    /** Creates a media-library folder. */
    public function storeFolder(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate(['name' => 'required|string|max:120', 'parent_id' => 'nullable|integer|min:1']);
        $folder = $this->media->createFolder($workspace, $request->attributes->get('workspaceMember'), $data['name'], $data['parent_id'] ?? null);
        return response()->json(['data' => $folder, 'message' => 'Media folder created.'], 201);
    }

    /** Renames or moves a media-library folder. */
    public function updateFolder(Request $request, MediaFolder $folder): JsonResponse
    {
        $data = $request->validate(['name' => 'sometimes|required|string|max:120', 'parent_id' => 'nullable|integer|min:1']);
        return response()->json(['data' => $this->media->updateFolder($folder, $request->attributes->get('workspaceMember'), $data), 'message' => 'Media folder saved.']);
    }

    /** Moves an empty media folder to Trash. */
    public function destroyFolder(Request $request, MediaFolder $folder): JsonResponse
    {
        $this->media->trashFolder($folder, $request->attributes->get('workspaceMember'));
        return response()->json(['message' => 'Media folder moved to Trash.']);
    }

    /** Sets the signed-in user's avatar from an existing workspace image asset. */
    public function setAvatar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'media_asset_id' => 'nullable|integer|min:1|required_without:file',
            'file' => 'nullable|file|image|max:10240|required_without:media_asset_id',
        ]);
        $workspace = $request->attributes->get('workspace');
        if ($request->hasFile('file')) {
            $uploaded = $this->media->upload($workspace, $request->user(), $request->file('file'), null, ['source' => 'profile-avatar', 'name' => trim($request->user()->first_name.' '.$request->user()->last_name).' avatar']);
            $asset = $uploaded['asset'];
        } else {
            $member = $request->attributes->get('workspaceMember');
            abort_unless($member->hasPermission('media.view') || $member->hasPermission('media.manage'), 403, 'You do not have permission to select an existing Media Library asset.');
            $asset = MediaAsset::query()->where('workspace_id', $workspace->id)->findOrFail((int) $data['media_asset_id']);
        }
        $asset = $this->media->setAvatar($request->user(), $request->attributes->get('workspaceMember'), $asset);
        return response()->json(['data' => ['avatar_url' => '/api/v1/media/public/'.$asset->uuid, 'media_asset_id' => $asset->id], 'message' => 'Profile photo updated.']);
    }

    /** Removes the signed-in user's media-backed profile photo. */
    public function clearAvatar(Request $request): JsonResponse
    {
        $this->media->clearAvatar($request->user());
        return response()->json(['message' => 'Profile photo removed.']);
    }

    /** Sets a workspace member's profile photo from Media Library for authorized people administrators. */
    public function setMemberAvatar(Request $request, \App\Models\WorkspaceMember $member): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $member->workspace_id === (int) $workspace->id, 404);
        $data = $request->validate(['media_asset_id' => 'required|integer|min:1']);
        $asset = MediaAsset::query()->where('workspace_id', $workspace->id)->findOrFail((int) $data['media_asset_id']);
        $member->loadMissing('user');
        abort_unless($member->user, 422, 'This workspace member does not have a user profile.');
        $asset = $this->media->setAvatar($member->user, $member, $asset, $request->user()->id);
        return response()->json(['data' => ['avatar_url' => '/api/v1/media/public/'.$asset->uuid, 'media_asset_id' => $asset->id], 'message' => 'Member profile photo updated.']);
    }

    /** Removes a workspace member's media-backed profile photo for authorized people administrators. */
    public function clearMemberAvatar(Request $request, \App\Models\WorkspaceMember $member): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $member->workspace_id === (int) $workspace->id, 404);
        $member->loadMissing('user');
        abort_unless($member->user, 422, 'This workspace member does not have a user profile.');
        $this->media->clearAvatar($member->user);
        return response()->json(['message' => 'Member profile photo removed.']);
    }

    /** Return a collection query constrained to what the current member may discover. */
    private function collectionQuery(int $workspaceId, $member)
    {
        $query=MediaCollection::query()->where('workspace_id',$workspaceId);if(!$member->hasPermission('media.manage'))$query->where(fn($q)=>$q->where('visibility','workspace')->orWhere('created_by',$member->user_id)->orWhereHas('members',fn($m)=>$m->where('workspace_members.id',$member->id)));return $query;
    }
    /** Synchronize explicit members on a restricted collection after workspace validation. */
    private function syncCollectionMembers(MediaCollection $collection,int $workspaceId,array $ids): void
    {
        $requested=collect($ids)->map(fn($id)=>(int)$id)->filter()->unique()->values();$valid=\App\Models\WorkspaceMember::query()->where('workspace_id',$workspaceId)->where('status','active')->whereIn('id',$requested)->pluck('id');abort_if($valid->count()!==$requested->count(),422,'One or more collection members are unavailable.');$collection->members()->sync($requested->mapWithKeys(fn($id)=>[$id=>['workspace_id'=>$workspaceId,'role'=>'viewer']])->all());
    }
    /** Shape one collection with sharing metadata and count while hiding unrelated member profile fields. */
    private function collectionPayload(MediaCollection $collection, $viewer = null): array
    {
        $canManage=$viewer?->hasPermission('media.manage')??false;if($canManage)$collection->loadMissing('members:id');$collection->loadCount('assets');return ['id'=>$collection->id,'name'=>$collection->name,'description'=>$collection->description,'visibility'=>$collection->visibility,'shared_member_ids'=>$canManage?$collection->members->pluck('id')->values()->all():[],'assets_count'=>(int)$collection->assets_count,'created_at'=>$collection->created_at?->toIso8601String(),'updated_at'=>$collection->updated_at?->toIso8601String()];
    }
    /** Shape one private rendition without exposing its physical storage path. */
    private function renditionPayload(MediaRendition $row): array { return ['id'=>$row->id,'width'=>$row->width,'height'=>$row->height,'fit'=>$row->fit,'format'=>$row->format,'quality'=>$row->quality,'mime_type'=>$row->mime_type,'size_bytes'=>$row->size_bytes,'status'=>$row->status,'content_url'=>'/api/v1/media/'.$row->media_asset_id.'/renditions/'.$row->id.'/content','created_at'=>$row->created_at?->toIso8601String()]; }
    /** Shape resumable progress for browser persistence without exposing temporary storage paths. */
    private function uploadSessionPayload(MediaUploadSession $session): array { $received=collect($session->received_chunks??[])->map(fn($value)=>(int)$value)->unique()->sort()->values()->all();return ['uuid'=>$session->uuid,'original_name'=>$session->original_name,'size_bytes'=>$session->size_bytes,'chunk_size_bytes'=>$session->chunk_size_bytes,'total_chunks'=>$session->total_chunks,'received_chunks'=>$received,'status'=>$session->status,'expires_at'=>$session->expires_at?->toIso8601String()]; }

    /** Shapes an asset without exposing its disk credentials or physical storage path. */
    private function payload(MediaAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'uuid' => $asset->uuid,
            'name' => $asset->name,
            'original_name' => $asset->original_name,
            'category' => $asset->category(),
            'mime_type' => $asset->mime_type,
            'extension' => $asset->extension,
            'size_bytes' => (int) $asset->size_bytes,
            'width' => $asset->width,
            'height' => $asset->height,
            'focal_x' => $asset->focal_x,
            'focal_y' => $asset->focal_y,
            'alt_text' => $asset->alt_text,
            'caption' => $asset->caption,
            'copyright_owner' => $asset->copyright_owner,
            'license_type' => $asset->license_type,
            'license_reference' => $asset->license_reference,
            'license_expires_at' => $asset->license_expires_at?->toDateString(),
            'usage_restrictions' => $asset->usage_restrictions,
            'rights_review_at' => $asset->rights_review_at?->toDateString(),
            'rights_status' => $asset->rightsStatus(),
            'visibility' => $asset->visibility,
            'status' => $asset->status,
            'folder' => $asset->folder ? ['id' => $asset->folder->id, 'name' => $asset->folder->name] : null,
            'tags' => $asset->tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color])->values()->all(),
            'collections' => $asset->relationLoaded('collections') ? $asset->collections->map(fn ($collection) => ['id'=>$collection->id,'name'=>$collection->name])->values()->all() : [],
            'is_favorite' => (bool) ($asset->is_favorite ?? false),
            'usages_count' => (int) ($asset->usages_count ?? $asset->usages()->count()),
            'versions_count' => (int) ($asset->versions_count ?? $asset->versions()->count()),
            'renditions_count' => (int) ($asset->renditions_count ?? $asset->renditions()->count()),
            'created_at' => $asset->created_at?->toIso8601String(),
            'updated_at' => $asset->updated_at?->toIso8601String(),
            'deleted_at' => $asset->deleted_at?->toIso8601String(),
            'content_url' => '/api/v1/media/'.$asset->id.'/content',
            'download_url' => '/api/v1/media/'.$asset->id.'/download',
            'public_url' => $asset->visibility === 'public' ? '/api/v1/media/public/'.$asset->uuid : null,
        ];
    }
}
