<?php

namespace Module\AcademicMobility\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Module\AcademicMobility\Facades\AcademicMobility;
use Module\AcademicMobility\Infrastructure\Resolvers\EloquentParticipantResolver;
use Module\AcademicMobility\Support\ExtensionKind;
use Module\AcademicMobility\Tests\TestCase;

class HostStudent extends Model
{
    protected $table = 'host_students';

    public $timestamps = false;

    protected $guarded = [];
}

class EloquentParticipantResolverTest extends TestCase
{
    public function test_adapts_a_host_model_without_modifying_it(): void
    {
        Schema::create('host_students', function (Blueprint $table) {
            $table->id();
            $table->string('first');
            $table->string('last');
            $table->string('code')->unique();
            $table->string('status');
        });
        $student = HostStudent::create(['first' => 'Ana', 'last' => 'Torres', 'code' => 'S-100', 'status' => 'active']);

        AcademicMobility::registerParticipantResolver('student', fn () => new EloquentParticipantResolver(
            HostStudent::class,
            'student',
            fn (HostStudent $s) => "{$s->first} {$s->last}",
            ['status'],
        ));
        AcademicMobility::registerParticipantResolver('professor', fn () => new EloquentParticipantResolver(
            HostStudent::class, 'student', 'first', ['status'], keyColumn: 'code',
        ));

        $participant = AcademicMobility::extension(ExtensionKind::ParticipantResolver, 'student')->resolve($student->id);

        $this->assertSame('student', $participant->participantType());
        $this->assertSame($student->id, $participant->participantId());
        $this->assertSame('Ana Torres', $participant->displayName());
        $this->assertSame(['status' => 'active'], $participant->attributes());
        $this->assertNull(AcademicMobility::extension(ExtensionKind::ParticipantResolver, 'student')->resolve(999));
        $this->assertSame('Ana', AcademicMobility::extension(ExtensionKind::ParticipantResolver, 'professor')->resolve('S-100')->displayName());
    }
}
