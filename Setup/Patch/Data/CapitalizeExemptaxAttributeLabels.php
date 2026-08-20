<?php

declare(strict_types=1);

namespace Exemptax\Integration\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Capitalize EXEMPTAX branding on existing customer attribute labels.
 */
class CapitalizeExemptaxAttributeLabels implements DataPatchInterface
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
            'exemptax_exemption_states' => [
                'frontend_label' => 'EXEMPTAX Exempt Regions',
                'note' => 'U.S. states where the customer is tax-exempt based on their EXEMPTAX certificates and integration settings. '
                    . 'The exemption is applied according to the order\'s ship-to state.',
            ],
            'exemptax_exemption_status' => [
                'frontend_label' => 'EXEMPTAX Exemption Status',
                'note' => 'Exemption status metadata from EXEMPTAX.',
            ],
        ];

        foreach ($updates as $code => $data) {
            if (!$customerSetup->getAttributeId(Customer::ENTITY, $code)) {
                continue;
            }

            $customerSetup->updateAttribute(Customer::ENTITY, $code, 'frontend_label', $data['frontend_label']);
            $customerSetup->updateAttribute(Customer::ENTITY, $code, 'note', $data['note']);
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [AddCustomerExemptionAttributes::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
