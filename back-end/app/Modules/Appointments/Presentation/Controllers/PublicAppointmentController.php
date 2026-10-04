<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Appointments\Application\Actions\BookPublicAppointmentAction;
use App\Modules\Appointments\Application\Services\PublicBookingSettings;
use App\Modules\Appointments\Presentation\Requests\PublicBookAppointmentRequest;
use App\Modules\Appointments\Presentation\Requests\UpdatePublicBookingSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class PublicAppointmentController extends ApiController
{
    public function clinics(Request $request, PublicBookingSettings $booking): JsonResponse
    {
        $date = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ])['date'] ?? Carbon::now(PublicBookingSettings::TIMEZONE)->toDateString();

        return ApiResponse::data($booking->availability((string) $date));
    }

    public function store(PublicBookAppointmentRequest $request, BookPublicAppointmentAction $action): JsonResponse
    {
        $appointment = $action->execute($request->validated());

        return ApiResponse::data([
            'appointment_number' => $appointment->appointment_number,
            'scheduled_at' => $appointment->scheduled_at?->copy()->timezone(PublicBookingSettings::TIMEZONE)->toIso8601String(),
            'clinic' => $appointment->clinic?->name,
            'doctor' => $appointment->doctor?->staff?->full_name,
            'patient_name' => $appointment->patient?->full_name,
            'mrn' => $appointment->patient?->mrn,
        ], [], 201);
    }

    public function showSettings(PublicBookingSettings $booking): JsonResponse
    {
        return ApiResponse::data($booking->present());
    }

    public function updateSettings(UpdatePublicBookingSettingsRequest $request, PublicBookingSettings $booking): JsonResponse
    {
        return ApiResponse::data($booking->save($request->validated()));
    }
}
