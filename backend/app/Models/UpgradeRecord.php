<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UpgradeRecord extends BaseModel
{
    protected $table = 'upgrade_records';

    const STATUS_PENDING = 1;
    const STATUS_APPROVED = 2;
    const STATUS_REJECTED = 3;
    const STATUS_REWARDED = 4;

    const TYPE_AUTO = 1;
    const TYPE_MANUAL = 2;
    const TYPE_INVITE_CODE = 3;
    const TYPE_ADMIN = 4;

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
        'status',
        'operation_logs',
        'reviewed_at',
        'reviewer_id',
    ];

    protected $casts = [
        'upgrade_type' => 'integer',
        'achievement_at_upgrade' => 'decimal:2',
        'invite_count_at_upgrade' => 'integer',
        'reward_bonus' => 'decimal:2',
        'is_rewarded' => 'boolean',
        'rewarded_at' => 'datetime:Y-m-d H:i:s',
        'status' => 'integer',
        'operation_logs' => 'array',
        'reviewed_at' => 'datetime:Y-m-d H:i:s',
    ];

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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
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
            $isAutoOrCode = in_array($type, [self::TYPE_AUTO, self::TYPE_INVITE_CODE]);
            $initialStatus = $isAutoOrCode
                ? ($bonus <= 0 ? self::STATUS_REWARDED : self::STATUS_APPROVED)
                : self::STATUS_PENDING;
            $initialLogs = [[
                'action' => 'create',
                'action_label' => '创建升级记录',
                'operator_id' => $operatorId,
                'operator_name' => $operatorId
                    ? (User::find($operatorId)?->nickname ?: User::find($operatorId)?->username ?: '系统')
                    : ($type == self::TYPE_AUTO ? '系统自动' : ($type == self::TYPE_INVITE_CODE ? '邀请码系统' : '系统')),
                'remark' => $remark ?: match ($type) {
                    self::TYPE_AUTO => '系统自动检测升级',
                    self::TYPE_MANUAL => '手动申请升级',
                    self::TYPE_INVITE_CODE => '邀请码升级',
                    self::TYPE_ADMIN => '后台管理员调整',
                    default => '等级变更',
                },
                'old_status' => $initialStatus,
                'new_status' => $initialStatus,
                'created_at' => now()->toDateTimeString(),
            ]];
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
                'status' => $initialStatus,
                'reviewed_at' => $isAutoOrCode ? now() : null,
                'reviewer_id' => $isAutoOrCode ? null : null,
                'operation_logs' => $initialLogs,
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

    public function addOperationLog(string $action, string $actionLabel, ?int $operatorId = null, ?string $remark = null, ?int $oldStatus = null, ?int $newStatus = null): void
    {
        $logs = is_array($this->operation_logs) ? $this->operation_logs : [];
        $operator = $operatorId ? User::find($operatorId) : null;
        $logs[] = [
            'action' => $action,
            'action_label' => $actionLabel,
            'operator_id' => $operatorId,
            'operator_name' => $operator ? ($operator->nickname ?: $operator->username) : '系统',
            'remark' => $remark,
            'old_status' => $oldStatus ?? $this->getOriginal('status'),
            'new_status' => $newStatus ?? $this->status,
            'created_at' => now()->toDateTimeString(),
        ];
        $this->operation_logs = $logs;
    }

    public function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_PENDING => '待审核',
            self::STATUS_APPROVED => '审核通过',
            self::STATUS_REJECTED => '审核拒绝',
            self::STATUS_REWARDED => '已发奖',
        ];
        return $labels[$this->status] ?? '未知';
    }

    public function getStatusTagType(): string
    {
        $types = [
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_REWARDED => 'primary',
        ];
        return $types[$this->status] ?? '';
    }

    public function isPending(): bool
    {
        return $this->status == self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status == self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status == self::STATUS_REJECTED;
    }

    public function isRewardedStatus(): bool
    {
        return $this->status == self::STATUS_REWARDED;
    }

    public function canApprove(): bool
    {
        return $this->status == self::STATUS_PENDING;
    }

    public function canReject(): bool
    {
        return $this->status == self::STATUS_PENDING;
    }

    public function canReward(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED])
            && $this->reward_bonus > 0 && !$this->is_rewarded;
    }

    public function approve(?int $reviewerId = null, ?string $remark = null): bool
    {
        if (!$this->canApprove()) {
            return false;
        }
        return DB::transaction(function () use ($reviewerId, $remark) {
            $oldStatus = $this->status;
            $this->status = self::STATUS_APPROVED;
            $this->reviewed_at = now();
            $this->reviewer_id = $reviewerId;
            $this->addOperationLog('approve', '审核通过', $reviewerId, $remark ?? '审核通过，升级有效', $oldStatus, self::STATUS_APPROVED);
            $result = $this->save();
            if ($result && $this->reward_bonus <= 0) {
                $prevStatus = $this->status;
                $this->is_rewarded = true;
                $this->rewarded_at = now();
                $this->status = self::STATUS_REWARDED;
                $this->addOperationLog('reward', '发放升级奖励', $reviewerId, '无奖励金额，自动标记已发奖', $prevStatus, self::STATUS_REWARDED);
                $result = $this->save();
            }
            return $result;
        });
    }

    public function reject(?int $reviewerId = null, ?string $remark = null): bool
    {
        if (!$this->canReject()) {
            return false;
        }
        return DB::transaction(function () use ($reviewerId, $remark) {
            $oldStatus = $this->status;
            $this->status = self::STATUS_REJECTED;
            $this->reviewed_at = now();
            $this->reviewer_id = $reviewerId;
            $this->addOperationLog('reject', '审核拒绝', $reviewerId, $remark ?? '审核不通过', $oldStatus, self::STATUS_REJECTED);
            $result = $this->save();
            if ($result && $this->old_level_id) {
                $user = User::find($this->user_id);
                if ($user) {
                    $user->dealer_level_id = $this->old_level_id;
                    $user->save();
                }
            }
            return $result;
        });
    }

    public function markRewarded(?int $operatorId = null, ?string $remark = null): bool
    {
        if ($this->is_rewarded || $this->status == self::STATUS_REWARDED) {
            return false;
        }
        if ($this->status == self::STATUS_REJECTED) {
            return false;
        }
        if ($this->status == self::STATUS_PENDING) {
            $approved = $this->approve($operatorId, '发奖前自动审核通过');
            if (!$approved) {
                return false;
            }
            if ($this->is_rewarded || $this->status == self::STATUS_REWARDED) {
                return true;
            }
        }
        $oldStatus = $this->status;
        $this->is_rewarded = true;
        $this->rewarded_at = now();
        $this->status = self::STATUS_REWARDED;
        $this->addOperationLog('reward', '发放升级奖励', $operatorId, $remark ?? ('发放升级奖励 ' . $this->reward_bonus . ' 元'), $oldStatus, self::STATUS_REWARDED);
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

    public function scopeByStatus($query, $status)
    {
        if (is_array($status)) {
            return $query->whereIn('status', $status);
        }
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeNotRejected($query)
    {
        return $query->where('status', '!=', self::STATUS_REJECTED);
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
            DB::raw("SUM(CASE WHEN upgrade_type = " . self::TYPE_ADMIN . " THEN 1 ELSE 0 END) as admin_count"),
            DB::raw("SUM(CASE WHEN status = " . self::STATUS_PENDING . " THEN 1 ELSE 0 END) as review_pending_count"),
            DB::raw("SUM(CASE WHEN status = " . self::STATUS_APPROVED . " THEN 1 ELSE 0 END) as approved_count"),
            DB::raw("SUM(CASE WHEN status = " . self::STATUS_REJECTED . " THEN 1 ELSE 0 END) as rejected_count"),
            DB::raw("SUM(CASE WHEN status = " . self::STATUS_REWARDED . " THEN 1 ELSE 0 END) as reward_done_count")
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
            'review_pending_count' => (int)$stats['review_pending_count'],
            'approved_count' => (int)$stats['approved_count'],
            'rejected_count' => (int)$stats['rejected_count'],
            'reward_done_count' => (int)$stats['reward_done_count'],
        ];
    }
}
