<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UpgradeRecord extends BaseModel
{
    protected $table = 'upgrade_records';

    protected $fillable = [
        'user_id',
        'old_level_id',
        'new_level_id',
        'upgrade_type',
        'achievement_at_upgrade',
        'invite_count_at_upgrade',
        'reward_bonus',
        'is_rewarded',
        'rewarded_at',
        'operator_id',
        'invite_code_id',
        'remark',
    ];

    protected $casts = [
        'upgrade_type' => 'integer',
        'achievement_at_upgrade' => 'decimal:2',
        'invite_count_at_upgrade' => 'integer',
        'reward_bonus' => 'decimal:2',
        'is_rewarded' => 'boolean',
        'rewarded_at' => 'datetime:Y-m-d H:i:s',
    ];

    const TYPE_AUTO = 1;
    const TYPE_MANUAL = 2;
    const TYPE_INVITE_CODE = 3;
    const TYPE_ADMIN = 4;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function oldLevel(): BelongsTo
    {
        return $this->belongsTo(DealerLevel::class, 'old_level_id');
    }

    public function newLevel(): BelongsTo
    {
        return $this->belongsTo(DealerLevel::class, 'new_level_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function inviteCode(): BelongsTo
    {
        return $this->belongsTo(InviteCode::class, 'invite_code_id');
    }

    public static function recordUpgrade(
        int $userId,
        ?int $oldLevelId,
        int $newLevelId,
        int $type = self::TYPE_AUTO,
        ?float $rewardBonus = null,
        ?int $operatorId = null,
        ?int $inviteCodeId = null,
        ?string $remark = null
    ): ?self {
        if ($oldLevelId && $oldLevelId == $newLevelId) {
            return null;
        }
        return DB::transaction(function () use (
            $userId,
            $oldLevelId,
            $newLevelId,
            $type,
            $rewardBonus,
            $operatorId,
            $inviteCodeId,
            $remark
        ) {
            $user = User::find($userId);
            if (!$user) {
                return null;
            }
            $newLevel = DealerLevel::find($newLevelId);
            $bonus = $rewardBonus ?? ($newLevel ? $newLevel->reward_bonus : 0);
            $record = self::create([
                'user_id' => $userId,
                'old_level_id' => $oldLevelId,
                'new_level_id' => $newLevelId,
                'upgrade_type' => $type,
                'achievement_at_upgrade' => $user->total_achievement,
                'invite_count_at_upgrade' => $user->total_invite_count,
                'reward_bonus' => $bonus,
                'is_rewarded' => $bonus <= 0,
                'rewarded_at' => $bonus <= 0 ? now() : null,
                'operator_id' => $operatorId,
                'invite_code_id' => $inviteCodeId,
                'remark' => $remark,
            ]);
            $user->dealer_level_id = $newLevelId;
            $user->save();
            return $record;
        });
    }

    public function getUpgradeTypeLabel(): string
    {
        $labels = [
            self::TYPE_AUTO => '自动升级',
            self::TYPE_MANUAL => '手动升级',
            self::TYPE_INVITE_CODE => '邀请码升级',
            self::TYPE_ADMIN => '后台调整',
        ];
        return $labels[$this->upgrade_type] ?? '未知';
    }

    public function isUpgrade(): bool
    {
        if (!$this->oldLevel) {
            return true;
        }
        return $this->newLevel && $this->newLevel->isHigherThan($this->oldLevel);
    }

    public function isDowngrade(): bool
    {
        if (!$this->oldLevel || !$this->newLevel) {
            return false;
        }
        return $this->newLevel->isLowerThan($this->oldLevel);
    }

    public function markRewarded(): bool
    {
        $this->is_rewarded = true;
        $this->rewarded_at = now();
        return $this->save();
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, int $type)
    {
        return $query->where('upgrade_type', $type);
    }

    public function scopeUnrewarded($query)
    {
        return $query->where('is_rewarded', false);
    }

    public function scopeReward($query)
    {
        return $query->where('reward_bonus', '>', 0);
    }

    public function scopeInDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public static function getUserUpgradeHistory(int $userId, int $limit = 20)
    {
        return self::with(['oldLevel', 'newLevel', 'operator'])
            ->byUser($userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function getUpgradeStats(array $filters = []): array
    {
        $query = self::query();
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->inDateRange($filters['start_date'], $filters['end_date']);
        }
        if (!empty($filters['upgrade_type'])) {
            $query->byType($filters['upgrade_type']);
        }
        $stats = $query->select(
            DB::raw('COUNT(*) as total_upgrades'),
            DB::raw('COALESCE(SUM(reward_bonus), 0) as total_bonus'),
            DB::raw("SUM(CASE WHEN is_rewarded = 1 THEN 1 ELSE 0 END) as rewarded_count"),
            DB::raw("SUM(CASE WHEN is_rewarded = 0 AND reward_bonus > 0 THEN 1 ELSE 0 END) as pending_count"),
            DB::raw("SUM(CASE WHEN upgrade_type = " . self::TYPE_AUTO . " THEN 1 ELSE 0 END) as auto_count"),
            DB::raw("SUM(CASE WHEN upgrade_type = " . self::TYPE_MANUAL . " THEN 1 ELSE 0 END) as manual_count"),
            DB::raw("SUM(CASE WHEN upgrade_type = " . self::TYPE_INVITE_CODE . " THEN 1 ELSE 0 END) as code_count"),
            DB::raw("SUM(CASE WHEN upgrade_type = " . self::TYPE_ADMIN . " THEN 1 ELSE 0 END) as admin_count")
        )
        ->first()
        ->toArray();
        return [
            'total_upgrades' => (int)$stats['total_upgrades'],
            'total_bonus' => (float)$stats['total_bonus'],
            'rewarded_count' => (int)$stats['rewarded_count'],
            'pending_count' => (int)$stats['pending_count'],
            'auto_count' => (int)$stats['auto_count'],
            'manual_count' => (int)$stats['manual_count'],
            'code_count' => (int)$stats['code_count'],
            'admin_count' => (int)$stats['admin_count'],
        ];
    }
}
