<?php

namespace Module\AcademicMobility\Tests\Unit;

use InvalidArgumentException;
use Module\AcademicMobility\Rules\ParticipantAttributeRule;
use Module\AcademicMobility\Rules\RuleContext;
use Module\AcademicMobility\Tests\Support\FakeParticipant;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParticipantAttributeRuleTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function cases(): array
    {
        return [
            'equals' => [['attribute' => 'status', 'value' => 'active'], true],
            'not equal' => [['attribute' => 'status', 'operator' => '!=', 'value' => 'active'], false],
            'greater or equal' => [['attribute' => 'gpa', 'operator' => '>=', 'value' => 4.0], true],
            'less than' => [['attribute' => 'gpa', 'operator' => '<', 'value' => 4.0], false],
            'in' => [['attribute' => 'program', 'operator' => 'in', 'value' => ['law', 'arts']], true],
            'not in' => [['attribute' => 'program', 'operator' => 'not_in', 'value' => ['law', 'arts']], false],
            'dot notation' => [['attribute' => 'faculty.code', 'value' => 'ENG'], true],
            'missing attribute' => [['attribute' => 'nope', 'value' => 1], false],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    #[DataProvider('cases')]
    public function test_evaluates_the_operators(array $params, bool $expected): void
    {
        $context = new RuleContext(new FakeParticipant(1, [
            'status' => 'active', 'gpa' => 4.2, 'program' => 'law', 'faculty' => ['code' => 'ENG'],
        ]));

        $this->assertSame($expected, (new ParticipantAttributeRule)->evaluate($context, $params)->passed);
    }

    public function test_requires_an_attribute_and_a_known_operator(): void
    {
        $context = new RuleContext(new FakeParticipant(1, ['a' => 1]));

        try {
            (new ParticipantAttributeRule)->evaluate($context, []);
            $this->fail('The attribute param is required.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        (new ParticipantAttributeRule)->evaluate($context, ['attribute' => 'a', 'operator' => '~', 'value' => 1]);
    }
}
