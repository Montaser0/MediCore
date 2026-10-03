<?php

declare(strict_types=1);

namespace App\Modules\Documents\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $entity_type
 * @property int $entity_id
 * @property string|null $category
 */
final class FileLinkModel extends BaseModel
{
    public const UPDATED_AT = null;

    protected $table = 'file_links';

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<FileModel, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(FileModel::class, 'file_id');
    }
}
