<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;

Route::post('/authorization', [AuthController::class, 'authenticate']);
Route::post('/registration', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->get('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->post('/files', [FileController::class, 'uploadFiles']);
Route::middleware('auth:sanctum')->patch('/files/{file_id}', [FileController::class, 'rename']);
Route::middleware('auth:sanctum')->delete('/files/{file_id}', [FileController::class, 'delete']);
Route::middleware('auth:sanctum')->get('/files/{file_id}', [FileController::class, 'download']); // TODO: conflict this route: /files/disk
Route::middleware('auth:sanctum')->post('/files/{file_id}/accesses', [FileController::class, 'addAccess']);
Route::middleware('auth:sanctum')->delete('/files/{file_id}/accesses', [FileController::class, 'deleteAccess']);
Route::middleware('auth:sanctum')->get('/files/disk', [FileController::class, 'getFiles']);

Route::get('/login', function () {
    return response()->json([
        'message' => 'Login failed'
    ], 403);
})->name('login');