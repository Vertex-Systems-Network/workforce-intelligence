<?php

use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ChatEnterpriseController;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/chat')->middleware(['auth:sanctum', ResolveWorkspace::class, 'locale', 'workspace.audit', 'workspace.module:chat', 'workspace.permission_any:chat.view,chat.create,chat.manage', 'throttle:600,1'])->group(function () {
    Route::get('/conversations', [ChatController::class, 'index']);
    Route::get('/inbox', [ChatController::class, 'inbox']);
    Route::post('/inbox/triage', [ChatController::class, 'triageInbox']);
    Route::get('/notification-preferences', [ChatController::class, 'notificationPreferences']);
    Route::put('/notification-preferences', [ChatController::class, 'updateNotificationPreferences']);
    Route::get('/options', [ChatController::class, 'options']);
    Route::get('/saved', [ChatController::class, 'saved']);
    Route::get('/search', [ChatController::class, 'search']);
    Route::get('/attachments/{attachment}', [ChatController::class, 'attachment']);

    Route::post('/conversations', [ChatController::class, 'storeConversation'])->middleware('workspace.permission_any:chat.create,chat.manage');
    Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages']);
    Route::get('/conversations/{conversation}/context', [ChatController::class, 'context']);
    Route::post('/conversations/{conversation}/context/bulk', [ChatController::class, 'bulkContext']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware(['workspace.permission_any:chat.create,chat.manage', 'throttle:60,1']);
    Route::post('/conversations/{conversation}/polls', [ChatController::class, 'createPoll'])->middleware('workspace.permission_any:chat.create,chat.manage');
    Route::put('/conversations/{conversation}/mute', [ChatController::class, 'mute']);
    Route::put('/conversations/{conversation}/read', [ChatController::class, 'read']);
    Route::get('/conversations/{conversation}/draft', [ChatController::class, 'draft']);
    Route::put('/conversations/{conversation}/draft', [ChatController::class, 'saveDraft']);
    Route::delete('/conversations/{conversation}/draft', [ChatController::class, 'deleteDraft']);

    Route::put('/messages/{message}', [ChatController::class, 'edit'])->middleware('workspace.permission_any:chat.create,chat.manage');
    Route::delete('/messages/{message}', [ChatController::class, 'destroy'])->middleware('workspace.permission_any:chat.create,chat.manage,chat.moderate');
    Route::get('/messages/{message}/history', [ChatController::class, 'history']);
    Route::post('/messages/{message}/reaction', [ChatController::class, 'react'])->middleware('throttle:120,1');
    Route::post('/messages/{message}/pin', [ChatController::class, 'pin']);
    Route::post('/messages/{message}/save', [ChatController::class, 'saveMessage']);
    Route::put('/messages/{message}/save-note', [ChatController::class, 'updateSavedNote']);
    Route::post('/messages/{message}/forward', [ChatController::class, 'forward'])->middleware('workspace.permission_any:chat.create,chat.manage');
    Route::get('/messages/{message}/thread', [ChatController::class, 'thread']);
    Route::put('/messages/{message}/thread/follow', [ChatController::class, 'followThread']);
    Route::post('/polls/{poll}/vote', [ChatController::class, 'votePoll']);


    Route::get('/public-channels', [ChatController::class, 'publicChannels']);
    Route::post('/conversations/{conversation}/join', [ChatController::class, 'joinChannel']);
    Route::post('/conversations/{conversation}/leave', [ChatController::class, 'leaveChannel']);
    Route::put('/conversations/{conversation}/channel', [ChatController::class, 'updateChannel']);
    Route::post('/conversations/{conversation}/members', [ChatController::class, 'addChannelMembers']);
    Route::delete('/conversations/{conversation}/members/{member}', [ChatController::class, 'removeChannelMember']);
    Route::put('/conversations/{conversation}/members/{member}/role', [ChatController::class, 'updateChannelMemberRole']);
    Route::put('/conversations/{conversation}/notifications', [ChatController::class, 'notificationMode']);
    Route::get('/conversations/{conversation}/resources', [ChatController::class, 'resources']);
    Route::post('/conversations/{conversation}/resources', [ChatController::class, 'addResource']);
    Route::delete('/resources/{resource}', [ChatController::class, 'deleteResource']);
    Route::post('/messages/{message}/actions', [ChatController::class, 'messageAction'])->middleware('workspace.permission_any:chat.create,chat.manage');



    // Chat V2.4 enterprise collaboration administration.
    Route::get('/enterprise/overview', [ChatEnterpriseController::class, 'overview'])->middleware('workspace.permission_any:chat.guests_manage,chat.retention_manage,chat.export,chat.legal_hold_manage,chat.dlp_manage');
    Route::post('/enterprise/conversations/{conversation}/external-invitations', [ChatEnterpriseController::class, 'inviteExternal'])->middleware('workspace.permission:chat.guests_manage');
    Route::patch('/enterprise/external-members/{member}', [ChatEnterpriseController::class, 'updateExternalMember'])->middleware('workspace.permission:chat.guests_manage');
    Route::put('/enterprise/conversations/{conversation}/policy', [ChatEnterpriseController::class, 'updateConversationPolicy'])->middleware('workspace.permission_any:chat.retention_manage,chat.guests_manage,chat.export,chat.dlp_manage');
    Route::post('/enterprise/legal-holds', [ChatEnterpriseController::class, 'createLegalHold'])->middleware('workspace.permission:chat.legal_hold_manage');
    Route::post('/enterprise/legal-holds/{hold}/release', [ChatEnterpriseController::class, 'releaseLegalHold'])->middleware('workspace.permission:chat.legal_hold_manage');
    Route::post('/enterprise/conversations/{conversation}/exports', [ChatEnterpriseController::class, 'createExport'])->middleware('workspace.permission:chat.export');
    Route::get('/enterprise/exports/{export}/download', [ChatEnterpriseController::class, 'downloadExport'])->middleware('workspace.permission:chat.export');
    Route::post('/enterprise/dlp-policies', [ChatEnterpriseController::class, 'createDlpPolicy'])->middleware('workspace.permission:chat.dlp_manage');
    Route::patch('/enterprise/dlp-policies/{policy}', [ChatEnterpriseController::class, 'updateDlpPolicy'])->middleware('workspace.permission:chat.dlp_manage');
    Route::post('/enterprise/messages/{message}/moderate', [ChatEnterpriseController::class, 'moderateMessage']);

    Route::post('/presence', [ChatController::class, 'presence'])->middleware('throttle:120,1');
});
