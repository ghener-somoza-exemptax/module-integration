<?php

declare(strict_types=1);

namespace Exemptax\Integration\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Account Information order:
 * Main Exemption Type → Exempt Regions → Exemption Status
 *
 * Magento stores customer attribute order on customer_eav_attribute.sort_order
 * and eav_entity_attribute.sort_order (not eav_attribute).
 * updateAttribute(..., 'sort_order', $n, $n) updates both: additional table + set position.
 */
class ReorderExemptaxCustomerAttributes implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $positions = [
            'exemptax_main_exemption_type' => 999,
            'exemptax_exemption_states' => 1000,
            'exemptax_exemption_status' => 1001,
        ];

        foreach ($positions as $code => $sortOrder) {
            if (!$customerSetup->getAttributeId(Customer::ENTITY, $code)) {
                continue;
            }
            // 4th arg → customer_eav_attribute.sort_order; 5th → eav_entity_attribute.sort_order
            $customerSetup->updateAttribute(
                Customer::ENTITY,
                $code,
                'sort_order',
                $sortOrder,
                $sortOrder
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [
            AddCustomerExemptionAttributes::class,
            AddMainExemptionTypeAttribute::class,
            ConvertExemptionStatesToMultiselect::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
