# Changelog

Follows [Semantic Versioning](https://semver.org). The public API (what 1.x will keep stable) is:
everything in `Contracts/`, the `AcademicMobility` facade, the config keys, `ExtensionKind`, the
DTOs/value objects/events/exceptions, and the `{prefix}mobilities` table. Everything else is internal.

## [Unreleased]

### Added
- Institutions (`Institution` model, one local), origin/destination on every mobility, derived `direction` and `scope`,
  built-in `direction`/`scope` conditions, institutions API and React pages. **Breaking vs the skeleton:**
  `CreateMobilityData` now takes the two institution ids after the participant.
- Mobilities list: JSON API, Inertia/React page, `MobilityBuilder` (filters + sort), `routes.*` and `per_page` config.
- ACL: `MobilityAbility` + deny-by-default `MobilityPolicy` over the Laravel Gate (`mobilities.index`, `mobilities.show`).
- Package skeleton: auto-discovered service provider, publishable config and migration, facade.
- Extension registry (`registerRule`, `registerStrategy`, `registerConditionEvaluator`,
  `registerParticipantResolver`, `registerDocumentProvider`, `registerApprover`) with
  class / closure / instance registration, container resolution and replacement by alias.
- Contracts: `Participant`, `ParticipantResolver`, `Rule`, `ConditionEvaluator`, `MobilityStrategy`,
  `DocumentProvider`, `Approver`; `MobilityRepository` port.
- Core: `Mobility` model, `MobilityStatus`, `CreateMobility`, events and exceptions.
- Rules: `RuleEngine`, built-in `participant_attribute` rule and `default` strategy.
- Workflow: status transitions (`TransitionMobility`).
- Infrastructure: `EloquentParticipantResolver` / `ModelParticipant` adapters.
