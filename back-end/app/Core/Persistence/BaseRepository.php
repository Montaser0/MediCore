<?php

declare(strict_types=1);

namespace App\Core\Persistence;

use App\Core\Exceptions\ResourceNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
abstract class BaseRepository
{
    /**
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return $this->model()::query();
    }

    /**
     * @return TModel|null
     */
    public function find(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    /**
     * @return TModel
     */
    public function findOrFail(int $id): Model
    {
        $record = $this->find($id);

        if ($record === null) {
            throw ResourceNotFoundException::for(class_basename($this->model()), $id);
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }
}
