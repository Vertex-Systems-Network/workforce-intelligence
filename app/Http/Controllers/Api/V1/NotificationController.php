<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Models\WorkspaceNotification;
use App\Services\Notifications\WorkspaceNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
/** Provides notification controller behavior within the WorkIntel application. */ class NotificationController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$user=$request->user();$limit=min(100,max(1,(int)$request->integer('limit',40)));
        if (! Schema::hasTable('workspace_notifications')) return response()->json(['data'=>[],'unread_count'=>0,'storage_available'=>false]);
        $query=WorkspaceNotification::where('workspace_id',$workspace->id)->where('user_id',$user->id)->latest('created_at');
        if($request->boolean('unread_only'))$query->whereNull('read_at');
        return response()->json(['data'=>$query->limit($limit)->get(),'unread_count'=>WorkspaceNotification::where('workspace_id',$workspace->id)->where('user_id',$user->id)->whereNull('read_at')->count()]);
    }
    /** Handles the read operation for the current WorkIntel workflow. */ public function read(Request $request,WorkspaceNotification $notification): JsonResponse
    {
        abort_unless(Schema::hasTable('workspace_notifications'),503,'Notification storage is unavailable until migrations are repaired.');$workspace=$request->attributes->get('workspace');abort_unless($notification->workspace_id===$workspace->id&&$notification->user_id===$request->user()->id,404);$notification->update(['read_at'=>now()]);return response()->json(['data'=>$notification->fresh()]);
    }
    /** Handles the read all operation for the current WorkIntel workflow. */ public function readAll(Request $request): JsonResponse
    {
        if (! Schema::hasTable('workspace_notifications')) return response()->json(['message'=>'Notification storage is not installed yet.','storage_available'=>false]);$workspace=$request->attributes->get('workspace');WorkspaceNotification::where('workspace_id',$workspace->id)->where('user_id',$request->user()->id)->whereNull('read_at')->update(['read_at'=>now()]);return response()->json(['message'=>'Notifications marked read.']);
    }
    /** Handles the preferences operation for the current WorkIntel workflow. */ public function preferences(Request $request,WorkspaceNotificationService $service): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');if (! Schema::hasTable('notification_preferences')) return response()->json(['data'=>[],'storage_available'=>false]);$service->defaults($workspace->id,$request->user()->id);return response()->json(['data'=>NotificationPreference::where('workspace_id',$workspace->id)->where('user_id',$request->user()->id)->orderBy('category')->get()]);
    }
    /** Updates update preferences data for the requested resource. */ public function updatePreferences(Request $request,WorkspaceNotificationService $service): JsonResponse
    {
        abort_unless(Schema::hasTable('notification_preferences'),503,'Notification preferences are unavailable until migrations are repaired.');$workspace=$request->attributes->get('workspace');$data=$request->validate(['preferences'=>['required','array','max:20'],'preferences.*.category'=>['required','string','max:40'],'preferences.*.in_app'=>['required','boolean'],'preferences.*.email'=>['required','boolean'],'preferences.*.digest'=>['required',Rule::in(['immediate','daily','weekly'])]]);
        foreach($data['preferences'] as $pref)NotificationPreference::updateOrCreate(['workspace_id'=>$workspace->id,'user_id'=>$request->user()->id,'category'=>$pref['category']],$pref);
        return $this->preferences($request,$service);
    }
}
