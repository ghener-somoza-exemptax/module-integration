<?php

declare(strict_types=1);

namespace Exemptax\Integration\Block\Customer;

use Exemptax\Integration\Model\Customer\EcommerceDropUrlBuilder;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Builds the EXEMPTAX ecommerce-drop URL for logged-in Magento customers
 * (iframe page or popup), mirroring Shopify theme embeds.
 */
class EcommerceDropLink extends Template
{
    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly EcommerceDropUrlBuilder $urlBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isVisible(): bool
    {
        return $this->getEcommerceDropUrl() !== '';
    }

    public function getLinkLabel(): string
    {
        return (string) ($this->getData('link_label') ?: __('Tax-Exempt Certificates'));
    }

    /**
     * Full ecommerce-drop URL with Magento customer context query params.
     */
    public function getEcommerceDropUrl(): string
    {
        if (!$this->customerSession->isLoggedIn()) {
            return '';
        }

        return $this->urlBuilder->build($this->customerSession->getCustomer());
    }
}
