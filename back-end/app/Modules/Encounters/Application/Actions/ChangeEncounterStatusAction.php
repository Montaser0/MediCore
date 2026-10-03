<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Modules\Encounters\Domain\Enums\EncounterStatus;
use App\Modules\Encounters\Domain\Exceptions\InvalidEncounterTransitionException;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use Illuminate\Support\Facades\DB;

/**
 * The single place an encounter's status changes. It refuses illegal moves and
 * writes the history row, so no caller can leave the visit in a state the
 * status engine does not allow.
 */
final class ChangeEncounterStatusAction
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    public function execute(EncounterModel $encounter, EncounterStatus $target, ?string $note = null): EncounterModel
    {
        $current = $encounter->status;

        if ($current === $target) {
            return $encounter;
        }

        if (! $current->canTransitionTo($target)) {
            throw InvalidEncounterTransitionException::between($current, $target);
        }

        return DB::transaction(function () use ($encounter, $current, $target, $note): EncounterModel {
            $attributes = ['status' => $target->value];

            if ($target === EncounterStatus::InProgress && $encounter->started_at === null) {
                $attributes['started_at'] = now();
            }

            if ($target->isTerminal()) {
                $attributes['ended_at'] = now();
            }

            $encounter->forceFill($attributes)->save();

            $encounter->statusHistory()->create([
                'from_status' => $current->value,
                'to_status' => $target->value,
                'changed_by' => $this->currentUser->id(),
                'note' => $note,
            ]);

            return $encounter;
        });
    }
}
