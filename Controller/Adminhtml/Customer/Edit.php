<?php

declare(strict_types=1);

namespace Exemptax\Integration\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Redirect;

/**
 * Deep-link entry from EXEMPTAX customer view.
 *
 * Magento admin URLs require a per-session secret key; links without it are
 * rejected and land on the Dashboard. This action is listed in $_publicActions
 * so EXEMPTAX can open /admin/exemptax/customer/edit/id/{id} without a key,
 * then redirect to the real customer edit URL with a valid key.
 */
class Edit extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Customer::manage';

    /**
     * @var string[]
     */
    protected $_publicActions = ['edit'];

    public function execute()
    {
        $customerId = (int) $this->getRequest()->getParam('id');

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($customerId <= 0) {
            return $resultRedirect->setPath('customer/index');
        }

        return $resultRedirect->setPath('customer/index/edit', ['id' => $customerId]);
    }
}
