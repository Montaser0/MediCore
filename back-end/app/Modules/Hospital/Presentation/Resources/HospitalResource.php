<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Presentation\Resources;

use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HospitalModel
 */
final class HospitalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'logo_path' => $this->logo_path,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
        ];
    }
}
