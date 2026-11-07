<?php

namespace Module\AcademicMobility\Core\Persistence;

use Module\AcademicMobility\Core\Models\Mobility;

/**
 * Storage port of the Core. Rebind it in the container to store mobilities
 * somewhere else.
 */
interface MobilityRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Mobility;

    public function find(string $id): ?Mobility;

    public function save(Mobility $mobility): Mobility;
}
