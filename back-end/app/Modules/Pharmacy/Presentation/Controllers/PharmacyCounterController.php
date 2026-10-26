<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use App\Modules\Pharmacy\Application\Actions\PharmacyCounterAction;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyCounterTicketModel;
use App\Modules\Pharmacy\Presentation\Resources\PharmacyCounterTicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PharmacyCounterController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $tickets = PharmacyCounterTicketModel::query()
            ->with('patient')
            ->forCurrentHospital()
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString()),
                fn ($query) => $query->where('status', 'waiting'),
            )
            ->orderBy('sent_at')
            ->paginate($this->perPage(50));

        return ApiResponse::paginated(PharmacyCounterTicketResource::collection($tickets), $tickets);
    }

    public function store(Request $request, PharmacyCounterAction $counter): JsonResponse
    {
        $patientId = (int) $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
        ])['patient_id'];

        $ticket = $counter->send(PatientModel::query()->findOrFail($patientId));

        return ApiResponse::resource(new PharmacyCounterTicketResource($ticket));
    }

    public function sell(Request $request, PharmacyCounterTicketModel $ticket, PharmacyCounterAction $counter): JsonResponse
    {
        $payload = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.medication_id' => ['required', 'integer', 'exists:medications,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        return ApiResponse::resource(new PharmacyCounterTicketResource(
            $counter->sell($ticket, $payload['lines'])
        ));
    }

    public function cancel(PharmacyCounterTicketModel $ticket, PharmacyCounterAction $counter): JsonResponse
    {
        return ApiResponse::resource(new PharmacyCounterTicketResource($counter->cancel($ticket)));
    }
}
