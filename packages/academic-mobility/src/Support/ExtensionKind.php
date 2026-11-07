<?php

namespace Module\AcademicMobility\Support;

use Module\AcademicMobility\Contracts\Approver;
use Module\AcademicMobility\Contracts\ConditionEvaluator;
use Module\AcademicMobility\Contracts\DocumentProvider;
use Module\AcademicMobility\Contracts\MobilityStrategy;
use Module\AcademicMobility\Contracts\ParticipantResolver;
use Module\AcademicMobility\Contracts\Rule;

/**
 * Every extension point of the package: what a host application can register,
 * the contract it must satisfy and the config key that lists its defaults.
 */
enum ExtensionKind: string
{
    case Rule = 'rule';
    case Strategy = 'strategy';
    case ConditionEvaluator = 'condition_evaluator';
    case ParticipantResolver = 'participant_resolver';
    case DocumentProvider = 'document_provider';
    case Approver = 'approver';

    /**
     * @return class-string
     */
    public function contract(): string
    {
        return match ($this) {
            self::Rule => Rule::class,
            self::Strategy => MobilityStrategy::class,
            self::ConditionEvaluator => ConditionEvaluator::class,
            self::ParticipantResolver => ParticipantResolver::class,
            self::DocumentProvider => DocumentProvider::class,
            self::Approver => Approver::class,
        };
    }

    public function configKey(): string
    {
        return match ($this) {
            self::Rule => 'rules',
            self::Strategy => 'strategies',
            self::ConditionEvaluator => 'condition_evaluators',
            self::ParticipantResolver => 'participant_resolvers',
            self::DocumentProvider => 'document_providers',
            self::Approver => 'approvers',
        };
    }

    public function label(): string
    {
        return str_replace('_', ' ', $this->value);
    }
}
