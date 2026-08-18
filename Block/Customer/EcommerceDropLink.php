<?php

declare(strict_types=1);

namespace Exemptax\Integration\Block\Customer;

use Exemptax\Integration\Model\Config;
use Exemptax\Integration\Model\Customer\EcommerceDropUrlBuilder;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url as CustomerUrl;
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
        private readonly CustomerUrl $customerUrl,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isVisible(): bool
    {
        return $this->getEcommerceDropUrl() !== '';
    }

    public function isCustomerLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    public function getLoginUrl(): string
    {
        return $this->customerUrl->getLoginUrl();
    }

    /**
     * The dedicated storefront page renders its own CMS heading, so it opts out.
     */
    public function shouldShowHeading(): bool
    {
        $showHeading = $this->getData('show_heading');

        return $showHeading === null || (bool) $showHeading;
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

    /**
     * Storefront CMS page that embeds the drop, so links can stay on the merchant's domain.
     */
    public function getCertificatesPageUrl(): string
    {
        return $this->_urlBuilder->getUrl(null, [
            '_direct' => $this->config->getCertificatesPageIdentifier(),
        ]);
    }
}
