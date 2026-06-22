<?php

namespace Tests\Unit;

use App\Models\DealerLevel;
use App\Models\InviteChain;
use App\Models\InviteCode;
use App\Models\UpgradeRecord;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class StatusFlowIntegrationTest extends TestCase
{
    use CreatesTestData;

    protected array $levels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->levels = $this->createDealerLevels();
    }

    public function test_invite_code_to_chain_to_upgrade_record_full_flow()
    {
        $ownerLevel = $this->levels[3];
        $targetLevel = $this->levels[2];
        $owner = $this->createUser($ownerLevel->id, [
            'total_achievement' => 50000,
            'total_invite_count' => 20,
        ]);

        $inviteCode = InviteCode::createForUser(
            $owner->id,
            1,
            $targetLevel->id,
            100.00,
            50.00
        );

        $this->assertEquals(InviteCode::STATUS_ACTIVE, $inviteCode->status);
        $this->assertTrue($inviteCode->canUse());

        $newUser = $this->createUser();

        $newUser->inviter_id = $owner->id;
        $newUser->save();

        $chain = InviteChain::createInviteChain(
            $owner->id,
            $newUser->id,
            $inviteCode->id,
            $inviteCode->reward_amount
        );
        $inviteCode->markUsed();

        UpgradeRecord::recordUpgrade(
            $newUser->id,
            $newUser->dealer_level_id,
            $inviteCode->target_dealer_level_id,
            UpgradeRecord::TYPE_INVITE_CODE,
            null,
            null,
            $inviteCode->id,
            '使用邀请码升级'
        );

        $inviteCode->refresh();
        $this->assertEquals(InviteCode::STATUS_USED_UP, $inviteCode->status);
        $this->assertFalse($inviteCode->canUse());
        $this->assertEquals(1, $inviteCode->used_count);

        $chain->refresh();
        $this->assertEquals(InviteChain::STATUS_CONFIRMED, $chain->status);
        $this->assertEquals($inviteCode->id, $chain->invite_code_id);
        $this->assertEquals(100.00, $chain->reward_amount);

        $newUser->refresh();
        $this->assertEquals($owner->id, $newUser->inviter_id);
        $this->assertEquals($targetLevel->id, $newUser->dealer_level_id);

        $upgradeRecord = UpgradeRecord::where('user_id', $newUser->id)->latest()->first();
        $this->assertNotNull($upgradeRecord);
        $this->assertEquals(UpgradeRecord::STATUS_APPROVED, $upgradeRecord->status);
        $this->assertEquals(UpgradeRecord::TYPE_INVITE_CODE, $upgradeRecord->upgrade_type);
        $this->assertEquals($inviteCode->id, $upgradeRecord->invite_code_id);
        $this->assertEquals($targetLevel->reward_bonus, $upgradeRecord->reward_bonus);

        $chain->markRewarded();
        $upgradeRecord->markRewarded();

        $this->assertEquals(InviteChain::STATUS_REWARDED, $chain->status);
        $this->assertEquals(UpgradeRecord::STATUS_REWARDED, $upgradeRecord->status);
    }

    public function test_three_level_invite_chain_with_upgrade_cycle()
    {
        $admin = $this->createAdminUser();

        $grandRootUser = $this->createUser($this->levels[4]->id, [
            'total_achievement' => 500000,
            'total_invite_count' => 500,
        ]);
        $grandRootUser->invite_path = (string)$grandRootUser->id;
        $grandRootUser->invite_depth = 0;
        $grandRootUser->save();

        $rootUser = $this->createUser($this->levels[3]->id, [
            'inviter_id' => $grandRootUser->id,
            'total_achievement' => 80000,
            'total_invite_count' => 100,
        ]);
        $rootUser->invite_path = $grandRootUser->id . '-' . $rootUser->id;
        $rootUser->invite_depth = 1;
        $rootUser->save();

        $midUser = $this->createUser($this->levels[2]->id, [
            'inviter_id' => $rootUser->id,
            'total_achievement' => 10000,
            'total_invite_count' => 20,
        ]);
        $midUser->invite_path = $grandRootUser->id . '-' . $rootUser->id . '-' . $midUser->id;
        $midUser->invite_depth = 2;
        $midUser->save();

        $inviteCodeA = InviteCode::createForUser(
            $rootUser->id,
            10,
            $this->levels[1]->id,
            200.00
        );

        $leafUser = $this->createUser();

        $leafUser->inviter_id = $midUser->id;
        $leafUser->save();

        $chain = InviteChain::createInviteChain(
            $midUser->id,
            $leafUser->id,
            $inviteCodeA->id,
            200.00
        );
        $inviteCodeA->markUsed();

        UpgradeRecord::recordUpgrade(
            $leafUser->id,
            null,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_INVITE_CODE
        );

        $this->assertEquals(3, InviteChain::where('invitee_id', $leafUser->id)->count());

        $directChain = InviteChain::where('invitee_id', $leafUser->id)->where('depth', 1)->first();
        $this->assertEquals($midUser->id, $directChain->inviter_id);
        $this->assertEquals(InviteChain::STATUS_CONFIRMED, $directChain->status);

        $depth2Chain = InviteChain::where('invitee_id', $leafUser->id)->where('depth', 2)->first();
        $this->assertEquals($rootUser->id, $depth2Chain->inviter_id);
        $this->assertEquals(InviteChain::STATUS_CONFIRMED, $depth2Chain->status);

        $depth3Chain = InviteChain::where('invitee_id', $leafUser->id)->where('depth', 3)->first();
        $this->assertEquals($grandRootUser->id, $depth3Chain->inviter_id);
        $this->assertEquals(InviteChain::STATUS_CONFIRMED, $depth3Chain->status);

        $leafUser->refresh();
        $this->assertEquals($this->levels[1]->id, $leafUser->dealer_level_id);

        $upgradeRecord = UpgradeRecord::where('user_id', $leafUser->id)->first();
        $this->assertEquals(UpgradeRecord::STATUS_APPROVED, $upgradeRecord->status);

        $directChain->addCommission(500.00, $admin->id, '佣金月结');
        $depth2Chain->addCommission(200.00, $admin->id, '间接佣金月结');
        $depth3Chain->addCommission(80.00, $admin->id, '三级间接佣金');

        $directChain->markRewarded($admin->id, '月度邀请奖励发放');
        $upgradeRecord->markRewarded($admin->id, '升级奖励发放');

        $this->assertEquals(InviteChain::STATUS_REWARDED, $directChain->status);
        $this->assertEquals(500.00, $directChain->total_commission);
        $this->assertEquals(UpgradeRecord::STATUS_REWARDED, $upgradeRecord->status);
    }

    public function test_invite_chain_cancelled_invite_code_still_valid()
    {
        $owner = $this->createUser($this->levels[2]->id);

        $inviteCode = InviteCode::createForUser(
            $owner->id,
            3,
            $this->levels[1]->id,
            100.00
        );

        $user1 = $this->createUser();
        $user1->inviter_id = $owner->id;
        $user1->save();

        $chain1 = InviteChain::createInviteChain($owner->id, $user1->id, $inviteCode->id, 100.00);
        $inviteCode->markUsed();

        $user2 = $this->createUser();
        $user2->inviter_id = $owner->id;
        $user2->save();

        $chain2 = InviteChain::createInviteChain($owner->id, $user2->id, $inviteCode->id, 100.00);
        $inviteCode->markUsed();

        $this->assertEquals(InviteCode::STATUS_ACTIVE, $inviteCode->status);
        $this->assertEquals(1, $inviteCode->remainingUses());

        $chain1->cancel(null, '用户1取消邀请关系');

        $this->assertEquals(InviteChain::STATUS_CANCELLED, $chain1->status);
        $this->assertEquals(InviteChain::STATUS_CONFIRMED, $chain2->status);

        $inviteCode->refresh();
        $this->assertEquals(InviteCode::STATUS_ACTIVE, $inviteCode->status);
        $this->assertEquals(2, $inviteCode->used_count);

        $user3 = $this->createUser();
        $user3->inviter_id = $owner->id;
        $user3->save();

        $chain3 = InviteChain::createInviteChain($owner->id, $user3->id, $inviteCode->id, 100.00);
        $inviteCode->markUsed();

        $inviteCode->refresh();
        $this->assertEquals(InviteCode::STATUS_USED_UP, $inviteCode->status);
        $this->assertEquals(3, $inviteCode->used_count);
        $this->assertEquals(0, $inviteCode->remainingUses());
    }

    public function test_upgrade_rejected_then_auto_upgrade_again_success()
    {
        $user = $this->createUser($this->levels[0]->id, [
            'total_achievement' => 500,
            'total_invite_count' => 1,
        ]);

        $record1 = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[0]->id,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_MANUAL
        );

        $user->refresh();
        $this->assertEquals($this->levels[1]->id, $user->dealer_level_id);

        $record1->reject(null, '邀请人数不达标');

        $user->refresh();
        $this->assertEquals($this->levels[0]->id, $user->dealer_level_id);
        $this->assertEquals(UpgradeRecord::STATUS_REJECTED, $record1->status);

        $user->total_achievement = 2000;
        $user->total_invite_count = 5;
        $user->save();

        $record2 = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[0]->id,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_AUTO
        );

        $this->assertNotNull($record2);
        $this->assertEquals(UpgradeRecord::STATUS_APPROVED, $record2->status);

        $user->refresh();
        $this->assertEquals($this->levels[1]->id, $user->dealer_level_id);

        $record2->markRewarded();

        $this->assertEquals(UpgradeRecord::STATUS_REWARDED, $record2->status);

        $upgradeHistory = UpgradeRecord::where('user_id', $user->id)->get();
        $this->assertCount(2, $upgradeHistory);
    }

    public function test_dealer_level_requirements_and_invite_code_upgrade_synergy()
    {
        $lowestLevel = DealerLevel::findLowestLevel();
        $highestLevel = DealerLevel::findHighestLevel();
        $this->assertEquals(1, $lowestLevel->level);
        $this->assertEquals(5, $highestLevel->level);

        $goldLevel = $this->levels[2];
        $owner = $this->createUser($goldLevel->id, [
            'total_achievement' => 10000,
            'total_invite_count' => 25,
        ]);

        $this->assertFalse($owner->canUpgradeToLevel($goldLevel));

        $diamondLevel = $this->levels[3];
        $this->assertFalse($owner->canUpgradeToLevel($diamondLevel));

        $diamondInviteCode = InviteCode::createForUser(
            $owner->id,
            1,
            $diamondLevel->id,
            1000.00,
            500.00
        );

        $user = $this->createUser();

        $user->inviter_id = $owner->id;
        $user->save();

        InviteChain::createInviteChain($owner->id, $user->id, $diamondInviteCode->id, 1000.00);
        $diamondInviteCode->markUsed();

        UpgradeRecord::recordUpgrade(
            $user->id,
            null,
            $diamondLevel->id,
            UpgradeRecord::TYPE_INVITE_CODE
        );

        $user->refresh();
        $this->assertEquals($diamondLevel->id, $user->dealer_level_id);

        $upgrade = UpgradeRecord::where('user_id', $user->id)->latest()->first();
        $this->assertEquals($diamondLevel->id, $upgrade->new_level_id);
        $this->assertEquals(UpgradeRecord::TYPE_INVITE_CODE, $upgrade->upgrade_type);
        $this->assertEquals($diamondLevel->reward_bonus, $upgrade->reward_bonus);
    }

    public function test_multiple_upgrades_user_level_history_status_chain()
    {
        $user = $this->createUser($this->levels[0]->id, [
            'total_achievement' => 0,
            'total_invite_count' => 0,
        ]);

        $record1 = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[0]->id,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_AUTO
        );
        $this->assertEquals(UpgradeRecord::STATUS_APPROVED, $record1->status);
        $record1->markRewarded();

        $user->refresh();
        $user->total_achievement = 10000;
        $user->total_invite_count = 15;
        $user->save();

        $record2 = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[1]->id,
            $this->levels[2]->id,
            UpgradeRecord::TYPE_AUTO
        );
        $this->assertEquals(UpgradeRecord::STATUS_APPROVED, $record2->status);
        $record2->markRewarded();

        $user->refresh();
        $user->total_achievement = 50000;
        $user->total_invite_count = 50;
        $user->save();

        $record3 = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[2]->id,
            $this->levels[3]->id,
            UpgradeRecord::TYPE_MANUAL
        );
        $record3->approve();
        $record3->markRewarded();

        $history = UpgradeRecord::getUserUpgradeHistory($user->id, 10);
        $this->assertCount(3, $history);

        $expected = [
            UpgradeRecord::STATUS_REWARDED,
            UpgradeRecord::STATUS_REWARDED,
            UpgradeRecord::STATUS_REWARDED,
        ];

        $actual = $history->pluck('status')->toArray();
        $this->assertEquals($expected, $actual);

        $user->refresh();
        $this->assertEquals($this->levels[3]->id, $user->dealer_level_id);
    }

    public function test_operation_logs_record_complete_status_history()
    {
        $user = $this->createUser($this->levels[0]->id);

        $record = UpgradeRecord::recordUpgrade(
            $user->id,
            $this->levels[0]->id,
            $this->levels[1]->id,
            UpgradeRecord::TYPE_MANUAL
        );

        $this->assertCount(1, $record->operation_logs);
        $this->assertEquals('create', $record->operation_logs[0]['action']);
        $this->assertEquals(UpgradeRecord::STATUS_PENDING, $record->operation_logs[0]['old_status']);
        $this->assertEquals(UpgradeRecord::STATUS_PENDING, $record->operation_logs[0]['new_status']);

        $record->approve();
        $this->assertCount(2, $record->operation_logs);
        $this->assertEquals('approve', $record->operation_logs[1]['action']);
        $this->assertEquals(UpgradeRecord::STATUS_PENDING, $record->operation_logs[1]['old_status']);
        $this->assertEquals(UpgradeRecord::STATUS_APPROVED, $record->operation_logs[1]['new_status']);

        $record->markRewarded();
        $this->assertCount(3, $record->operation_logs);
        $this->assertEquals('reward', $record->operation_logs[2]['action']);
        $this->assertEquals(UpgradeRecord::STATUS_APPROVED, $record->operation_logs[2]['old_status']);
        $this->assertEquals(UpgradeRecord::STATUS_REWARDED, $record->operation_logs[2]['new_status']);

        $statusFlow = array_map(function ($log) {
            return [$log['old_status'], $log['new_status']];
        }, $record->operation_logs);

        $this->assertEquals([
            [UpgradeRecord::STATUS_PENDING, UpgradeRecord::STATUS_PENDING],
            [UpgradeRecord::STATUS_PENDING, UpgradeRecord::STATUS_APPROVED],
            [UpgradeRecord::STATUS_APPROVED, UpgradeRecord::STATUS_REWARDED],
        ], $statusFlow);
    }
}
