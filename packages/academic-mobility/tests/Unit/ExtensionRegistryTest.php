<?php

namespace Module\AcademicMobility\Tests\Unit;

use Illuminate\Container\Container;
use Module\AcademicMobility\Core\Exceptions\InvalidExtension;
use Module\AcademicMobility\Core\Exceptions\UnknownExtension;
use Module\AcademicMobility\Rules\ParticipantAttributeRule;
use Module\AcademicMobility\Support\ExtensionKind;
use Module\AcademicMobility\Support\ExtensionRegistry;
use Module\AcademicMobility\Tests\Support\ActiveEnrollmentRule;
use Module\AcademicMobility\Tests\Support\ArrayParticipantResolver;
use PHPUnit\Framework\TestCase;
use stdClass;

class ExtensionRegistryTest extends TestCase
{
    private function registry(): ExtensionRegistry
    {
        return new ExtensionRegistry(new Container);
    }

    public function test_registers_a_class_a_closure_and_an_instance(): void
    {
        $registry = $this->registry();
        $instance = new ParticipantAttributeRule;

        $registry->register(ExtensionKind::Rule, 'by_class', ActiveEnrollmentRule::class);
        $registry->register(ExtensionKind::Rule, 'by_closure', fn () => new ActiveEnrollmentRule);
        $registry->register(ExtensionKind::Rule, 'by_instance', $instance);

        $this->assertInstanceOf(ActiveEnrollmentRule::class, $registry->resolve(ExtensionKind::Rule, 'by_class'));
        $this->assertInstanceOf(ActiveEnrollmentRule::class, $registry->resolve(ExtensionKind::Rule, 'by_closure'));
        $this->assertSame($instance, $registry->resolve(ExtensionKind::Rule, 'by_instance'));
        $this->assertSame(['by_class', 'by_closure', 'by_instance'], $registry->aliases(ExtensionKind::Rule));
    }

    public function test_resolved_extensions_are_shared(): void
    {
        $registry = $this->registry();
        $registry->register(ExtensionKind::Rule, 'a', ActiveEnrollmentRule::class);

        $this->assertSame($registry->resolve(ExtensionKind::Rule, 'a'), $registry->resolve(ExtensionKind::Rule, 'a'));
    }

    public function test_registering_an_alias_again_replaces_it(): void
    {
        $registry = $this->registry();
        $registry->register(ExtensionKind::Rule, 'a', ActiveEnrollmentRule::class);
        $registry->resolve(ExtensionKind::Rule, 'a');

        $registry->register(ExtensionKind::Rule, 'a', ParticipantAttributeRule::class);

        $this->assertInstanceOf(ParticipantAttributeRule::class, $registry->resolve(ExtensionKind::Rule, 'a'));
    }

    public function test_rejects_classes_that_do_not_implement_the_contract(): void
    {
        $this->expectException(InvalidExtension::class);

        $this->registry()->register(ExtensionKind::Rule, 'bad', stdClass::class);
    }

    public function test_rejects_missing_classes_and_wrong_instances(): void
    {
        $registry = $this->registry();

        try {
            $registry->register(ExtensionKind::Rule, 'bad', 'App\\DoesNotExist');
            $this->fail('A missing class must be rejected.');
        } catch (InvalidExtension) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidExtension::class);
        $registry->register(ExtensionKind::Rule, 'bad', new stdClass);
    }

    public function test_a_closure_building_the_wrong_type_fails_on_resolve(): void
    {
        $registry = $this->registry();
        $registry->register(ExtensionKind::Rule, 'bad', fn () => new stdClass);

        $this->expectException(InvalidExtension::class);

        $registry->resolve(ExtensionKind::Rule, 'bad');
    }

    public function test_unknown_aliases_throw(): void
    {
        $this->expectException(UnknownExtension::class);

        $this->registry()->resolve(ExtensionKind::Strategy, 'nope');
    }

    public function test_kinds_are_isolated(): void
    {
        $registry = $this->registry();
        $registry->register(ExtensionKind::Rule, 'a', ActiveEnrollmentRule::class);

        $this->assertTrue($registry->has(ExtensionKind::Rule, 'a'));
        $this->assertFalse($registry->has(ExtensionKind::Strategy, 'a'));
    }

    public function test_participants_must_be_a_student_a_professor_or_staff(): void
    {
        $registry = $this->registry();

        foreach (['student', 'professor', 'staff'] as $kind) {
            $registry->register(ExtensionKind::ParticipantResolver, $kind, new ArrayParticipantResolver([], $kind));
        }
        $this->assertSame(['student', 'professor', 'staff'], $registry->aliases(ExtensionKind::ParticipantResolver));

        $this->expectException(InvalidExtension::class);
        $registry->register(ExtensionKind::ParticipantResolver, 'alien', new ArrayParticipantResolver([], 'alien'));
    }
}
