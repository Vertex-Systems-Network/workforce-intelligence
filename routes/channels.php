<?php
use App\Models\{ChatConversation,WorkspaceMember};use Illuminate\Support\Facades\Broadcast;
Broadcast::channel('workspace.{workspaceId}.chat.{conversationId}',function($user,int $workspaceId,int $conversationId){$member=WorkspaceMember::where('workspace_id',$workspaceId)->where('user_id',$user->id)->where('status','active')->first();return $member&&ChatConversation::where('workspace_id',$workspaceId)->whereKey($conversationId)->whereHas('members',fn($q)=>$q->where('workspace_members.id',$member->id))->exists();});
