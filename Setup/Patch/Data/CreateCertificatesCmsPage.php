<?php

declare(strict_types=1);

namespace Exemptax\Integration\Setup\Patch\Data;

use Exemptax\Integration\Model\Config;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * Storefront page that embeds the EXEMPTAX ecommerce drop, mirroring the
 * Shopify tax-exempt-customers page. Copy lives in CMS so merchants can edit it.
 */
class CreateCertificatesCmsPage implements DataPatchInterface
{
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly PageInterfaceFactory $pageFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly Config $config
    ) {
    }

    public function apply(): self
    {
        $identifier = $this->config->getCertificatesPageIdentifier();

        if ($this->pageExists($identifier)) {
            return $this;
        }

        /** @var PageInterface $page */
        $page = $this->pageFactory->create();
        $page->setIdentifier($identifier)
            ->setTitle('Tax Exempt Certificates')
            ->setContentHeading('Tax Exempt Certificates')
            ->setPageLayout('1column')
            ->setContent($this->getContent())
            ->setIsActive(true);
        $page->setData('stores', [Store::DEFAULT_STORE_ID]);

        $this->pageRepository->save($page);

        return $this;
    }

    private function pageExists(string $identifier): bool
    {
        $criteria = $this->searchCriteriaBuilder
            ->addFilter(PageInterface::IDENTIFIER, $identifier)
            ->setPageSize(1)
            ->create();

        return $this->pageRepository->getList($criteria)->getTotalCount() > 0;
    }

    private function getContent(): string
    {
        return <<<'HTML'
<p>If you're a tax exempt customer, you may submit tax exemption documents using the steps below:</p>
<ol>
    <li>Click on the <strong>Submit Certificates</strong> button to dynamically generate a tax exemption certificate through a guided flow. If you already have a completed tax document, click on the <strong>Upload Pre-Completed Certificates</strong> button.</li>
    <li>Once you submit your document(s), you will be able to proceed with checkout exempt from sales tax.</li>
    <li>Please note that if your document is expired, or is determined to be invalid at a later date, we may collect sales tax, and reach out to you offline to address any issues.</li>
</ol>
<p>If you have any questions specific to tax, please contact your tax advisor.</p>
HTML;
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
