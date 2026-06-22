<?php

namespace Tests\Unit;

use App\Models\DealerLevel;
use App\Models\InviteCode;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class InviteCodeStatusTest extends TestCase
{
    use CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDealerLevels();
    }

    public function test_create_invite_code_should_be_active_status()
    {
        $owner = $this->createUser();

        $inviteCode = InviteCode::createForUser($owner->id);

        $this->assertEquals(InviteCode::STATUS_ACTIVE, $inviteCode->status);
        $this->assertEquals('正常', $inviteCode->getStatusLabel());
        $this->assertNotNull($inviteCode->activated_at);
        $this->assertEquals(0, $inviteCode->used_count);
        $this->assertTrue($inviteCode->canUse());
    }

    public function test_invite_code_disable_and_enable_status_flow()
    {
        $owner = $this->createUser();
        $inviteCode = InviteCode::createForUser($owner->id);

        $result = $inviteCode->disable();

        $this->assertTrue($result);
        $this->assertEquals(InviteCode::STATUS_DISABLED, $inviteCode->status);
        $this->assertEquals('已禁用', $inviteCode->getStatusLabel());
        $this->assertFalse($inviteCode->canUse());

        $result = $inviteCode->enable();

        $this->assertTrue($result);
        $this->assertEquals(InviteCode::STATUS_ACTIVE, $inviteCode->status);
        $this->assertTrue($inviteCode->canUse());
    }

    public function test_used_up_invite_code_can_not_be_enabled()
    {
        $owner = $this->createUser();
        $inviteCode = InviteCode::createForUser($owner->id, 2);

        $inviteCode->markUsed();
        $inviteCode->markUsed();

        $this->assertEquals(InviteCode::STATUS_USED_UP, $inviteCode->status);
        $this->assertEquals('已用完', $inviteCode->getStatusLabel());
        $this->assertTrue($inviteCode->isUsedUp());
        $this->assertFalse($inviteCode->canUse());

        $inviteCode->status = InviteCode::STATUS_DISABLED;
        $inviteCode->save();

        $result = $inviteCode->enable();

        $this->assertFalse($result);
        $this->assertEquals(InviteCode::STATUS_DISABLED, $inviteCode->status);
    }

    public function test_expired_invite_code_can_not_be_enabled()
    {
        $owner = $this->createUser();
        $inviteCode = InviteCode::createForUser(
            $owner->id,
            10,
            null,
            0,
            0,
            now()->subDay()
        );

        $this->assertTrue($inviteCode->isExpired());

        $inviteCode->status = InviteCode::STATUS_DISABLED;
        $inviteCode->save();

        $result = $inviteCode->enable();

        $this->assertFalse($result);
        $this->assertEquals(InviteCode::STATUS_DISABLED, $inviteCode->status);
    }

    public function test_mark_used_single_use_updates_status()
    {
        $owner = $this->createUser();
        $inviteCode = InviteCode::createForUser($owner->id, 1);

        $result = $inviteCode->markUsed();

        $this->assertTrue($result);
        $this->assertEquals(1, $inviteCode->used_count);
        $this->assertEquals(InviteCode::STATUS_USED_UP, $inviteCode->status);
        $this->assertEquals(0, $inviteCode->remainingUses());
    }

    public function test_mark_used_multiple_uses_only_status_up_on_limit()
    {
        $owner = $this->createUser();
        $inviteCode = InviteCode::createForUser($owner->id, 3);

        $inviteCode->markUsed();
        $this->assertEquals(InviteCode::STATUS_ACTIVE, $inviteCode->status);
        $this->assertEquals(1, $inviteCode->used_count);
        $this->assertEquals(2, $inviteCode->remainingUses());

        $inviteCode->markUsed();
        $this->assertEquals(InviteCode::STATUS_ACTIVE, $inviteCode->status);
        $this->assertEquals(2, $inviteCode->used_count);
        $this->assertEquals(1, $inviteCode->remainingUses());

        $inviteCode->markUsed();
        $this->assertEquals(InviteCode::STATUS_USED_UP, $inviteCode->status);
        $this->assertEquals(3, $inviteCode->used_count);
        $this->assertEquals(0, $inviteCode->remainingUses());
    }

    public function test_check_and_update_status_for_expired()
    {
        $owner = $this->createUser();
        $inviteCode = InviteCode::createForUser(
            $owner->id,
            10,
            null,
            0,
            0,
            now()->subDay()
        );

        $this->assertEquals(InviteCode::STATUS_ACTIVE, $inviteCode->status);

        $inviteCode->checkAndUpdateStatus();

        $this->assertEquals(InviteCode::STATUS_EXPIRED, $inviteCode->status);
        $this->assertEquals('已过期', $inviteCode->getStatusLabel());
        $this->assertFalse($inviteCode->canUse());
    }

    public function test_check_and_update_status_for_used_up()
    {
        $owner = $this->createUser();
        $inviteCode = InviteCode::createForUser($owner->id, 2);
        $inviteCode->used_count = 2;
        $inviteCode->save();

        $inviteCode->status = InviteCode::STATUS_ACTIVE;
        $inviteCode->save();

        $inviteCode->checkAndUpdateStatus();

        $this->assertEquals(InviteCode::STATUS_USED_UP, $inviteCode->status);
    }

    public function test_full_status_lifecycle_active_disabled_active_used_up()
    {
        $owner = $this->createUser();
        $inviteCode = InviteCode::createForUser($owner->id, 2);

        $this->assertEquals(InviteCode::STATUS_ACTIVE, $inviteCode->status);
        $this->assertTrue($inviteCode->canUse());

        $inviteCode->disable();
        $this->assertEquals(InviteCode::STATUS_DISABLED, $inviteCode->status);
        $this->assertFalse($inviteCode->canUse());

        $inviteCode->enable();
        $this->assertEquals(InviteCode::STATUS_ACTIVE, $inviteCode->status);
        $this->assertTrue($inviteCode->canUse());

        $inviteCode->markUsed();
        $this->assertEquals(InviteCode::STATUS_ACTIVE, $inviteCode->status);

        $inviteCode->markUsed();
        $this->assertEquals(InviteCode::STATUS_USED_UP, $inviteCode->status);
        $this->assertFalse($inviteCode->canUse());
    }

    public function test_create_invite_code_with_target_dealer_level()
    {
        $owner = $this->createUser();
        $levels = DealerLevel::all();
        $targetLevel = $levels[2];

        $inviteCode = InviteCode::createForUser(
            $owner->id,
            1,
            $targetLevel->id,
            100.00,
            50.00
        );

        $this->assertEquals($targetLevel->id, $inviteCode->target_dealer_level_id);
        $this->assertEquals(100.00, $inviteCode->reward_amount);
        $this->assertEquals(50.00, $inviteCode->new_user_bonus);
        $this->assertEquals(InviteCode::STATUS_ACTIVE, $inviteCode->status);
    }

    public function test_invite_code_status_check_used_up_has_priority_over_expired()
    {
        $owner = $this->createUser();
        $inviteCode = InviteCode::createForUser(
            $owner->id,
            2,
            null,
            0,
            0,
            now()->subDay()
        );
        $inviteCode->used_count = 2;
        $inviteCode->status = InviteCode::STATUS_ACTIVE;
        $inviteCode->save();

        $inviteCode->checkAndUpdateStatus();

        $this->assertEquals(InviteCode::STATUS_USED_UP, $inviteCode->status);
    }

    public function test_disabled_status_is_not_affected_by_check_and_update()
    {
        $owner = $this->createUser();
        $inviteCode = InviteCode::createForUser($owner->id, 1);
        $inviteCode->disable();

        $inviteCode->checkAndUpdateStatus();

        $this->assertEquals(InviteCode::STATUS_DISABLED, $inviteCode->status);
    }
}
