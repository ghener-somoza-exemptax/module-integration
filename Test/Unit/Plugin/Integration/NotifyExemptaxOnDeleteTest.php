<?php

declare(strict_types=1);

namespace Exemptax\Integration\Test\Unit\Plugin\Integration;

use Exemptax\Integration\Model\Integration\DeletedNotifier;
use Exemptax\Integration\Plugin\Integration\NotifyExemptaxOnDelete;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\Integration;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NotifyExemptaxOnDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!interface_exists(IntegrationServiceInterface::class) || !class_exists(Integration::class)) {
            $this->markTestSkipped('Magento Integration module is not available in this PHPUnit bootstrap.');
        }
    }

    public function test_notifies_before_proceeding_with_delete(): void
    {
        $integration = $this->createMock(Integration::class);
        $integration->method('getData')->willReturn([Integration::NAME => 'EXEMPTAX']);

        $subject = $this->createMock(IntegrationServiceInterface::class);
        $subject->expects($this->once())->method('get')->with(12)->willReturn($integration);

        $notifier = $this->createMock(DeletedNotifier::class);
        $notifier->expects($this->once())
            ->method('notifyBeforeDelete')
            ->with([Integration::NAME => 'EXEMPTAX']);

        $plugin = new NotifyExemptaxOnDelete($notifier, $this->createMock(LoggerInterface::class));
        $called = false;
        $result = $plugin->aroundDelete($subject, static function ($id) use (&$called) {
            $called = $id === 12;

            return true;
        }, 12);

        $this->assertTrue($called);
        $this->assertTrue($result);
    }

    public function test_still_deletes_when_notify_throws(): void
    {
        $subject = $this->createMock(IntegrationServiceInterface::class);
        $subject->method('get')->willThrowException(new \RuntimeException('missing'));

        $notifier = $this->createMock(DeletedNotifier::class);
        $notifier->expects($this->never())->method('notifyBeforeDelete');

        $plugin = new NotifyExemptaxOnDelete($notifier, $this->createMock(LoggerInterface::class));
        $this->assertSame('deleted', $plugin->aroundDelete($subject, static fn () => 'deleted', 9));
    }
}
