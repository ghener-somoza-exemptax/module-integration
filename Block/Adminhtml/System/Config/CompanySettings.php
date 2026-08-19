<?php

declare(strict_types=1);

namespace Exemptax\Integration\Block\Adminhtml\System\Config;

use Exemptax\Integration\Model\Config;
use Exemptax\Integration\Model\Settings\Client as SettingsClient;
use Exemptax\Integration\Model\WebhookSettingsManagement;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Live EXEMPTAX Adobe Commerce company settings (HMAC GET/POST to Laravel).
 */
class CompanySettings extends Field
{
    protected $_template = 'Exemptax_Integration::system/config/company_settings.phtml';

    /** @var array<string, mixed>|null */
    private ?array $settings = null;

    private ?string $loadError = null;

    /** @var list<array{id: string, code: string}>|null */
    private ?array $localCustomerGroups = null;

    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly SettingsClient $settingsClient,
        private readonly WebhookSettingsManagement $webhookSettings,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Bypass Magento label/value columns so the panel can be full-width and left-aligned.
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return sprintf(
            '<tr id="row_%s"><td colspan="4" class="exemptax-settings-cell">%s</td></tr>',
            $element->getHtmlId(),
            $this->_toHtml()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        if (!$this->config->canManageCompanySettings()) {
            $this->loadError = (string) __(
                'Configure Settings URL and EXEMPTAX ex-key under EXEMPTAX → Integration first, then refresh this page.'
            );
            $this->settings = $this->config->getCompanySettings();

            return $this->settings;
        }

        try {
            $this->settings = $this->settingsClient->getSettings();
            $this->webhookSettings->persistCompanySettings($this->settings);
        } catch (\Throwable $e) {
            $this->loadError = $e->getMessage();
            $this->settings = $this->config->getCompanySettings();
        }

        return $this->settings;
    }

    public function getLoadError(): ?string
    {
        $this->getSettings();

        return $this->loadError;
    }

    public function isLocked(): bool
    {
        $settings = $this->getSettings();

        return !empty($settings['locked']);
    }

    /**
     * Raw ISO timestamp from EXEMPTAX (UTC). Display formatting is done in the browser
     * so it matches the admin's machine local timezone.
     */
    public function getRawLastSync(): ?string
    {
        $settings = $this->getSettings();
        if (!empty($settings['locked']) || empty($settings['last_sync_at'])) {
            return null;
        }

        return (string) $settings['last_sync_at'];
    }

    /**
     * Local Magento customer groups (excludes NOT LOGGED IN / id 0).
     *
     * @return list<array{id: string, code: string}>
     */
    public function getLocalCustomerGroups(): array
    {
        if ($this->localCustomerGroups !== null) {
            return $this->localCustomerGroups;
        }

        $options = [];
        try {
            $groups = $this->groupRepository->getList($this->searchCriteriaBuilder->create())->getItems();
            foreach ($groups as $group) {
                $id = (string) $group->getId();
                if ($id === '' || $id === '0') {
                    continue;
                }
                $code = trim((string) $group->getCode());
                $options[] = [
                    'id' => $id,
                    'code' => $code !== '' ? $code : $id,
                ];
            }
        } catch (\Throwable) {
            $options = [];
        }

        usort($options, static fn (array $a, array $b): int => strcasecmp($a['code'], $b['code']));
        $this->localCustomerGroups = $options;

        return $this->localCustomerGroups;
    }

    /**
     * Selected group IDs for the multiselect. Empty EXEMPTAX allow-list ⇒ all local groups.
     *
     * @return list<string>
     */
    public function getSelectedCustomerGroupIds(): array
    {
        $allIds = array_column($this->getLocalCustomerGroups(), 'id');
        $saved = $this->getSettings()['ac_customer_groups'] ?? [];
        if (!is_array($saved) || $saved === []) {
            return $allIds;
        }

        $saved = array_map('strval', $saved);

        return array_values(array_intersect($allIds, $saved));
    }

    public function getStatusUrl(): string
    {
        return $this->getUrl('exemptax/settings/status');
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('exemptax/settings/save');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function getTaxEngineOptions(): array
    {
        return [
            ['value' => 'magento', 'label' => (string) __('Magento native tax')],
            ['value' => 'taxjar', 'label' => (string) __('TaxJar')],
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function getExemptionAutomationOptions(): array
    {
        return [
            ['value' => 0, 'label' => (string) __('Do not update')],
            ['value' => 1, 'label' => (string) __('Exempt with pending certificate (customer friendly)')],
            ['value' => 2, 'label' => (string) __('Exempt with active certificate (business friendly)')],
        ];
    }
}
