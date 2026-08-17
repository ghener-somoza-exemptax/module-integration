<?php

declare(strict_types=1);

namespace Exemptax\Integration\Test\Unit\Model\Tax;

use Exemptax\Integration\Model\Tax\ExemptionStates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExemptionStatesTest extends TestCase
{
    #[DataProvider('parseProvider')]
    public function test_parse_normalizes_codes(string|array|null $value, array $expected): void
    {
        $this->assertSame($expected, (new ExemptionStates())->parse($value));
    }

    public static function parseProvider(): array
    {
        return [
            'null' => [null, []],
            'empty' => ['', []],
            'comma csv' => ['ca, ny,tx', ['CA', 'NY', 'TX']],
            'whitespace csv' => [" ca\nTX  ", ['CA', 'TX']],
            'array values' => [['ca', 'CA', 'ny'], ['CA', 'NY']],
            'ignores blanks in array' => [['', 'or'], ['OR']],
        ];
    }

    public function test_contains_matches_region_code(): void
    {
        $states = new ExemptionStates();

        $this->assertTrue($states->contains(['CA', 'NY'], 'ca'));
        $this->assertTrue($states->contains(['CA', 'NY'], ' NY '));
        $this->assertFalse($states->contains(['CA', 'NY'], 'TX'));
        $this->assertFalse($states->contains(['CA'], ''));
        $this->assertFalse($states->contains([], 'CA'));
        $this->assertFalse($states->contains(['CA'], null));
    }
}
