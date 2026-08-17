<?php

declare(strict_types=1);

namespace Exemptax\Integration\Model;

use Laminas\Http\Request;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Integration\Model\OauthService as MagentoOauthService;

/**
 * Local-dev friendly OAuth consumer POST.
 *
 * Magento Docker cannot verify Herd (*.test) TLS certificates by default, which
 * surfaces as Laminas "Unable to read response, or response is empty" during Activate.
 */
class OauthService extends MagentoOauthService
{
    /**
     * @inheritdoc
     */
    public function postToConsumer($consumerId, $endpointUrl)
    {
        try {
            $consumer = $this->loadConsumer($consumerId);
            $consumer->setUpdatedAt($this->gmtDate());
            $consumer->save();
            if (!$consumer->getId()) {
                throw new \Magento\Framework\Oauth\Exception(
                    __('A consumer with "%1" ID doesn\'t exist. Verify the ID and try again.', $consumerId)
                );
            }
            $consumerData = $consumer->getData();
            $verifier = $this->_tokenFactory->create()->createVerifierToken($consumerId);
            $storeBaseUrl = $this->_storeManager->getStore()->getBaseUrl();
            $this->_httpClient->setUri($endpointUrl);
            $this->_httpClient->setParameterPost(
                [
                    'oauth_consumer_key' => $consumerData['key'],
                    'oauth_consumer_secret' => $consumerData['secret'],
                    'store_base_url' => $storeBaseUrl,
                    'oauth_verifier' => $verifier->getVerifier(),
                ]
            );
            $maxredirects = $this->_dataHelper->getConsumerPostMaxRedirects();
            $timeout = $this->_dataHelper->getConsumerPostTimeout();

            $options = [
                'maxredirects' => $maxredirects,
                'timeout' => $timeout,
            ];

            if ($this->shouldRelaxSsl((string) $endpointUrl)) {
                // Magento Curl adapter keys (not Laminas sslverifypeer).
                $options['verifypeer'] = false;
                $options['verifyhost'] = 0;
            }

            $this->_httpClient->setOptions($options);
            $this->_httpClient->setMethod(Request::METHOD_POST);
            $this->_httpClient->send();
            return $verifier->getVerifier();
        } catch (\Magento\Framework\Exception\LocalizedException $exception) {
            throw $exception;
        } catch (\Magento\Framework\Oauth\Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            $this->_logger->critical($exception);
            throw new \Magento\Framework\Oauth\Exception(
                __('The attempt to post data to consumer failed due to an unexpected error. Please try again later.')
            );
        }
    }

    private function shouldRelaxSsl(string $endpointUrl): bool
    {
        $host = strtolower((string) parse_url($endpointUrl, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || $host === 'host.docker.internal') {
            return true;
        }

        return (bool) preg_match('/\.(test|local|localhost)$/', $host);
    }

    private function gmtDate(): string
    {
        /** @var DateTime $date */
        $date = ObjectManager::getInstance()->get(DateTime::class);

        return (string) $date->gmtDate();
    }
}
