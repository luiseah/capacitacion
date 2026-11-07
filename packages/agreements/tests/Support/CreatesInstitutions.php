<?php

namespace Module\Agreements\Tests\Support;

use Module\AcademicMobility\Core\Models\Institution;

/**
 * Institutions every mobility test needs: a local one (Spain) and two others.
 *
 * @property Institution $local
 * @property Institution $national
 * @property Institution $foreign
 */
trait CreatesInstitutions
{
    protected Institution $local;

    protected Institution $national;

    protected Institution $foreign;

    protected function createInstitutions(): void
    {
        $this->local = Institution::create(['name' => 'Universidad Local', 'country' => 'ES', 'is_local' => true]);
        $this->national = Institution::create(['name' => 'Universidad de Barcelona', 'country' => 'ES']);
        $this->foreign = Institution::create(['name' => 'Sorbonne', 'country' => 'FR']);
    }
}
