<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class InviteCode extends BaseModel
{
    protected $table = 'invite_codes';

    protected $fillable = [
        'code',
        'owner_id',
        'target_dealer_level_id',
        'max_uses',
        'used_count',
        'reward_amount',
        'new_user_bonus',
        'expires_at',
        'activated_at',
        'status',
        'remark',
        'created_by',
    ];

    protected $casts = [
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'reward_amount' => 'decimal:2',
        'new_user_bonus' => 'decimal:2',
        'status' => 'integer',
        'expires_at' => 'datetime:Y-m-d H:i:s',
        'activated_at' => 'datetime:Y-m-d H:i:s',
    ];

    const STATUS_DISABLED = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_USED_UP = 2;
    const STATUS_EXPIRED = 3;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function targetDealerLevel(): BelongsTo
    {
        return $this->belongsTo(DealerLevel::class, 'target_dealer_level_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inviteChains(): HasMany
    {
        return $this->hasMany(InviteChain::class, 'invite_code_id');
    }

    public function upgradeRecords(): HasMany
    {
        return $this->hasMany(UpgradeRecord::class, 'invite_code_id');
    }

    public static function generateUniqueCode(int $length = 8): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[mt_rand(0, strlen($characters) - 1)];
            }
        } while (self::withTrashed()->where('code', $code)->exists());
        return $code;
    }

    public static function createForUser(
        int $ownerId,
        int $maxUses = 1,
        ?int $targetLevelId = null,
        float $rewardAmount = 0,
        float $newUserBonus = 0,
        ?string $expireAt = null,
        ?int $createdBy = null,
        ?string $remark = null
    ): self {
        $expireDays = config('app.invite_code.default_expire_days', 365);
        $expiresAt = $expireAt ?? now()->addDays($expireDays);
        $code = self::generateUniqueCode(config('app.invite_code.length', 8));
        return self::create([
            'code' => $code,
            'owner_id' => $ownerId,
            'target_dealer_level_id' => $targetLevelId,
            'max_uses' => $maxUses,
            'used_count' => 0,
            'reward_amount' => $rewardAmount,
            'new_user_bonus' => $newUserBonus,
            'expires_at' => $expiresAt,
            'activated_at' => now(),
            'status' => self::STATUS_ACTIVE,
            'remark' => $remark,
            'created_by' => $createdBy ?? $ownerId,
        ]);
    }

    public function canUse(): bool
    {
        if ($this->status != self::STATUS_ACTIVE) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        return $this->used_count < $this->max_uses;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isUsedUp(): bool
    {
        return $this->used_count >= $this->max_uses;
    }

    public function markUsed(): bool
    {
        $this->used_count++;
        if ($this->used_count >= $this->max_uses) {
            $this->status = self::STATUS_USED_UP;
        }
        return $this->save();
    }

    public function checkAndUpdateStatus(): void
    {
        if ($this->status == self::STATUS_ACTIVE) {
            if ($this->isUsedUp()) {
                $this->status = self::STATUS_USED_UP;
                $this->save();
            } elseif ($this->isExpired()) {
                $this->status = self::STATUS_EXPIRED;
                $this->save();
            }
        }
    }

    public function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_DISABLED => '已禁用',
            self::STATUS_ACTIVE => '正常',
            self::STATUS_USED_UP => '已用完',
            self::STATUS_EXPIRED => '已过期',
        ];
        return $labels[$this->status] ?? '未知';
    }

    public function remainingUses(): int
    {
        return max(0, $this->max_uses - $this->used_count);
    }

    public function disable(): bool
    {
        $this->status = self::STATUS_DISABLED;
        return $this->save();
    }

    public function enable(): bool
    {
        if ($this->isExpired() || $this->isUsedUp()) {
            return false;
        }
        $this->status = self::STATUS_ACTIVE;
        return $this->save();
    }
}
