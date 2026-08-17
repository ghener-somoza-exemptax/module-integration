<?php

declare(strict_types=1);

namespace Exemptax\Integration\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Phase 2.1 — EXEMPTAX main exemption reason shown on Magento customer
 * and used to map TaxJar tj_exemption_type (one type per customer).
 */
class AddMainExemptionTypeAttribute implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory,
        private readonly AttributeSetFactory $attributeSetFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $customerEntity = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        $attributeSetId = (int) $customerEntity->getDefaultAttributeSetId();
        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = (int) $attributeSet->getDefaultGroupId($attributeSetId);

        if (!$customerSetup->getAttributeId(Customer::ENTITY, 'exemptax_main_exemption_type')) {
            $customerSetup->addAttribute(Customer::ENTITY, 'exemptax_main_exemption_type', [
                'type' => 'varchar',
                'label' => 'EXEMPTAX Main Exemption Type',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 999,
                'system' => 0,
                'note' => 'Primary exemption reason determined by EXEMPTAX. For TaxJar, this is mapped to the customer\'s exemption type.',
            ]);

            $attribute = $customerSetup->getEavConfig()->getAttribute(
                Customer::ENTITY,
                'exemptax_main_exemption_type'
            );
            $attribute->addData([
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => ['adminhtml_customer'],
            ]);
            $attribute->save();
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
