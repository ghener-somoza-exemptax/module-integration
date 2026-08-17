<?php

declare(strict_types=1);

namespace Exemptax\Integration\Model\Tax;

/**
 * Parse and match Magento customer exemptax_exemption_states values.
 */
class ExemptionStates
{
    public const ATTRIBUTE_CODE = 'exemptax_exemption_states';

    /**
     * @param string|array<int|string, mixed>|null $value
     * @return list<string>
     */
    public function parse(string|array|null $value): array
    {
        if (is_array($value)) {
            $value = implode(',', array_map(static fn ($part) => (string) $part, $value));
        }

        if ($value === null || trim($value) === '') {
            return [];
        }

        $parts = preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $codes = [];
        foreach ($parts as $part) {
            $code = strtoupper(trim((string) $part));
            if ($code !== '' && !in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * @param list<string> $states
     */
    public function contains(array $states, ?string $regionCode): bool
    {
        $regionCode = strtoupper(trim((string) $regionCode));
        if ($regionCode === '' || $states === []) {
            return false;
        }

        return in_array($regionCode, $states, true);
    }
}
