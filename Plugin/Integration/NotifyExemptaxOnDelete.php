<?php

declare(strict_types=1);

namespace Exemptax\Integration\Plugin\Integration;

use Exemptax\Integration\Model\Integration\DeletedNotifier;
use Magento\Integration\Api\IntegrationServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Before Magento deletes an OAuth integration, disconnect EXEMPTAX if this is the EXEMPTAX app.
 */
class NotifyExemptaxOnDelete
{
    public function __construct(
        private readonly DeletedNotifier $deletedNotifier,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param int|string $integrationId
     * @return mixed
     */
    public function aroundDelete(IntegrationServiceInterface $subject, callable $proceed, $integrationId)
    {
        try {
            $integration = $subject->get($integrationId);
            $this->deletedNotifier->notifyBeforeDelete($integration->getData());
        } catch (\Throwable $e) {
            $this->logger->warning('Exemptax integration delete notify failed', [
                'integration_id' => $integrationId,
                'error' => $e->getMessage(),
            ]);
        }

        return $proceed($integrationId);
    }
}
