<?php

declare(strict_types=1);

namespace Exemptax\Integration\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Exemptax\Integration\Model\Customer\Attribute\Source\UsStates;

/**
 * Convert exemptax_exemption_states from plain text to US-state multiselect
 * (Account Information). Stored values remain comma-separated state codes.
 */
class ConvertExemptionStatesToMultiselect implements DataPatchInterface
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

        if (!$customerSetup->getAttributeId(Customer::ENTITY, 'exemptax_exemption_states')) {
            $this->moduleDataSetup->getConnection()->endSetup();

            return $this;
        }

        $customerSetup->updateAttribute(
            Customer::ENTITY,
            'exemptax_exemption_states',
            'frontend_input',
            'multiselect'
        );
        $customerSetup->updateAttribute(
            Customer::ENTITY,
            'exemptax_exemption_states',
            'frontend_label',
            'EXEMPTAX Exempt Regions'
        );
        $customerSetup->updateAttribute(
            Customer::ENTITY,
            'exemptax_exemption_states',
            'backend_model',
            ArrayBackend::class
        );
        $customerSetup->updateAttribute(
            Customer::ENTITY,
            'exemptax_exemption_states',
            'source_model',
            UsStates::class
        );
        $customerSetup->updateAttribute(
            Customer::ENTITY,
            'exemptax_exemption_states',
            'note',
            'U.S. states where the customer is tax-exempt based on their EXEMPTAX certificates and integration settings. '
            . 'The exemption is applied according to the order\'s ship-to state.'
        );

        $attribute = $customerSetup->getEavConfig()->getAttribute(
            Customer::ENTITY,
            'exemptax_exemption_states'
        );
        $customerEntity = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        $attributeSetId = (int) $customerEntity->getDefaultAttributeSetId();
        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = (int) $attributeSet->getDefaultGroupId($attributeSetId);

        $attribute->addData([
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
            'used_in_forms' => ['adminhtml_customer'],
        ]);
        $attribute->save();

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [
            AddCustomerExemptionAttributes::class,
            CapitalizeExemptaxAttributeLabels::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
