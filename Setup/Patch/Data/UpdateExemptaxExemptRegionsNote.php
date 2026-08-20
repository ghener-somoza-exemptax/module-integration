<?php

declare(strict_types=1);

namespace Exemptax\Integration\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Reword EXEMPTAX Exempt Regions admin note to include integration settings.
 */
class UpdateExemptaxExemptRegionsNote implements DataPatchInterface
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
        if ($customerSetup->getAttributeId(Customer::ENTITY, 'exemptax_exemption_states')) {
            $customerSetup->updateAttribute(
                Customer::ENTITY,
                'exemptax_exemption_states',
                'note',
                'U.S. states where the customer is tax-exempt based on their EXEMPTAX certificates and integration settings. '
                . 'The exemption is applied according to the order\'s ship-to state.'
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [
            UpdateExemptaxAttributeNotes::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
