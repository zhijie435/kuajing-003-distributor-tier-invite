<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DealerLevel extends BaseModel
{
    protected $table = 'dealer_levels';

    protected $fillable = [
        'name',
        'code',
        'level',
        'icon',
        'description',
        'min_achievement',
        'min_invite_count',
        'commission_rate',
        'reward_bonus',
        'privileges',
        'is_active',
    ];

    protected $casts = [
        'privileges' => 'array',
        'is_active' => 'boolean',
        'min_achievement' => 'decimal:2',
        'min_invite_count' => 'integer',
        'commission_rate' => 'decimal:2',
        'reward_bonus' => 'decimal:2',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'dealer_level_id');
    }

    public function inviteCodes(): HasMany
    {
        return $this->hasMany(InviteCode::class, 'target_dealer_level_id');
    }

    public function upgradeRecordsTo(): HasMany
    {
        return $this->hasMany(UpgradeRecord::class, 'new_level_id');
    }

    public function upgradeRecordsFrom(): HasMany
    {
        return $this->hasMany(UpgradeRecord::class, 'old_level_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('level', 'asc');
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_active', true);
    }

    public function isHigherThan(DealerLevel $other): bool
    {
        return $this->level > $other->level;
    }

    public function isLowerThan(DealerLevel $other): bool
    {
        return $this->level < $other->level;
    }

    public function meetsRequirements(float $achievement, int $inviteCount): bool
    {
        return $achievement >= $this->min_achievement && $inviteCount >= $this->min_invite_count;
    }

    public static function findNextLevel(float $achievement, int $inviteCount): ?self
    {
        return self::enabled()
            ->where('min_achievement', '<=', $achievement)
            ->where('min_invite_count', '<=', $inviteCount)
            ->orderBy('level', 'desc')
            ->first();
    }

    public static function findLowestLevel(): ?self
    {
        return self::enabled()->ordered()->first();
    }

    public static function findHighestLevel(): ?self
    {
        return self::enabled()->orderBy('level', 'desc')->first();
    }
}
