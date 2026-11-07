<?php

namespace Module\AcademicMobility\Core\Persistence;

use Module\AcademicMobility\Core\Models\Mobility;

class EloquentMobilityRepository implements MobilityRepository
{
    /**
     * @return class-string<Mobility>
     */
    private function model(): string
    {
        return config('academic-mobility.models.mobility', Mobility::class);
    }

    public function create(array $attributes): Mobility
    {
        return $this->model()::query()->create($attributes);
    }

    public function find(string $id): ?Mobility
    {
        return $this->model()::query()->find($id);
    }

    public function save(Mobility $mobility): Mobility
    {
        $mobility->save();

        return $mobility;
    }
}
