<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class InviteChain extends BaseModel
{
    protected $table = 'invite_chains';

    const STATUS_PENDING = 1;
    const STATUS_CONFIRMED = 2;
    const STATUS_CANCELLED = 3;
    const STATUS_REWARDED = 4;

    protected $fillable = [
        'inviter_id',
        'invitee_id',
        'invite_code_id',
        'depth',
        'commission_rate',
        'total_commission',
        'reward_amount',
        'is_rewarded',
        'rewarded_at',
        'remark',
        'status',
        'operator_id',
        'operation_logs',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'depth' => 'integer',
        'commission_rate' => 'decimal:2',
        'total_commission' => 'decimal:2',
        'reward_amount' => 'decimal:2',
        'is_rewarded' => 'boolean',
        'rewarded_at' => 'datetime:Y-m-d H:i:s',
        'status' => 'integer',
        'operation_logs' => 'array',
        'confirmed_at' => 'datetime:Y-m-d H:i:s',
        'cancelled_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }

    public function inviteCode(): BelongsTo
    {
        return $this->belongsTo(InviteCode::class, 'invite_code_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public static function createInviteChain(
        int $inviterId,
        int $inviteeId,
        ?int $inviteCodeId = null,
        ?float $rewardAmount = null,
        ?float $commissionRate = null
    ): self {
        $inviter = User::find($inviterId);
        $level = $inviter && $inviter->dealerLevel ? $inviter->dealerLevel : null;
        return DB::transaction(function () use (
            $inviterId,
            $inviteeId,
            $inviteCodeId,
            $rewardAmount,
            $commissionRate,
            $level,
            $inviter
        ) {
            $initialLogs = [[
                'action' => 'create',
                'action_label' => '创建邀请关系',
                'operator_id' => null,
                'operator_name' => '系统',
                'remark' => $inviteCodeId ? '通过邀请码建立关系' : '直接创建邀请关系',
                'old_status' => self::STATUS_CONFIRMED,
                'new_status' => self::STATUS_CONFIRMED,
                'created_at' => now()->toDateTimeString(),
            ]];
            $chain = self::create([
                'inviter_id' => $inviterId,
                'invitee_id' => $inviteeId,
                'invite_code_id' => $inviteCodeId,
                'depth' => 1,
                'commission_rate' => $commissionRate ?? ($level ? $level->commission_rate : 0),
                'total_commission' => 0,
                'reward_amount' => $rewardAmount ?? 0,
                'is_rewarded' => false,
                'status' => self::STATUS_CONFIRMED,
                'confirmed_at' => now(),
                'operation_logs' => $initialLogs,
            ]);
            self::createAncestorChains($inviterId, $inviteeId, $inviteCodeId);
            $invitee = User::find($inviteeId);
            if ($invitee) {
                $invitee->invite_path = $invitee->buildInvitePath();
                $invitee->invite_depth = $invitee->calculateInviteDepth();
                $invitee->save();
            }
            if ($inviter) {
                $inviter->incrementInviteCount();
            }
            return $chain;
        });
    }

    protected static function createAncestorChains(int $directInviterId, int $inviteeId, ?int $inviteCodeId): void
    {
        $inviter = User::find($directInviterId);
        if (!$inviter) {
            return;
        }
        $ancestorIds = $inviter->getInviteAncestors();
        $depth = 2;
        foreach ($ancestorIds as $ancestorId) {
            $ancestor = User::find($ancestorId);
            $level = $ancestor && $ancestor->dealerLevel ? $ancestor->dealerLevel : null;
            self::create([
                'inviter_id' => $ancestorId,
                'invitee_id' => $inviteeId,
                'invite_code_id' => $inviteCodeId,
                'depth' => $depth,
                'commission_rate' => $level ? max(0, $level->commission_rate * (1 - $depth * 0.2)) : 0,
                'total_commission' => 0,
                'reward_amount' => 0,
                'is_rewarded' => true,
                'status' => self::STATUS_CONFIRMED,
                'confirmed_at' => now(),
                'operation_logs' => [[
                    'action' => 'create',
                    'action_label' => '创建间接邀请关系',
                    'operator_id' => null,
                    'operator_name' => '系统',
                    'remark' => "深度{$depth}间接邀请",
                    'old_status' => self::STATUS_CONFIRMED,
                    'new_status' => self::STATUS_CONFIRMED,
                    'created_at' => now()->toDateTimeString(),
                ]],
                'remark' => "深度{$depth}间接邀请",
            ]);
            $depth++;
            if ($depth > 10) {
                break;
            }
        }
    }

    public function markRewarded(?int $operatorId = null, ?string $remark = null): bool
    {
        $oldStatus = $this->status;
        $this->is_rewarded = true;
        $this->rewarded_at = now();
        $this->status = self::STATUS_REWARDED;
        $this->operator_id = $operatorId;
        $this->addOperationLog('reward', '发放邀请奖励', $operatorId, $remark ?? '手动发放奖励', $oldStatus, self::STATUS_REWARDED);
        return $this->save();
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
            self::STATUS_PENDING => '待确认',
            self::STATUS_CONFIRMED => '已确认',
            self::STATUS_CANCELLED => '已取消',
            self::STATUS_REWARDED => '已发奖',
        ];
        return $labels[$this->status] ?? '未知';
    }

    public function getStatusTagType(): string
    {
        $types = [
            self::STATUS_PENDING => 'warning',
            self::STATUS_CONFIRMED => 'success',
            self::STATUS_CANCELLED => 'info',
            self::STATUS_REWARDED => 'primary',
        ];
        return $types[$this->status] ?? '';
    }

    public function isPending(): bool
    {
        return $this->status == self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status == self::STATUS_CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status == self::STATUS_CANCELLED;
    }

    public function isRewarded(): bool
    {
        return $this->status == self::STATUS_REWARDED;
    }

    public function canConfirm(): bool
    {
        return $this->status == self::STATUS_PENDING;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED])
            && !$this->is_rewarded;
    }

    public function confirm(?int $operatorId = null, ?string $remark = null): bool
    {
        if (!$this->canConfirm()) {
            return false;
        }
        $oldStatus = $this->status;
        $this->status = self::STATUS_CONFIRMED;
        $this->confirmed_at = now();
        $this->operator_id = $operatorId;
        $this->addOperationLog('confirm', '确认邀请关系', $operatorId, $remark ?? '手动确认邀请关系有效', $oldStatus, self::STATUS_CONFIRMED);
        return $this->save();
    }

    public function cancel(?int $operatorId = null, ?string $remark = null): bool
    {
        if (!$this->canCancel()) {
            return false;
        }
        $oldStatus = $this->status;
        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = now();
        $this->operator_id = $operatorId;
        $this->addOperationLog('cancel', '取消邀请关系', $operatorId, $remark ?? '手动取消邀请关系', $oldStatus, self::STATUS_CANCELLED);
        return DB::transaction(function () {
            $result = $this->save();
            if ($result && $this->depth == 1) {
                $invitee = User::find($this->invitee_id);
                if ($invitee && $invitee->inviter_id == $this->inviter_id) {
                    $invitee->inviter_id = null;
                    $invitee->invite_path = null;
                    $invitee->invite_depth = 0;
                    $invitee->save();
                }
                $inviter = User::find($this->inviter_id);
                if ($inviter) {
                    $inviter->total_invite_count = max(0, $inviter->total_invite_count - 1);
                    $inviter->save();
                }
            }
            return $result;
        });
    }

    public function addCommission(float $amount, ?int $operatorId = null, ?string $remark = null): bool
    {
        if ($amount <= 0) {
            return false;
        }
        $oldCommission = $this->total_commission;
        $this->total_commission += $amount;
        if ($operatorId || $remark) {
            $this->addOperationLog(
                'add_commission',
                '增加佣金',
                $operatorId,
                ($remark ?? '佣金结算') . "，增加金额：{$amount}，原累计：{$oldCommission}",
                $this->status,
                $this->status
            );
        }
        return $this->save();
    }

    public function isDirectInvite(): bool
    {
        return $this->depth == 1;
    }

    public function isIndirectInvite(): bool
    {
        return $this->depth > 1;
    }

    public function scopeDirect($query)
    {
        return $query->where('depth', 1);
    }

    public function scopeIndirect($query)
    {
        return $query->where('depth', '>', 1);
    }

    public function scopeByDepth($query, int $depth)
    {
        return $query->where('depth', $depth);
    }

    public function scopeByInviter($query, int $inviterId)
    {
        return $query->where('inviter_id', $inviterId);
    }

    public function scopeByInvitee($query, int $inviteeId)
    {
        return $query->where('invitee_id', $inviteeId);
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

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeNotCancelled($query)
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED);
    }

    public static function getDirectInviteesCount(int $inviterId): int
    {
        return self::where('inviter_id', $inviterId)->direct()->count();
    }

    public static function getTotalInviteesCount(int $inviterId): int
    {
        return self::where('inviter_id', $inviterId)->count();
    }

    public static function getInviteStats(int $inviterId): array
    {
        $stats = self::select(
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN depth = 1 THEN 1 ELSE 0 END) as direct_count"),
            DB::raw("SUM(CASE WHEN depth > 1 THEN 1 ELSE 0 END) as indirect_count"),
            DB::raw('COALESCE(SUM(total_commission), 0) as total_commission'),
            DB::raw('COALESCE(SUM(reward_amount), 0) as total_reward'),
            DB::raw("SUM(CASE WHEN is_rewarded = 1 THEN 1 ELSE 0 END) as rewarded_count"),
            DB::raw("SUM(CASE WHEN status = " . self::STATUS_PENDING . " THEN 1 ELSE 0 END) as pending_count"),
            DB::raw("SUM(CASE WHEN status = " . self::STATUS_CONFIRMED . " THEN 1 ELSE 0 END) as confirmed_count"),
            DB::raw("SUM(CASE WHEN status = " . self::STATUS_CANCELLED . " THEN 1 ELSE 0 END) as cancelled_count")
        )
        ->where('inviter_id', $inviterId)
        ->first()
        ->toArray();

        $depthStats = self::select('depth', DB::raw('COUNT(*) as count'))
            ->where('inviter_id', $inviterId)
            ->groupBy('depth')
            ->orderBy('depth')
            ->pluck('count', 'depth')
            ->toArray();

        return [
            'total' => (int)$stats['total'],
            'direct_count' => (int)$stats['direct_count'],
            'indirect_count' => (int)$stats['indirect_count'],
            'total_commission' => (float)$stats['total_commission'],
            'total_reward' => (float)$stats['total_reward'],
            'rewarded_count' => (int)$stats['rewarded_count'],
            'pending_count' => (int)$stats['pending_count'],
            'confirmed_count' => (int)$stats['confirmed_count'],
            'cancelled_count' => (int)$stats['cancelled_count'],
            'depth_stats' => $depthStats,
        ];
    }
}
