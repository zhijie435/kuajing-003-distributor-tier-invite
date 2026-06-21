<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class InviteChain extends BaseModel
{
    protected $table = 'invite_chains';

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
    ];

    protected $casts = [
        'depth' => 'integer',
        'commission_rate' => 'decimal:2',
        'total_commission' => 'decimal:2',
        'reward_amount' => 'decimal:2',
        'is_rewarded' => 'boolean',
        'rewarded_at' => 'datetime:Y-m-d H:i:s',
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
            $level
        ) {
            $chain = self::create([
                'inviter_id' => $inviterId,
                'invitee_id' => $inviteeId,
                'invite_code_id' => $inviteCodeId,
                'depth' => 1,
                'commission_rate' => $commissionRate ?? ($level ? $level->commission_rate : 0),
                'total_commission' => 0,
                'reward_amount' => $rewardAmount ?? 0,
                'is_rewarded' => false,
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
                'remark' => "深度{$depth}间接邀请",
            ]);
            $depth++;
            if ($depth > 10) {
                break;
            }
        }
    }

    public function markRewarded(): bool
    {
        $this->is_rewarded = true;
        $this->rewarded_at = now();
        return $this->save();
    }

    public function addCommission(float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }
        $this->total_commission += $amount;
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
            DB::raw("SUM(CASE WHEN is_rewarded = 1 THEN 1 ELSE 0 END) as rewarded_count")
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
            'depth_stats' => $depthStats,
        ];
    }
}
