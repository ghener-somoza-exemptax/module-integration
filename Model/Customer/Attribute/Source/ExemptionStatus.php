<?php

declare(strict_types=1);

namespace Exemptax\Integration\Model\Customer\Attribute\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

/**
 * EXEMPTAX Exemption Status dropdown.
 *
 * Stored values match EXEMPTAX REST writes: "active" when coverage exists,
 * empty string when coverage is cleared. Empty is shown as Inactive.
 */
class ExemptionStatus extends AbstractSource
{
    public const VALUE_ACTIVE = 'active';

    public const VALUE_INACTIVE = '';

    public const ATTRIBUTE_CODE = 'exemptax_exemption_status';

    /**
     * @return list<array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function getAllOptions(): array
    {
        if ($this->_options !== null) {
            return $this->_options;
        }

        $this->_options = [
            ['value' => self::VALUE_ACTIVE, 'label' => __('Active')],
            ['value' => self::VALUE_INACTIVE, 'label' => __('Inactive')],
        ];

        return $this->_options;
    }
}
