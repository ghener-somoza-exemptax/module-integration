<?php

declare(strict_types=1);

namespace Exemptax\Integration\Model\Webhook;

use Exemptax\Integration\Model\Config;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class Client
{
    public function __construct(
        private readonly Config $config,
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function post(array $payload, ?int $websiteId = null): bool
    {
        if (!$this->config->canSendWebhooks($websiteId)) {
            return false;
        }

        $url = $this->config->getWebhookUrl($websiteId);
        $body = $this->json->serialize($payload);
        $curl = $this->curlFactory->create();

        if (!$this->config->shouldVerifySsl($websiteId)) {
            $curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
            $curl->setOption(CURLOPT_SSL_VERIFYHOST, 0);
        }

        $curl->setTimeout(90);
        $exKey = $this->config->getExKey($websiteId);
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'ex-key' => $exKey,
            'X-Exemptax-Hmac-Sha256' => base64_encode(hash_hmac('sha256', $body, $exKey, true)),
        ];
        $curl->setHeaders($headers);

        // Force raw JSON body (Curl::post() may treat arrays as form fields).
        $curl->setOption(CURLOPT_POST, true);
        $curl->setOption(CURLOPT_POSTFIELDS, $body);
        $curl->setOption(CURLOPT_CUSTOMREQUEST, 'POST');

        try {
            $curl->post($url, $body);
            $status = (int) $curl->getStatus();
            if ($status < 200 || $status >= 300) {
                $this->logger->warning('Exemptax webhook non-2xx response', [
                    'status' => $status,
                    'body' => substr((string) $curl->getBody(), 0, 500),
                    'url' => $url,
                    'scope' => $payload['scope'] ?? null,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Exemptax webhook request failed', [
                'error' => $e->getMessage(),
                'url' => $url,
                'scope' => $payload['scope'] ?? null,
            ]);

            return false;
        }
    }
}
