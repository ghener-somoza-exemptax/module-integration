<?php

declare(strict_types=1);

namespace Exemptax\Integration\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Exemptax\Integration\Model\Customer\Attribute\Source\ExemptionStatus;

/**
 * Convert exemptax_exemption_status from text to Active/Inactive select.
 * Stored values stay "active" or empty (empty = Inactive).
 */
class ConvertExemptionStatusToSelect implements DataPatchInterface
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

        if (!$customerSetup->getAttributeId(Customer::ENTITY, 'exemptax_exemption_status')) {
            $this->moduleDataSetup->getConnection()->endSetup();

            return $this;
        }

        $customerSetup->updateAttribute(
            Customer::ENTITY,
            'exemptax_exemption_status',
            'frontend_input',
            'select'
        );
        $customerSetup->updateAttribute(
            Customer::ENTITY,
            'exemptax_exemption_status',
            'source_model',
            ExemptionStatus::class
        );
        $customerSetup->updateAttribute(
            Customer::ENTITY,
            'exemptax_exemption_status',
            'note',
            'Exemption status from EXEMPTAX. Active when certificate coverage is applied; '
            . 'Inactive when coverage is cleared.'
        );

        $attribute = $customerSetup->getEavConfig()->getAttribute(
            Customer::ENTITY,
            'exemptax_exemption_status'
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
            ReorderExemptaxCustomerAttributes::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
