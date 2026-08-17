<?php

declare(strict_types=1);

namespace Exemptax\Integration\Model\Settings;

use Exemptax\Integration\Model\Config;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Live GET/PUT of EXEMPTAX Adobe Commerce company settings (HMAC auth).
 */
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
     * @return array<string, mixed>
     */
    public function getSettings(?int $websiteId = null): array
    {
        return $this->request('GET', '', null, $websiteId);
    }

    /**
     * @param array{tax_engine: string, tax_exempt_flag: int} $payload
     * @return array<string, mixed>
     */
    public function putSettings(array $payload, ?int $websiteId = null): array
    {
        $body = $this->json->serialize($payload);

        return $this->request('PUT', $body, $payload, $websiteId);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $body, ?array $payload, ?int $websiteId): array
    {
        if (!$this->config->canManageCompanySettings($websiteId)) {
            throw new \RuntimeException(
                'EXEMPTAX settings URL and ex-key must be configured under EXEMPTAX → Integration first.'
            );
        }

        $url = $this->config->getSettingsUrl($websiteId);
        $exKey = $this->config->getExKey($websiteId);
        $curl = $this->curlFactory->create();

        if (!$this->config->shouldVerifySsl($websiteId)) {
            $curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
            $curl->setOption(CURLOPT_SSL_VERIFYHOST, 0);
        }

        $curl->setTimeout(60);
        $curl->setHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'ex-key' => $exKey,
            'X-Exemptax-Hmac-Sha256' => base64_encode(hash_hmac('sha256', $body, $exKey, true)),
        ]);

        try {
            if ($method === 'GET') {
                $curl->get($url);
            } else {
                // Magento Curl::post() forces POST; BE accepts POST|PUT for settings update.
                $curl->setOption(CURLOPT_POST, true);
                $curl->setOption(CURLOPT_POSTFIELDS, $body);
                $curl->setOption(CURLOPT_CUSTOMREQUEST, 'POST');
                $curl->post($url, $body);
            }

            $status = (int) $curl->getStatus();
            $responseBody = (string) $curl->getBody();
            $decoded = [];
            if ($responseBody !== '') {
                try {
                    $decoded = $this->json->unserialize($responseBody);
                } catch (\Throwable) {
                    $decoded = ['error' => $responseBody];
                }
            }
            if (!is_array($decoded)) {
                $decoded = ['error' => 'Unexpected EXEMPTAX response'];
            }

            if ($status < 200 || $status >= 300) {
                $message = (string) ($decoded['error'] ?? $decoded['message'] ?? ('HTTP ' . $status));
                $this->logger->warning('Exemptax module settings request failed', [
                    'status' => $status,
                    'url' => $url,
                    'method' => $method,
                    'message' => $message,
                ]);
                throw new \RuntimeException($message, $status);
            }

            return $decoded;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Exemptax module settings request exception', [
                'error' => $e->getMessage(),
                'url' => $url,
                'method' => $method,
            ]);
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }
    }
}
