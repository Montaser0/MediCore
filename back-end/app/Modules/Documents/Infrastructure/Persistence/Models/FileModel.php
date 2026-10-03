<?php

declare(strict_types=1);

namespace App\Modules\Documents\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $uuid
 * @property string $disk
 * @property string $path
 */
final class FileModel extends BaseModel
{
    use SoftDeletes;

    protected $table = 'files';

    protected $casts = [
        'size' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        // Files are addressed by uuid so sequential ids are never exposed.
        return 'uuid';
    }

    /**
     * @return HasMany<FileLinkModel, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(FileLinkModel::class, 'file_id');
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'uploaded_by');
    }

    public function downloadPath(): string
    {
        return "/api/files/{$this->uuid}";
    }
}
