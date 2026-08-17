<?php

declare(strict_types=1);

namespace Exemptax\Integration\Model\Customer\Attribute\Source;

use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

/**
 * US state/region options for EXEMPTAX Exempt Regions multiselect.
 * Option values are state codes (MI, IL) so BE / native tax hook stay unchanged.
 */
class UsStates extends AbstractSource
{
    public function __construct(
        private readonly RegionCollectionFactory $regionCollectionFactory
    ) {
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function getAllOptions(): array
    {
        if ($this->_options !== null) {
            return $this->_options;
        }

        $collection = $this->regionCollectionFactory->create();
        $collection->addCountryFilter('US');
        $collection->setOrder('default_name', 'ASC');

        $options = [];
        foreach ($collection as $region) {
            $code = strtoupper(trim((string) $region->getCode()));
            if ($code === '') {
                continue;
            }

            $name = trim((string) $region->getDefaultName());
            if ($name === '') {
                $name = trim((string) $region->getName());
            }

            $options[] = [
                'value' => $code,
                'label' => $name !== '' ? sprintf('%s (%s)', $name, $code) : $code,
            ];
        }

        $this->_options = $options;

        return $this->_options;
    }
}
