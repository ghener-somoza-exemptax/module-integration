<?php

declare(strict_types=1);

namespace Exemptax\Integration\Plugin\Customer;

use Exemptax\Integration\Model\Customer\EcommerceDropUrlBuilder;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Customer\DataProviderWithDefaultAddresses;

/**
 * Adds a read-only per-customer EXEMPTAX certificate-form URL under Exemption Status.
 */
class EcommerceDropFormPlugin
{
    public function __construct(
        private readonly EcommerceDropUrlBuilder $urlBuilder,
        private readonly CustomerFactory $customerFactory
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public function afterGetMeta(DataProviderWithDefaultAddresses $subject, array $meta): array
    {
        $statusSort = (int) ($meta['customer']['children']['exemptax_exemption_status']['arguments']['data']['config']['sortOrder'] ?? 1001);

        $meta['customer']['children']['exemptax_ecommerce_drop_url'] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => __('EXEMPTAX Certificate Form URL'),
                        'formElement' => 'input',
                        'componentType' => 'field',
                        'dataType' => 'text',
                        'dataScope' => 'exemptax_ecommerce_drop_url',
                        'sortOrder' => $statusSort + 1,
                        'visible' => true,
                        'required' => false,
                        'notice' => __('Opens the EXEMPTAX tax-exemption certificate form for this customer.'),
                        'elementTmpl' => 'Exemptax_Integration/form/element/link',
                        '__disableTmpl' => ['label' => true, 'notice' => true],
                    ],
                ],
            ],
        ];

        return $meta;
    }

    /**
     * @param array<int|string, mixed> $result
     * @return array<int|string, mixed>
     */
    public function afterGetData(DataProviderWithDefaultAddresses $subject, array $result): array
    {
        foreach ($result as $customerId => $row) {
            if (!is_array($row) || !isset($row['customer']) || !(int) $customerId) {
                continue;
            }

            $customer = $this->customerFactory->create()->load((int) $customerId);
            $result[$customerId]['customer']['exemptax_ecommerce_drop_url'] = $this->urlBuilder->build(
                $customer,
                false
            );
        }

        return $result;
    }
}
