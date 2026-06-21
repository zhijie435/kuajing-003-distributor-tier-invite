<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DealerLevel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['dealerLevel', 'inviter.dealerLevel']);
        if ($request->has('dealer_level_id')) {
            $query->where('dealer_level_id', $request->input('dealer_level_id'));
        }
        if ($request->has('inviter_id')) {
            $query->where('inviter_id', $request->input('inviter_id'));
        }
        if ($request->has('has_inviter')) {
            if ($request->boolean('has_inviter')) {
                $query->whereNotNull('inviter_id');
            } else {
                $query->whereNull('inviter_id');
            }
        }
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->has('keyword')) {
            $keyword = '%' . $request->input('keyword') . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', $keyword)
                  ->orWhere('nickname', 'like', $keyword)
                  ->orWhere('phone', 'like', $keyword)
                  ->orWhere('email', 'like', $keyword);
            });
        }
        if ($request->has('min_achievement')) {
            $query->where('total_achievement', '>=', $request->input('min_achievement'));
        }
        if ($request->has('min_invite_count')) {
            $query->where('total_invite_count', '>=', $request->input('min_invite_count'));
        }
        $sortField = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);
        return $this->paginated(
            $query,
            $request->input('page', 1),
            $request->input('page_size', 20)
        );
    }

    public function show($id)
    {
        $user = User::with([
            'dealerLevel',
            'inviter.dealerLevel',
            'upgradeRecords.newLevel',
            'upgradeRecords.oldLevel',
        ])->find($id);
        if (!$user) {
            return $this->error('用户不存在', 404);
        }
        $eligibleLevel = $user->findEligibleLevel();
        $canUpgrade = false;
        $upgradeReason = null;
        if ($eligibleLevel) {
            if (!$user->dealerLevel) {
                $canUpgrade = true;
            } elseif ($eligibleLevel->isHigherThan($user->dealerLevel)) {
                $canUpgrade = true;
            } else {
                $upgradeReason = '当前等级已满足最高可达成等级';
            }
        } else {
            $upgradeReason = '未达到任何等级升级条件';
        }
        return $this->success([
            'user' => $user->only([
                'id', 'username', 'nickname', 'avatar', 'email', 'phone',
                'total_achievement', 'current_month_achievement', 'total_invite_count',
                'inviter_id', 'invite_path', 'invite_depth',
                'status', 'last_login_at', 'created_at',
            ]),
            'dealer_level' => $user->dealerLevel ? [
                'id' => $user->dealerLevel->id,
                'name' => $user->dealerLevel->name,
                'code' => $user->dealerLevel->code,
                'level' => $user->dealerLevel->level,
                'icon' => $user->dealerLevel->icon,
                'commission_rate' => $user->dealerLevel->commission_rate,
            ] : null,
            'inviter' => $user->inviter ? [
                'id' => $user->inviter->id,
                'username' => $user->inviter->username,
                'nickname' => $user->inviter->nickname,
                'avatar' => $user->inviter->avatar,
                'dealer_level' => $user->inviter->dealerLevel ? [
                    'id' => $user->inviter->dealerLevel->id,
                    'name' => $user->inviter->dealerLevel->name,
                ] : null,
            ] : null,
            'upgrade_info' => [
                'can_upgrade' => $canUpgrade,
                'reason' => $upgradeReason,
                'eligible_level' => $eligibleLevel ? [
                    'id' => $eligibleLevel->id,
                    'name' => $eligibleLevel->name,
                    'level' => $eligibleLevel->level,
                    'min_achievement' => $eligibleLevel->min_achievement,
                    'min_invite_count' => $eligibleLevel->min_invite_count,
                    'reward_bonus' => $eligibleLevel->reward_bonus,
                ] : null,
                'progress' => $this->calculateUpgradeProgress($user),
            ],
            'recent_upgrades' => $user->upgradeRecords->take(5)->map(function ($record) {
                return [
                    'id' => $record->id,
                    'old_level' => $record->oldLevel ? $record->oldLevel->name : null,
                    'new_level' => $record->newLevel ? $record->newLevel->name : null,
                    'type' => $record->upgrade_type,
                    'type_label' => $record->getUpgradeTypeLabel(),
                    'reward_bonus' => $record->reward_bonus,
                    'created_at' => $record->created_at,
                ];
            }),
        ]);
    }

    private function calculateUpgradeProgress(User $user): array
    {
        $nextLevel = DealerLevel::enabled()
            ->where(function ($q) use ($user) {
                if ($user->dealerLevel) {
                    $q->where('level', '>', $user->dealerLevel->level);
                }
            })
            ->orderBy('level', 'asc')
            ->first();
        if (!$nextLevel) {
            return [
                'target_level' => null,
                'achievement_current' => $user->total_achievement,
                'achievement_target' => 0,
                'achievement_progress' => 100,
                'invite_current' => $user->total_invite_count,
                'invite_target' => 0,
                'invite_progress' => 100,
            ];
        }
        $achievementProgress = $nextLevel->min_achievement > 0
            ? min(100, round($user->total_achievement / $nextLevel->min_achievement * 100, 2))
            : 100;
        $inviteProgress = $nextLevel->min_invite_count > 0
            ? min(100, round($user->total_invite_count / $nextLevel->min_invite_count * 100, 2))
            : 100;
        return [
            'target_level' => [
                'id' => $nextLevel->id,
                'name' => $nextLevel->name,
                'level' => $nextLevel->level,
            ],
            'achievement_current' => $user->total_achievement,
            'achievement_target' => $nextLevel->min_achievement,
            'achievement_progress' => $achievementProgress,
            'invite_current' => $user->total_invite_count,
            'invite_target' => $nextLevel->min_invite_count,
            'invite_progress' => $inviteProgress,
        ];
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:users,username',
            'password' => 'required|string|min:6|max:50',
            'nickname' => 'nullable|string|max:50',
            'email' => 'nullable|string|email|max:100|unique:users,email',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'avatar' => 'nullable|string|max:255',
            'dealer_level_id' => 'nullable|integer|exists:dealer_levels,id',
            'inviter_id' => 'nullable|integer|exists:users,id',
            'invite_code' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $inviterId = $request->input('inviter_id');
        $dealerLevelId = $request->input('dealer_level_id');
        if (!$inviterId && $request->filled('invite_code')) {
            $inviteCode = \App\Models\InviteCode::where('code', strtoupper($request->input('invite_code')))->first();
            if ($inviteCode && $inviteCode->canUse()) {
                $inviterId = $inviteCode->owner_id;
                if (!$dealerLevelId && $inviteCode->target_dealer_level_id) {
                    $dealerLevelId = $inviteCode->target_dealer_level_id;
                }
            }
        }
        if (!$dealerLevelId) {
            $lowestLevel = DealerLevel::findLowestLevel();
            $dealerLevelId = $lowestLevel ? $lowestLevel->id : null;
        }
        $user = User::create([
            'username' => $request->input('username'),
            'password' => $request->input('password'),
            'nickname' => $request->input('nickname', $request->input('username')),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'avatar' => $request->input('avatar'),
            'dealer_level_id' => $dealerLevelId,
            'inviter_id' => $inviterId,
            'status' => 1,
        ]);
        if ($inviterId) {
            \App\Models\InviteChain::createInviteChain($inviterId, $user->id);
        }
        $user->invite_path = $user->buildInvitePath();
        $user->invite_depth = $user->calculateInviteDepth();
        $user->save();
        return $this->success(
            $user->load(['dealerLevel', 'inviter']),
            '用户创建成功',
            201
        );
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->error('用户不存在', 404);
        }
        $validator = Validator::make($request->all(), [
            'nickname' => 'nullable|string|max:50',
            'email' => 'nullable|string|email|max:100|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20|unique:users,phone,' . $id,
            'avatar' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $user->update($request->only(['nickname', 'email', 'phone', 'avatar', 'status']));
        return $this->success($user->fresh()->load(['dealerLevel']), '用户更新成功');
    }

    public function addAchievement(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->error('用户不存在', 404);
        }
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|gt:0',
            'remark' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $amount = $request->input('amount');
        $user->incrementAchievement($amount);
        return $this->success([
            'user_id' => $user->id,
            'added_amount' => $amount,
            'total_achievement' => $user->fresh()->total_achievement,
            'current_month_achievement' => $user->fresh()->current_month_achievement,
        ], '业绩添加成功');
    }

    public function getInvitees(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->error('用户不存在', 404);
        }
        $query = User::with('dealerLevel')->where('inviter_id', $id);
        if ($request->has('dealer_level_id')) {
            $query->where('dealer_level_id', $request->input('dealer_level_id'));
        }
        if ($request->has('keyword')) {
            $keyword = '%' . $request->input('keyword') . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', $keyword)
                  ->orWhere('nickname', 'like', $keyword);
            });
        }
        $sortField = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);
        return $this->paginated(
            $query,
            $request->input('page', 1),
            $request->input('page_size', 20)
        );
    }

    public function stats()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::active()->count(),
            'users_with_dealer_level' => User::whereNotNull('dealer_level_id')->count(),
            'users_with_inviter' => User::whereNotNull('inviter_id')->count(),
            'users_without_inviter' => User::whereNull('inviter_id')->count(),
            'total_achievement' => (float)User::sum('total_achievement'),
            'total_invite_count' => (int)User::sum('total_invite_count'),
            'dealer_level_distribution' => DealerLevel::enabled()
                ->ordered()
                ->withCount('users')
                ->get()
                ->map(function ($level) {
                    return [
                        'level_id' => $level->id,
                        'level_name' => $level->name,
                        'level_weight' => $level->level,
                        'user_count' => $level->users_count,
                    ];
                }),
        ];
        return $this->success($stats);
    }
}
