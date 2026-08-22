<?php

use App\Http\Controllers\Api\V1\AgentReleaseController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AgentReleaseController::class, 'current']);
Route::get('/download', [AgentReleaseController::class, 'download']);
