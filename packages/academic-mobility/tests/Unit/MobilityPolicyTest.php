<?php

namespace Module\AcademicMobility\Tests\Unit;

use Illuminate\Support\Facades\Gate;
use Module\AcademicMobility\Authorization\MobilityAbility;
use Module\AcademicMobility\Authorization\MobilityPolicy;
use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Tests\Support\TestUser;
use Module\AcademicMobility\Tests\TestCase;

class MobilityPolicyTest extends TestCase
{
    public function test_denies_by_default(): void
    {
        $user = new TestUser;

        $this->assertFalse((new MobilityPolicy)->viewAny($user)->allowed());
        $this->assertFalse((new MobilityPolicy)->view($user, new Mobility)->allowed());
    }

    public function test_follows_the_host_acl(): void
    {
        Gate::define(MobilityAbility::ViewAny->value, fn () => true);

        $this->assertTrue((new MobilityPolicy)->viewAny(new TestUser)->allowed());
        $this->assertFalse((new MobilityPolicy)->view(new TestUser, new Mobility)->allowed());
    }

    public function test_policy_is_registered_for_the_model(): void
    {
        $this->assertInstanceOf(MobilityPolicy::class, Gate::getPolicyFor(Mobility::class));
    }
}
