<?php

declare(strict_types=1);

namespace Exemptax\Integration\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCustomerExemptionAttributes implements DataPatchInterface
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

        if (!$customerSetup->getAttributeId(Customer::ENTITY, 'exemptax_exemption_states')) {
            $customerSetup->addAttribute(Customer::ENTITY, 'exemptax_exemption_states', [
                'type' => 'varchar',
                'label' => 'EXEMPTAX Exempt Regions',
                'input' => 'multiselect',
                'source' => \Exemptax\Integration\Model\Customer\Attribute\Source\UsStates::class,
                'backend' => \Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend::class,
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 1000,
                'system' => 0,
                'note' => 'U.S. states where the customer is tax-exempt according to their EXEMPTAX certificates. '
                    . 'The exemption is applied based on the order\'s ship-to state.',
            ]);

            $attribute = $customerSetup->getEavConfig()->getAttribute(
                Customer::ENTITY,
                'exemptax_exemption_states'
            );
            $attribute->addData([
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => ['adminhtml_customer'],
            ]);
            $attribute->save();
        }

        if (!$customerSetup->getAttributeId(Customer::ENTITY, 'exemptax_exemption_status')) {
            $customerSetup->addAttribute(Customer::ENTITY, 'exemptax_exemption_status', [
                'type' => 'varchar',
                'label' => 'EXEMPTAX Exemption Status',
                'input' => 'select',
                'source' => \Exemptax\Integration\Model\Customer\Attribute\Source\ExemptionStatus::class,
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 1001,
                'system' => 0,
                'note' => 'Exemption status from EXEMPTAX. Active when certificate coverage is applied; '
                    . 'Inactive when coverage is cleared.',
            ]);

            $attribute = $customerSetup->getEavConfig()->getAttribute(
                Customer::ENTITY,
                'exemptax_exemption_status'
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
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
