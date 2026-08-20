<?php
namespace App\Services\Installation;
use App\Models\InstallationGuideProgress;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Releases\ReleaseCatalogService;
use App\Support\InstallationGuideCatalog;
/** Provides installation guide service behavior within the WorkIntel application. */ class InstallationGuideService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly ReleaseCatalogService $releases){}
    public function list(Workspace $workspace,WorkspaceMember $member,string $serverUrl):array
    {
        return collect(InstallationGuideCatalog::all())->map(fn($g,$key)=>$this->resolve($key,$g,$workspace,$member,$serverUrl,null))->values()->all();
    }
    /** Handles the one operation for the current WorkIntel workflow. */ public function one(string $key,Workspace $workspace,WorkspaceMember $member,string $serverUrl,?string $enrollmentCode=null):array
    {
        $guide=InstallationGuideCatalog::get($key);abort_unless($guide,404,'Installation guide not found.');return $this->resolve($key,$guide,$workspace,$member,$serverUrl,$enrollmentCode);
    }
    /** Returns resolve data required by the current workflow. */ private function resolve(string $key,array $guide,Workspace $workspace,WorkspaceMember $member,string $serverUrl,?string $enrollmentCode):array
    {
        $release=$guide['release_slug']?$this->releases->find($guide['release_slug']):null;$progress=InstallationGuideProgress::where('workspace_id',$workspace->id)->where('member_id',$member->id)->where('guide_key',$key)->first();
        $vars=['server_url'=>rtrim($serverUrl,'/'),'enrollment_code'=>$enrollmentCode?:'WI-XXXX-XXXX-XXXX','filename'=>$release['filename']??'workintel-release.zip'];
        $steps=array_map(function($step)use($vars){foreach(['title','text','command'] as $field)if(isset($step[$field]))$step[$field]=preg_replace_callback('/\{\{([a-z_]+)\}\}/',fn($m)=>(string)($vars[$m[1]]??$m[0]),$step[$field]);return $step;},$guide['steps']);
        return ['key'=>$key,'title'=>$guide['title'],'platform'=>$guide['platform'],'audience'=>$guide['audience'],'summary'=>$guide['summary'],'requirements'=>$guide['requirements'],'release'=>$release?array_diff_key($release,['file'=>true]):null,'steps'=>$steps,'progress'=>['completed_steps'=>$progress?->completed_steps??[],'current_step'=>$progress?->current_step,'completed_at'=>$progress?->completed_at?->toIso8601String()]];
    }
}
