<?php

declare(strict_types=1);

namespace App\Core\Backup;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BackupController extends ApiController
{
    public function index(Request $request, BackupService $backups): JsonResponse
    {
        $target = (string) $request->query('location', 'local');
        $drive = $request->query('drive');

        try {
            return ApiResponse::data($backups->history($target, is_string($drive) ? $drive : null));
        } catch (BackupException $exception) {
            return ApiResponse::message($exception->getMessage(), 422);
        }
    }

    public function drives(BackupService $backups): JsonResponse
    {
        return ApiResponse::data($backups->drives());
    }

    public function store(Request $request, BackupService $backups): JsonResponse
    {
        $data = $request->validate([
            'target' => ['required', 'in:local,usb'],
            'drive' => ['nullable', 'string', 'size:1'],
        ]);

        try {
            return ApiResponse::data(
                $backups->create((string) $data['target'], isset($data['drive']) ? (string) $data['drive'] : null),
                [],
                201,
            );
        } catch (BackupException $exception) {
            return ApiResponse::message($exception->getMessage(), 422);
        }
    }

    public function restore(Request $request, BackupService $backups): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'string', 'max:180'],
            'location' => ['required', 'in:local,usb'],
            'drive' => ['nullable', 'string', 'size:1'],
            'confirm' => ['required', 'boolean'],
        ]);

        try {
            return ApiResponse::data($backups->restore(
                (string) $data['file'],
                (string) $data['location'],
                isset($data['drive']) ? (string) $data['drive'] : null,
                (bool) $data['confirm'],
            ));
        } catch (BackupException $exception) {
            return ApiResponse::message($exception->getMessage(), 422);
        }
    }
}
