<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class User extends BaseModel
{
    protected $table = 'users';

    protected $fillable = [
        'username',
        'email',
        'phone',
        'password',
        'nickname',
        'avatar',
        'dealer_level_id',
        'total_achievement',
        'current_month_achievement',
        'total_invite_count',
        'inviter_id',
        'invite_path',
        'invite_depth',
        'api_token',
        'status',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'total_achievement' => 'decimal:2',
        'current_month_achievement' => 'decimal:2',
        'status' => 'integer',
        'last_login_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function dealerLevel(): BelongsTo
    {
        return $this->belongsTo(DealerLevel::class, 'dealer_level_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'inviter_id');
    }

    public function invitees(): HasMany
    {
        return $this->hasMany(self::class, 'inviter_id');
    }

    public function inviteCodes(): HasMany
    {
        return $this->hasMany(InviteCode::class, 'owner_id');
    }

    public function createdInviteCodes(): HasMany
    {
        return $this->hasMany(InviteCode::class, 'created_by');
    }

    public function inviteChainsAsInviter(): HasMany
    {
        return $this->hasMany(InviteChain::class, 'inviter_id');
    }

    public function inviteChainsAsInvitee(): HasOne
    {
        return $this->hasOne(InviteChain::class, 'invitee_id');
    }

    public function upgradeRecords(): HasMany
    {
        return $this->hasMany(UpgradeRecord::class, 'user_id');
    }

    public function operatedUpgrades(): HasMany
    {
        return $this->hasMany(UpgradeRecord::class, 'operator_id');
    }

    public function setPasswordAttribute($value): void
    {
        if (!empty($value)) {
            $this->attributes['password'] = Hash::make($value);
        }
    }

    public function generateApiToken(): string
    {
        $token = Str::random(60);
        $this->api_token = hash('sha256', $token);
        $this->save();
        return $token;
    }

    public function isActive(): bool
    {
        return $this->status == 1;
    }

    public function getInviteAncestors(int $maxDepth = 10): array
    {
        if (empty($this->invite_path)) {
            return [];
        }
        $ids = explode('-', trim($this->invite_path, '-'));
        $ids = array_slice(array_filter($ids), 0, $maxDepth);
        return array_reverse($ids);
    }

    public function getInviteDescendantIds(int $maxDepth = 5): array
    {
        $descendants = [];
        $currentLevelIds = [$this->id];
        for ($i = 1; $i <= $maxDepth; $i++) {
            if (empty($currentLevelIds)) {
                break;
            }
            $nextLevelIds = self::whereIn('inviter_id', $currentLevelIds)
                ->pluck('id')
                ->toArray();
            $descendants = array_merge($descendants, $nextLevelIds);
            $currentLevelIds = $nextLevelIds;
        }
        return $descendants;
    }

    public function buildInvitePath(): string
    {
        if (!$this->inviter_id) {
            return (string)$this->id;
        }
        $inviter = self::find($this->inviter_id);
        if ($inviter && !empty($inviter->invite_path)) {
            return $inviter->invite_path . '-' . $this->id;
        }
        return ($inviter ? $inviter->id : '') . '-' . $this->id;
    }

    public function calculateInviteDepth(): int
    {
        if (!$this->inviter_id) {
            return 0;
        }
        $depth = 0;
        $currentId = $this->inviter_id;
        while ($currentId) {
            $depth++;
            $parent = self::find($currentId);
            $currentId = $parent ? $parent->inviter_id : null;
        }
        return $depth;
    }

    public function incrementAchievement(float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }
        $this->total_achievement += $amount;
        $this->current_month_achievement += $amount;
        return $this->save();
    }

    public function incrementInviteCount(int $count = 1): bool
    {
        if ($count <= 0) {
            return false;
        }
        $this->total_invite_count += $count;
        return $this->save();
    }

    public function canUpgradeToLevel(DealerLevel $level): bool
    {
        if (!$this->dealerLevel) {
            return $level->meetsRequirements($this->total_achievement, $this->total_invite_count);
        }
        return $level->isHigherThan($this->dealerLevel) &&
               $level->meetsRequirements($this->total_achievement, $this->total_invite_count);
    }

    public function findEligibleLevel(): ?DealerLevel
    {
        return DealerLevel::findNextLevel($this->total_achievement, $this->total_invite_count);
    }

    public function buildInviteTree(int $maxDepth = 3): array
    {
        $tree = [
            'user' => $this->toArray(),
            'level' => 0,
            'children' => [],
        ];
        $this->loadInviteChildren($tree, 1, $maxDepth);
        return $tree;
    }

    private function loadInviteChildren(array &$node, int $currentDepth, int $maxDepth): void
    {
        if ($currentDepth > $maxDepth) {
            return;
        }
        $userId = $node['user']['id'];
        $children = self::with('dealerLevel')
            ->where('inviter_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
        foreach ($children as $child) {
            $childNode = [
                'user' => $child->toArray(),
                'level' => $currentDepth,
                'children' => [],
            ];
            $node['children'][] = $childNode;
            $this->loadInviteChildren($childNode, $currentDepth + 1, $maxDepth);
        }
    }
}
