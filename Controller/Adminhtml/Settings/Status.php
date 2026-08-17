<?php

declare(strict_types=1);

namespace Exemptax\Integration\Controller\Adminhtml\Settings;

use Exemptax\Integration\Model\Config;
use Exemptax\Integration\Model\Settings\Client as SettingsClient;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Status extends Action
{
    public const ADMIN_RESOURCE = 'Exemptax_Integration::config';

    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly SettingsClient $settingsClient,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->canManageCompanySettings()) {
            $data = $this->config->getCompanySettings();

            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'data' => $data,
                'message' => (string) __(
                    'Configure Settings URL and EXEMPTAX ex-key under EXEMPTAX → Integration first.'
                ),
            ]);
        }

        try {
            $data = $this->settingsClient->getSettings();

            return $result->setData([
                'success' => true,
                'data' => $data,
                'message' => !empty($data['locked'])
                    ? (string) __('Integration settings are locked until your previous sync is complete.')
                    : (string) __('Settings refreshed from EXEMPTAX.'),
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
