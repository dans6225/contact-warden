<?php

declare(strict_types=1);

namespace ContactWarden\Tests\Admin;

use ContactWarden\Admin\AdminMaintenance;
use ContactWarden\Admin\MaintenanceActions;
use PHPUnit\Framework\TestCase;

final class MaintenanceActionsTest extends TestCase
{
    public function test_purge_tokens_reports_removed_count(): void
    {
        $maintenance = $this->createMock(AdminMaintenance::class);
        $maintenance->expects($this->once())->method('purgeExpiredTokens')->willReturn(5);

        $result = (new MaintenanceActions($maintenance))->handle('purge_tokens', []);

        $this->assertTrue($result->success);
        $this->assertSame('5 expired or used tokens removed.', $result->message);
    }

    public function test_purge_submissions_uses_days_input_for_cutoff(): void
    {
        $maintenance = $this->createMock(AdminMaintenance::class);
        $maintenance->expects($this->once())
            ->method('purgeSubmissionsOlderThan')
            ->with($this->callback(static function (\DateTimeImmutable $cutoff): bool {
                $expected = (new \DateTimeImmutable())->modify('-30 days');

                return abs($cutoff->getTimestamp() - $expected->getTimestamp()) < 5;
            }))
            ->willReturn(12);

        $result = (new MaintenanceActions($maintenance))->handle('purge_submissions', ['days' => '30']);

        $this->assertTrue($result->success);
        $this->assertSame('12 submission log entries older than 30 days removed.', $result->message);
    }

    public function test_purge_abuse_uses_days_input_for_cutoff(): void
    {
        $maintenance = $this->createMock(AdminMaintenance::class);
        $maintenance->expects($this->once())->method('purgeAbuseEventsOlderThan')->willReturn(3);

        $result = (new MaintenanceActions($maintenance))->handle('purge_abuse', ['days' => '7']);

        $this->assertTrue($result->success);
        $this->assertSame('3 abuse log entries older than 7 days removed.', $result->message);
    }

    public function test_a_missing_or_negative_days_input_means_zero_days(): void
    {
        $maintenance = $this->createMock(AdminMaintenance::class);
        $maintenance->expects($this->exactly(2))->method('purgeSubmissionsOlderThan')->willReturn(0);

        $actions = new MaintenanceActions($maintenance);

        $this->assertSame('0 submission log entries older than 0 days removed.', $actions->handle('purge_submissions', [])->message);
        $this->assertSame('0 submission log entries older than 0 days removed.', $actions->handle('purge_submissions', ['days' => '-5'])->message);
    }

    public function test_forget_reputation_requires_a_subject(): void
    {
        $maintenance = $this->createMock(AdminMaintenance::class);
        $maintenance->expects($this->never())->method('forgetReputation');

        $result = (new MaintenanceActions($maintenance))->handle('forget_reputation', ['subject' => '   ']);

        $this->assertFalse($result->success);
    }

    public function test_forget_reputation_clears_the_given_subject(): void
    {
        $maintenance = $this->createMock(AdminMaintenance::class);
        $maintenance->expects($this->once())->method('forgetReputation')->with('1.2.3.4');

        $result = (new MaintenanceActions($maintenance))->handle('forget_reputation', ['subject' => ' 1.2.3.4 ']);

        $this->assertTrue($result->success);
        $this->assertSame('Reputation for 1.2.3.4 cleared.', $result->message);
    }

    public function test_reset_reputation_clears_everything(): void
    {
        $maintenance = $this->createMock(AdminMaintenance::class);
        $maintenance->expects($this->once())->method('resetAllReputation');

        $result = (new MaintenanceActions($maintenance))->handle('reset_reputation', []);

        $this->assertTrue($result->success);
    }

    public function test_unknown_action_fails_without_touching_anything(): void
    {
        $maintenance = $this->createMock(AdminMaintenance::class);
        $maintenance->expects($this->never())->method($this->anything());

        $result = (new MaintenanceActions($maintenance))->handle('not_a_real_action', []);

        $this->assertFalse($result->success);
        $this->assertSame('Unknown action "not_a_real_action".', $result->message);
    }
}
