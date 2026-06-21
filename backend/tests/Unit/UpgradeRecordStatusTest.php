<?php

namespace Tests\Unit;

use App\Models\DealerLevel;
use App\Models\UpgradeRecord;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class UpgradeRecordStatusTest extends TestCase
{
    use CreatesTestData;

    protected array $levels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->levels = $this->createDealerLevels();
    }

    public function test_auto_upgrade_with_bonus_starts_as_approved_status()
    {
        $user = $this->createUser($this->levels[0]->id, [
            'total_achievement' => 2000,
            'total_invite_count' => 5,
        ]);
        $targetLevel = $this->levels[1];

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[0]->id,
            $targetLevel->id,
            UpgradeRecord::TYPE_AUTO
        );

        $this->assertNotNull($record);
        $this->assertEquals(UpgradeRecord::STATUS_APPROVED, $record->status);
        $this->assertEquals('审核通过', $record->getStatusLabel());
        $this->assertTrue($record->isApproved());
        $this->assertFalse($record->is_rewarded);
        $this->assertEquals($targetLevel->reward_bonus, $record->reward_bonus);
        $this->assertEquals(2000, $record->achievement_at_upgrade);
        $this->assertEquals(5, $record->invite_count_at_upgrade);
    }

    public function test_auto_upgrade_without_bonus_starts_as_rewarded_status()
    {
        $user = $this->createUser(null, [
            'total_achievement' => 0,
            'total_invite_count' => 0,
        ]);
        $targetLevel = $this->levels[0];

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            null,
            $targetLevel->id,
            UpgradeRecord::TYPE_AUTO
        );

        $this->assertNotNull($record);
        $this->assertEquals(UpgradeRecord::STATUS_REWARDED, $record->status);
        $this->assertEquals('已发奖', $record->getStatusLabel());
        $this->assertTrue($record->isRewardedStatus());
        $this->assertTrue($record->is_rewarded);
        $this->assertNotNull($record->rewarded_at);
    }

    public function test_invite_code_upgrade_type_starts_as_approved_with_bonus()
    {
        $user = $this->createUser(null, [
            'total_achievement' => 0,
            'total_invite_count' => 0,
        ]);
        $targetLevel = $this->levels[2];

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            null,
            $targetLevel->id,
            UpgradeRecord::TYPE_INVITE_CODE
        );

        $this->assertNotNull($record);
        $this->assertEquals(UpgradeRecord::STATUS_APPROVED, $record->status);
        $this->assertEquals('邀请码升级', $record->getUpgradeTypeLabel());
        $this->assertTrue($record->canReward());
    }

    public function test_manual_upgrade_starts_as_pending_status()
    {
        $user = $this->createUser($this->levels[0]->id, [
            'total_achievement' => 100,
            'total_invite_count' => 1,
        ]);

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[0]->id,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_MANUAL
        );

        $this->assertNotNull($record);
        $this->assertEquals(UpgradeRecord::STATUS_PENDING, $record->status);
        $this->assertEquals('待审核', $record->getStatusLabel());
        $this->assertEquals('手动升级', $record->getUpgradeTypeLabel());
        $this->assertTrue($record->isPending());
        $this->assertTrue($record->canApprove());
        $this->assertTrue($record->canReject());
        $this->assertTrue($record->canReward());
    }

    public function test_admin_upgrade_starts_as_pending_status()
    {
        $user = $this->createUser($this->levels[1]->id);

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[1]->id,
            $this->levels[3]->id,
            UpgradeRecord::TYPE_ADMIN
        );

        $this->assertNotNull($record);
        $this->assertEquals(UpgradeRecord::STATUS_PENDING, $record->status);
        $this->assertEquals('后台调整', $record->getUpgradeTypeLabel());
    }

    public function test_approve_pending_with_bonus_status_flow()
    {
        $user = $this->createUser($this->levels[0]->id);
        $reviewer = $this->createAdminUser();

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[0]->id,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_MANUAL
        );

        $result = $record->approve($reviewer->id, '审核通过，业绩达标');

        $this->assertTrue($result);
        $this->assertEquals(UpgradeRecord::STATUS_APPROVED, $record->status);
        $this->assertEquals($reviewer->id, $record->reviewer_id);
        $this->assertNotNull($record->reviewed_at);
        $this->assertFalse($record->canApprove());
        $this->assertFalse($record->canReject());
        $this->assertTrue($record->canReward());

        $logs = $record->operation_logs;
        $lastLog = end($logs);
        $this->assertEquals('approve', $lastLog['action']);
        $this->assertEquals(UpgradeRecord::STATUS_PENDING, $lastLog['old_status']);
        $this->assertEquals(UpgradeRecord::STATUS_APPROVED, $lastLog['new_status']);
    }

    public function test_approve_pending_without_bonus_auto_marks_rewarded()
    {
        $user = $this->createUser(null);
        $reviewer = $this->createAdminUser();

        $record = UpgradeRecord::create([
            'user_id' => $user->id,
            'old_level_id' => null,
            'new_level_id' => $this->levels[0]->id,
            'upgrade_type' => UpgradeRecord::TYPE_MANUAL,
            'achievement_at_upgrade' => 0,
            'invite_count_at_upgrade' => 0,
            'reward_bonus' => 0,
            'is_rewarded' => false,
            'status' => UpgradeRecord::STATUS_PENDING,
            'operation_logs' => [],
        ]);

        $result = $record->approve($reviewer->id);

        $this->assertTrue($result);
        $this->assertEquals(UpgradeRecord::STATUS_REWARDED, $record->status);
        $this->assertTrue($record->is_rewarded);
        $this->assertNotNull($record->rewarded_at);
    }

    public function test_reject_pending_status_and_rollback_user_level()
    {
        $user = $this->createUser($this->levels[0]->id, [
            'total_achievement' => 0,
            'total_invite_count' => 0,
        ]);
        $reviewer = $this->createAdminUser();

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[0]->id,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_MANUAL
        );

        $user->refresh();
        $this->assertEquals($this->levels[1]->id, $user->dealer_level_id);

        $result = $record->reject($reviewer->id, '业绩不达标，审核拒绝');

        $this->assertTrue($result);
        $this->assertEquals(UpgradeRecord::STATUS_REJECTED, $record->status);
        $this->assertEquals('审核拒绝', $record->getStatusLabel());
        $this->assertTrue($record->isRejected());
        $this->assertEquals($reviewer->id, $record->reviewer_id);
        $this->assertNotNull($record->reviewed_at);
        $this->assertFalse($record->canApprove());
        $this->assertFalse($record->canReject());
        $this->assertFalse($record->canReward());

        $user->refresh();
        $this->assertEquals($this->levels[0]->id, $user->dealer_level_id);

        $logs = $record->operation_logs;
        $lastLog = end($logs);
        $this->assertEquals('reject', $lastLog['action']);
    }

    public function test_can_not_approve_non_pending_record()
    {
        $user = $this->createUser($this->levels[0]->id);

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[0]->id,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_AUTO
        );

        $this->assertFalse($record->canApprove());

        $result = $record->approve();

        $this->assertFalse($result);
        $this->assertEquals(UpgradeRecord::STATUS_APPROVED, $record->status);
    }

    public function test_mark_rewarded_from_approved_status()
    {
        $user = $this->createUser($this->levels[0]->id);
        $operator = $this->createAdminUser();

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[0]->id,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_AUTO
        );

        $this->assertEquals(UpgradeRecord::STATUS_APPROVED, $record->status);
        $this->assertTrue($record->canReward());

        $result = $record->markRewarded($operator->id, '发放升级奖励');

        $this->assertTrue($result);
        $this->assertEquals(UpgradeRecord::STATUS_REWARDED, $record->status);
        $this->assertTrue($record->isRewardedStatus());
        $this->assertTrue($record->is_rewarded);
        $this->assertNotNull($record->rewarded_at);
        $this->assertFalse($record->canReward());

        $logs = $record->operation_logs;
        $lastLog = end($logs);
        $this->assertEquals('reward', $lastLog['action']);
        $this->assertEquals(UpgradeRecord::STATUS_APPROVED, $lastLog['old_status']);
        $this->assertEquals(UpgradeRecord::STATUS_REWARDED, $lastLog['new_status']);
    }

    public function test_mark_rewarded_from_pending_auto_approves_first()
    {
        $user = $this->createUser($this->levels[0]->id);
        $operator = $this->createAdminUser();

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[0]->id,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_MANUAL
        );

        $this->assertEquals(UpgradeRecord::STATUS_PENDING, $record->status);

        $result = $record->markRewarded($operator->id, '发奖前自动审核');

        $this->assertTrue($result);
        $this->assertEquals(UpgradeRecord::STATUS_REWARDED, $record->status);
        $this->assertTrue($record->is_rewarded);
    }

    public function test_can_not_mark_rewarded_when_already_rewarded()
    {
        $user = $this->createUser($this->levels[0]->id);

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[0]->id,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_AUTO
        );
        $record->markRewarded();

        $result = $record->markRewarded();

        $this->assertFalse($result);
        $this->assertEquals(UpgradeRecord::STATUS_REWARDED, $record->status);
    }

    public function test_can_not_mark_rewarded_when_rejected()
    {
        $user = $this->createUser($this->levels[0]->id);

        $record = UpgradeRecord::create([
            'user_id' => $user->id,
            'old_level_id' => $this->levels[0]->id,
            'new_level_id' => $this->levels[1]->id,
            'upgrade_type' => UpgradeRecord::TYPE_MANUAL,
            'achievement_at_upgrade' => 100,
            'invite_count_at_upgrade' => 1,
            'reward_bonus' => 100,
            'is_rewarded' => false,
            'status' => UpgradeRecord::STATUS_REJECTED,
            'reviewed_at' => now(),
            'operation_logs' => [],
        ]);

        $result = $record->markRewarded();

        $this->assertFalse($result);
        $this->assertEquals(UpgradeRecord::STATUS_REJECTED, $record->status);
    }

    public function test_full_lifecycle_pending_approved_rewarded()
    {
        $user = $this->createUser($this->levels[0]->id, ['total_achievement' => 500, 'total_invite_count' => 2]);

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[0]->id,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_MANUAL
        );

        $this->assertEquals(UpgradeRecord::STATUS_PENDING, $record->status);
        $this->assertEquals('warning', $record->getStatusTagType());
        $this->assertTrue($record->isUpgrade());

        $record->approve();
        $this->assertEquals(UpgradeRecord::STATUS_APPROVED, $record->status);
        $this->assertEquals('success', $record->getStatusTagType());

        $record->markRewarded();
        $this->assertEquals(UpgradeRecord::STATUS_REWARDED, $record->status);
        $this->assertEquals('primary', $record->getStatusTagType());
        $this->assertTrue($record->isRewardedStatus());
    }

    public function test_full_lifecycle_pending_rejected()
    {
        $user = $this->createUser($this->levels[0]->id);

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[0]->id,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_MANUAL
        );

        $this->assertEquals(UpgradeRecord::STATUS_PENDING, $record->status);

        $record->reject(null, '不符合升级条件');
        $this->assertEquals(UpgradeRecord::STATUS_REJECTED, $record->status);
        $this->assertEquals('danger', $record->getStatusTagType());
        $this->assertTrue($record->isRejected());
    }

    public function test_same_level_returns_null_no_record()
    {
        $user = $this->createUser($this->levels[1]->id);

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[1]->id,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_AUTO
        );

        $this->assertNull($record);
    }

    public function test_downgrade_is_detected_correctly()
    {
        $user = $this->createUser($this->levels[3]->id);

        $record = UpgradeRecord::create([
            'user_id' => $user->id,
            'old_level_id' => $this->levels[3]->id,
            'new_level_id' => $this->levels[1]->id,
            'upgrade_type' => UpgradeRecord::TYPE_ADMIN,
            'achievement_at_upgrade' => 1000,
            'invite_count_at_upgrade' => 5,
            'reward_bonus' => 0,
            'is_rewarded' => true,
            'status' => UpgradeRecord::STATUS_REWARDED,
            'operation_logs' => [],
        ]);
        $record->load(['oldLevel', 'newLevel']);

        $this->assertTrue($record->isDowngrade());
        $this->assertFalse($record->isUpgrade());
    }

    public function test_initial_level_null_old_level_is_upgrade()
    {
        $user = $this->createUser();

        $record = UpgradeRecord::create([
            'user_id' => $user->id,
            'old_level_id' => null,
            'new_level_id' => $this->levels[0]->id,
            'upgrade_type' => UpgradeRecord::TYPE_AUTO,
            'achievement_at_upgrade' => 0,
            'invite_count_at_upgrade' => 0,
            'reward_bonus' => 0,
            'is_rewarded' => true,
            'status' => UpgradeRecord::STATUS_REWARDED,
            'operation_logs' => [],
        ]);
        $record->load(['oldLevel', 'newLevel']);

        $this->assertTrue($record->isUpgrade());
        $this->assertFalse($record->isDowngrade());
    }

    public function test_reject_without_old_level_keeps_user_level_unchanged()
    {
        $user = $this->createUser();

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            null,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_MANUAL
        );

        $user->refresh();
        $this->assertEquals($this->levels[1]->id, $user->dealer_level_id);

        $record->reject();

        $user->refresh();
        $this->assertNull($user->dealer_level_id);
    }
}
