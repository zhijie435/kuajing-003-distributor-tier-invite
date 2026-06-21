<?php

namespace Tests\Unit;

use App\Models\DealerLevel;
use App\Models\InviteChain;
use App\Models\InviteCode;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class InviteChainStatusTest extends TestCase
{
    use CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDealerLevels();
    }

    public function test_create_direct_invite_chain_starts_with_confirmed_status()
    {
        $inviter = $this->createUser(DealerLevel::where('level', 2)->first()->id, [
            'total_achievement' => 5000,
            'total_invite_count' => 5,
        ]);
        $invitee = $this->createUser();

        $chain = InviteChain::createInviteChain(
            $inviter->id,
            $invitee->id,
            null,
            50.00
        );

        $this->assertEquals(InviteChain::STATUS_CONFIRMED, $chain->status);
        $this->assertEquals('已确认', $chain->getStatusLabel());
        $this->assertEquals(1, $chain->depth);
        $this->assertEquals(50.00, $chain->reward_amount);
        $this->assertFalse($chain->is_rewarded);
        $this->assertNotNull($chain->confirmed_at);
        $this->assertTrue($chain->isConfirmed());
        $this->assertTrue($chain->isDirectInvite());
    }

    public function test_invite_chain_confirm_from_pending_status()
    {
        $inviter = $this->createUser();
        $invitee = $this->createUser();
        $operator = $this->createAdminUser();

        $chain = InviteChain::create([
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
            'depth' => 1,
            'commission_rate' => 10,
            'total_commission' => 0,
            'reward_amount' => 100,
            'is_rewarded' => false,
            'status' => InviteChain::STATUS_PENDING,
            'operation_logs' => [],
        ]);

        $this->assertTrue($chain->isPending());
        $this->assertTrue($chain->canConfirm());

        $result = $chain->confirm($operator->id, '确认邀请关系有效');

        $this->assertTrue($result);
        $this->assertEquals(InviteChain::STATUS_CONFIRMED, $chain->status);
        $this->assertNotNull($chain->confirmed_at);
        $this->assertEquals($operator->id, $chain->operator_id);
        $this->assertFalse($chain->canConfirm());

        $logs = $chain->operation_logs;
        $this->assertNotEmpty($logs);
        $lastLog = end($logs);
        $this->assertEquals('confirm', $lastLog['action']);
        $this->assertEquals(InviteChain::STATUS_PENDING, $lastLog['old_status']);
        $this->assertEquals(InviteChain::STATUS_CONFIRMED, $lastLog['new_status']);
    }

    public function test_can_not_confirm_already_confirmed_chain()
    {
        $inviter = $this->createUser();
        $invitee = $this->createUser();

        $chain = InviteChain::createInviteChain($inviter->id, $invitee->id);

        $this->assertFalse($chain->canConfirm());

        $result = $chain->confirm();

        $this->assertFalse($result);
        $this->assertEquals(InviteChain::STATUS_CONFIRMED, $chain->status);
    }

    public function test_cancel_confirmed_invite_chain_status_flow()
    {
        $inviter = $this->createUser();
        $invitee = $this->createUser();
        $operator = $this->createAdminUser();

        $invitee->inviter_id = $inviter->id;
        $invitee->save();

        $chain = InviteChain::createInviteChain($inviter->id, $invitee->id);

        $this->assertTrue($chain->canCancel());

        $result = $chain->cancel($operator->id, '用户主动解除邀请关系');

        $this->assertTrue($result);
        $this->assertEquals(InviteChain::STATUS_CANCELLED, $chain->status);
        $this->assertEquals('已取消', $chain->getStatusLabel());
        $this->assertNotNull($chain->cancelled_at);
        $this->assertFalse($chain->canCancel());

        $invitee->refresh();
        $this->assertNull($invitee->inviter_id);
    }

    public function test_cancel_pending_invite_chain()
    {
        $inviter = $this->createUser();
        $invitee = $this->createUser();

        $chain = InviteChain::create([
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
            'depth' => 1,
            'commission_rate' => 10,
            'total_commission' => 0,
            'reward_amount' => 0,
            'is_rewarded' => false,
            'status' => InviteChain::STATUS_PENDING,
            'operation_logs' => [],
        ]);

        $this->assertTrue($chain->canCancel());

        $result = $chain->cancel();

        $this->assertTrue($result);
        $this->assertEquals(InviteChain::STATUS_CANCELLED, $chain->status);
    }

    public function test_can_not_cancel_rewarded_chain()
    {
        $inviter = $this->createUser();
        $invitee = $this->createUser();

        $chain = InviteChain::createInviteChain($inviter->id, $invitee->id, null, 100.00);
        $chain->markRewarded();

        $this->assertFalse($chain->canCancel());

        $result = $chain->cancel();

        $this->assertFalse($result);
        $this->assertEquals(InviteChain::STATUS_REWARDED, $chain->status);
    }

    public function test_mark_rewarded_status_flow()
    {
        $inviter = $this->createUser();
        $invitee = $this->createUser();
        $operator = $this->createAdminUser();

        $chain = InviteChain::createInviteChain(
            $inviter->id,
            $invitee->id,
            null,
            100.00
        );

        $this->assertFalse($chain->is_rewarded);
        $this->assertEquals(InviteChain::STATUS_CONFIRMED, $chain->status);

        $result = $chain->markRewarded($operator->id, '手动发放邀请奖励');

        $this->assertTrue($result);
        $this->assertTrue($chain->is_rewarded);
        $this->assertEquals(InviteChain::STATUS_REWARDED, $chain->status);
        $this->assertEquals('已发奖', $chain->getStatusLabel());
        $this->assertNotNull($chain->rewarded_at);
        $this->assertEquals($operator->id, $chain->operator_id);
        $this->assertTrue($chain->isRewarded());

        $logs = $chain->operation_logs;
        $lastLog = end($logs);
        $this->assertEquals('reward', $lastLog['action']);
        $this->assertEquals(InviteChain::STATUS_CONFIRMED, $lastLog['old_status']);
        $this->assertEquals(InviteChain::STATUS_REWARDED, $lastLog['new_status']);
    }

    public function test_full_status_lifecycle_pending_confirmed_rewarded()
    {
        $inviter = $this->createUser();
        $invitee = $this->createUser();

        $chain = InviteChain::create([
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
            'depth' => 1,
            'commission_rate' => 10,
            'total_commission' => 0,
            'reward_amount' => 200.00,
            'is_rewarded' => false,
            'status' => InviteChain::STATUS_PENDING,
            'operation_logs' => [],
        ]);

        $this->assertEquals(InviteChain::STATUS_PENDING, $chain->status);
        $this->assertEquals('待确认', $chain->getStatusLabel());
        $this->assertTrue($chain->isPending());

        $chain->confirm();
        $this->assertEquals(InviteChain::STATUS_CONFIRMED, $chain->status);
        $this->assertTrue($chain->isConfirmed());

        $chain->markRewarded();
        $this->assertEquals(InviteChain::STATUS_REWARDED, $chain->status);
        $this->assertTrue($chain->isRewarded());
    }

    public function test_full_status_lifecycle_pending_cancelled()
    {
        $inviter = $this->createUser();
        $invitee = $this->createUser();

        $chain = InviteChain::create([
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
            'depth' => 1,
            'commission_rate' => 10,
            'total_commission' => 0,
            'reward_amount' => 0,
            'is_rewarded' => false,
            'status' => InviteChain::STATUS_PENDING,
            'operation_logs' => [],
        ]);

        $this->assertEquals(InviteChain::STATUS_PENDING, $chain->status);

        $chain->cancel();
        $this->assertEquals(InviteChain::STATUS_CANCELLED, $chain->status);
        $this->assertTrue($chain->isCancelled());
    }

    public function test_create_ancestor_chains_with_multi_depth()
    {
        $levels = DealerLevel::orderBy('level')->get();
        $userA = $this->createUser($levels[3]->id, ['total_achievement' => 50000, 'total_invite_count' => 50]);
        $userA->invite_path = (string)$userA->id;
        $userA->invite_depth = 0;
        $userA->save();

        $userB = $this->createUser($levels[2]->id, ['inviter_id' => $userA->id]);
        $userB->invite_path = "{$userA->id}-{$userB->id}";
        $userB->invite_depth = 1;
        $userB->save();

        $userC = $this->createUser($levels[1]->id, ['inviter_id' => $userB->id]);
        $userC->invite_path = "{$userA->id}-{$userB->id}-{$userC->id}";
        $userC->invite_depth = 2;
        $userC->save();

        $userD = $this->createUser();

        $chain = InviteChain::createInviteChain($userC->id, $userD->id);

        $directChains = InviteChain::where('invitee_id', $userD->id)
            ->where('depth', 1)
            ->get();
        $this->assertCount(1, $directChains);
        $this->assertEquals($userC->id, $directChains[0]->inviter_id);

        $depth2Chains = InviteChain::where('invitee_id', $userD->id)
            ->where('depth', 2)
            ->get();
        $this->assertCount(1, $depth2Chains);
        $this->assertEquals($userB->id, $depth2Chains[0]->inviter_id);

        $depth3Chains = InviteChain::where('invitee_id', $userD->id)
            ->where('depth', 3)
            ->get();
        $this->assertCount(1, $depth3Chains);
        $this->assertEquals($userA->id, $depth3Chains[0]->inviter_id);

        $allChains = InviteChain::where('invitee_id', $userD->id)->get();
        foreach ($allChains as $c) {
            $this->assertEquals(InviteChain::STATUS_CONFIRMED, $c->status);
        }
    }

    public function test_indirect_invite_depth_attributes()
    {
        $inviter = $this->createUser();
        $invitee = $this->createUser();

        $chain = InviteChain::create([
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
            'depth' => 3,
            'commission_rate' => 5,
            'total_commission' => 0,
            'reward_amount' => 0,
            'is_rewarded' => false,
            'status' => InviteChain::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'operation_logs' => [],
            'remark' => '深度3间接邀请',
        ]);

        $this->assertTrue($chain->isIndirectInvite());
        $this->assertFalse($chain->isDirectInvite());
        $this->assertEquals('success', $chain->getStatusTagType());
    }

    public function test_add_commission_preserves_status()
    {
        $inviter = $this->createUser();
        $invitee = $this->createUser();

        $chain = InviteChain::createInviteChain($inviter->id, $invitee->id);

        $originalStatus = $chain->status;
        $result = $chain->addCommission(150.50, null, '首次佣金结算');

        $this->assertTrue($result);
        $this->assertEquals($originalStatus, $chain->status);
        $this->assertEquals(150.50, $chain->total_commission);
        $logs = $chain->operation_logs;
        $this->assertNotEmpty($logs);
    }

    public function test_status_tag_types_match_correct_statuses()
    {
        $inviter1 = $this->createUser();
        $invitee1 = $this->createUser();
        $baseData = [
            'inviter_id' => $inviter1->id,
            'invitee_id' => $invitee1->id,
            'depth' => 1,
            'commission_rate' => 10,
            'total_commission' => 0,
            'reward_amount' => 0,
            'is_rewarded' => false,
            'operation_logs' => [],
        ];
        $pending = InviteChain::create(array_merge($baseData, ['status' => InviteChain::STATUS_PENDING]));
        $this->assertEquals('warning', $pending->getStatusTagType());

        $inviter2 = $this->createUser();
        $invitee2 = $this->createUser();
        $confirmed = InviteChain::create([
            'inviter_id' => $inviter2->id,
            'invitee_id' => $invitee2->id,
            'depth' => 1,
            'commission_rate' => 10,
            'total_commission' => 0,
            'reward_amount' => 0,
            'is_rewarded' => false,
            'status' => InviteChain::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'operation_logs' => [],
        ]);
        $this->assertEquals('success', $confirmed->getStatusTagType());

        $inviter3 = $this->createUser();
        $invitee3 = $this->createUser();
        $cancelled = InviteChain::create([
            'inviter_id' => $inviter3->id,
            'invitee_id' => $invitee3->id,
            'depth' => 1,
            'commission_rate' => 10,
            'total_commission' => 0,
            'reward_amount' => 0,
            'is_rewarded' => false,
            'status' => InviteChain::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'operation_logs' => [],
        ]);
        $this->assertEquals('info', $cancelled->getStatusTagType());

        $inviter4 = $this->createUser();
        $invitee4 = $this->createUser();
        $rewarded = InviteChain::create([
            'inviter_id' => $inviter4->id,
            'invitee_id' => $invitee4->id,
            'depth' => 1,
            'commission_rate' => 10,
            'total_commission' => 0,
            'reward_amount' => 0,
            'is_rewarded' => true,
            'rewarded_at' => now(),
            'status' => InviteChain::STATUS_REWARDED,
            'operation_logs' => [],
        ]);
        $this->assertEquals('primary', $rewarded->getStatusTagType());
    }

    public function test_cancel_updates_inviter_invite_count_when_depth_1()
    {
        $inviter = $this->createUser(null, ['total_invite_count' => 5]);
        $invitee = $this->createUser();

        $invitee->inviter_id = $inviter->id;
        $invitee->save();

        InviteChain::createInviteChain($inviter->id, $invitee->id);

        $inviter->refresh();
        $this->assertEquals(6, $inviter->total_invite_count);

        $chain = InviteChain::where('inviter_id', $inviter->id)
            ->where('invitee_id', $invitee->id)
            ->where('depth', 1)
            ->first();

        $chain->cancel();

        $inviter->refresh();
        $this->assertEquals(5, $inviter->total_invite_count);
    }
}
