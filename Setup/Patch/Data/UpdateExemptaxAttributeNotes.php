<?php

declare(strict_types=1);

namespace Exemptax\Integration\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Refresh EXEMPTAX customer attribute admin notes (main exemption type + exempt regions).
 */
class UpdateExemptaxAttributeNotes implements DataPatchInterface
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

        $updates = [
            'exemptax_main_exemption_type' =>
                'Primary exemption reason determined by EXEMPTAX. For TaxJar, this is mapped to the customer\'s exemption type.',
            'exemptax_exemption_states' =>
                'U.S. states where the customer is tax-exempt according to their EXEMPTAX certificates. '
                . 'The exemption is applied based on the order\'s ship-to state.',
        ];

        foreach ($updates as $code => $note) {
            if (!$customerSetup->getAttributeId(Customer::ENTITY, $code)) {
                continue;
            }
            $customerSetup->updateAttribute(Customer::ENTITY, $code, 'note', $note);
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
