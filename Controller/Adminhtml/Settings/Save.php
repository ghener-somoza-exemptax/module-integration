<?php

declare(strict_types=1);

namespace Exemptax\Integration\Controller\Adminhtml\Settings;

use Exemptax\Integration\Model\Config;
use Exemptax\Integration\Model\Settings\Client as SettingsClient;
use Exemptax\Integration\Model\WebhookSettingsManagement;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'Exemptax_Integration::config';

    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly SettingsClient $settingsClient,
        private readonly WebhookSettingsManagement $webhookSettings,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        $taxEngine = (string) $this->getRequest()->getParam('tax_engine', '');
        $taxExemptFlag = (int) $this->getRequest()->getParam('tax_exempt_flag', -1);
        if ($taxExemptFlag === 3) {
            $taxExemptFlag = 2;
        }
        $syncCustomerTags = (int) $this->getRequest()->getParam('sync_customer_tags', 0);
        $grandfatheredEntireExemption = (int) $this->getRequest()->getParam('grandfathered_entire_exemption', 0);
        $customerGroups = $this->getRequest()->getParam('ac_customer_groups', []);
        if (!is_array($customerGroups)) {
            $customerGroups = [];
        }
        $customerGroups = array_values(array_unique(array_map(
            static fn ($id): string => trim((string) $id),
            $customerGroups
        )));
        $customerGroups = array_values(array_filter($customerGroups, static fn (string $id): bool => $id !== ''));

        if (
            !in_array($taxEngine, ['magento', 'taxjar'], true)
            || !in_array($taxExemptFlag, [0, 1, 2], true)
            || !in_array($syncCustomerTags, [0, 1], true)
            || !in_array($grandfatheredEntireExemption, [0, 1], true)
        ) {
            return $result->setHttpResponseCode(422)->setData([
                'success' => false,
                'message' => (string) __('Invalid tax engine, exemption automation, legacy exemption, or sync tags value.'),
            ]);
        }

        if (!$this->config->canManageCompanySettings()) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string) __(
                    'Configure Settings URL and EXEMPTAX ex-key under EXEMPTAX → Integration first.'
                ),
            ]);
        }

        try {
            $data = $this->settingsClient->putSettings([
                'tax_engine' => $taxEngine,
                'tax_exempt_flag' => $taxExemptFlag,
                'ac_customer_groups' => $customerGroups,
                'sync_customer_tags' => $syncCustomerTags,
                'grandfathered_entire_exemption' => $grandfatheredEntireExemption,
            ]);
            $this->webhookSettings->persistCompanySettings($data);

            return $result->setData([
                'success' => true,
                'data' => $data,
                'message' => (string) ($data['message'] ?? __('Adobe Commerce settings saved. Updates will sync momentarily.')),
            ]);
        } catch (\Throwable $e) {
            $status = (int) $e->getCode();
            if ($status < 400 || $status > 599) {
                $status = 400;
            }

            return $result->setHttpResponseCode($status)->setData([
                'success' => false,
                'data' => $this->config->getCompanySettings(),
                'message' => $e->getMessage(),
            ]);
        }
    }
}
