<?php

namespace Tests\Traits;

use App\Models\DealerLevel;
use App\Models\User;

trait CreatesTestData
{
    protected function createDealerLevels(): array
    {
        $levels = [];
        $levelConfigs = [
            ['name' => '普通经销商', 'code' => 'NORMAL', 'level' => 1, 'min_achievement' => 0, 'min_invite_count' => 0, 'commission_rate' => 5, 'reward_bonus' => 0],
            ['name' => '银牌经销商', 'code' => 'SILVER', 'level' => 2, 'min_achievement' => 1000, 'min_invite_count' => 3, 'commission_rate' => 10, 'reward_bonus' => 100],
            ['name' => '金牌经销商', 'code' => 'GOLD', 'level' => 3, 'min_achievement' => 5000, 'min_invite_count' => 10, 'commission_rate' => 15, 'reward_bonus' => 500],
            ['name' => '钻石经销商', 'code' => 'DIAMOND', 'level' => 4, 'min_achievement' => 20000, 'min_invite_count' => 30, 'commission_rate' => 20, 'reward_bonus' => 2000],
            ['name' => '皇冠经销商', 'code' => 'CROWN', 'level' => 5, 'min_achievement' => 100000, 'min_invite_count' => 100, 'commission_rate' => 30, 'reward_bonus' => 10000],
        ];

        foreach ($levelConfigs as $config) {
            $levels[] = DealerLevel::create($config);
        }

        return $levels;
    }

    protected function createUser(?int $dealerLevelId = null, array $overrides = []): User
    {
        static $userCounter = 0;
        $userCounter++;

        return User::create(array_merge([
            'username' => 'testuser_' . $userCounter . '_' . time(),
            'email' => 'test' . $userCounter . '_' . time() . '@example.com',
            'phone' => '138' . str_pad((string)$userCounter, 8, '0', STR_PAD_LEFT),
            'password' => 'password123',
            'nickname' => '测试用户' . $userCounter,
            'dealer_level_id' => $dealerLevelId,
            'total_achievement' => 0,
            'total_invite_count' => 0,
            'status' => 1,
        ], $overrides));
    }

    protected function createAdminUser(): User
    {
        return $this->createUser(null, ['username' => 'admin_' . time()]);
    }
}
