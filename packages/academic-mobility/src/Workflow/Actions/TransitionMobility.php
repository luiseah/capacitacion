<?php

namespace Module\AcademicMobility\Workflow\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Module\AcademicMobility\Core\Enums\MobilityStatus;
use Module\AcademicMobility\Core\Exceptions\InvalidTransition;
use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Workflow\Events\MobilityStatusChanged;

/**
 * Moves a mobility along {@see MobilityStatus::allowedTransitions()}.
 * Approvals and guards plug in here in a later phase.
 */
class TransitionMobility
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    /**
     * @throws InvalidTransition
     */
    public function handle(Mobility $mobility, MobilityStatus $to): Mobility
    {
        $from = $mobility->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidTransition::make($from, $to);
        }

        $mobility->status = $to;
        $mobility->save();

        $this->events->dispatch(new MobilityStatusChanged($mobility, $from, $to));

        return $mobility;
    }
}
