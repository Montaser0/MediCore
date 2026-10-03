<?php

declare(strict_types=1);

use App\Modules\Documents\Presentation\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('files', [DocumentController::class, 'store']);
    Route::get('files/{file}', [DocumentController::class, 'download']);
    Route::get('files/{file}/details', [DocumentController::class, 'show']);
    Route::delete('files/{file}', [DocumentController::class, 'destroy']);

    Route::get('patients/{patientId}/documents', [DocumentController::class, 'forPatient']);
    Route::get('documents/{entityType}/{entityId}', [DocumentController::class, 'forEntity']);
});
