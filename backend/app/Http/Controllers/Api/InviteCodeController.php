<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InviteCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InviteCodeController extends Controller
{
    public function index(Request $request)
    {
        $query = InviteCode::with(['owner', 'targetDealerLevel', 'createdBy']);
        if ($request->has('owner_id')) {
            $query->where('owner_id', $request->input('owner_id'));
        }
        if ($request->has('created_by')) {
            $query->where('created_by', $request->input('created_by'));
        }
        if ($request->has('target_dealer_level_id')) {
            $query->where('target_dealer_level_id', $request->input('target_dealer_level_id'));
        }
        if ($request->has('status')) {
            $status = $request->input('status');
            if (is_array($status)) {
                $query->whereIn('status', $status);
            } else {
                $query->where('status', $status);
            }
        }
        if ($request->has('code')) {
            $query->where('code', 'like', '%' . $request->input('code') . '%');
        }
        if ($request->has('can_use')) {
            $query->where('status', InviteCode::STATUS_ACTIVE)
                  ->where('used_count', '<', DB::raw('max_uses'))
                  ->where(function ($q) {
                      $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
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

    public function show($id)
    {
        $code = InviteCode::with(['owner', 'targetDealerLevel', 'createdBy', 'inviteChains.invitee'])->find($id);
        if (!$code) {
            return $this->error('邀请码不存在', 404);
        }
        return $this->success($code);
    }

    public function findByCode($code)
    {
        $inviteCode = InviteCode::with(['owner', 'targetDealerLevel'])
            ->where('code', strtoupper($code))
            ->first();
        if (!$inviteCode) {
            return $this->error('邀请码不存在', 404);
        }
        $inviteCode->checkAndUpdateStatus();
        return $this->success([
            'code' => $inviteCode->code,
            'owner_name' => $inviteCode->owner ? $inviteCode->owner->nickname ?? $inviteCode->owner->username : null,
            'owner_avatar' => $inviteCode->owner ? $inviteCode->owner->avatar : null,
            'target_level' => $inviteCode->targetDealerLevel ? [
                'id' => $inviteCode->targetDealerLevel->id,
                'name' => $inviteCode->targetDealerLevel->name,
            ] : null,
            'reward_amount' => $inviteCode->reward_amount,
            'new_user_bonus' => $inviteCode->new_user_bonus,
            'expires_at' => $inviteCode->expires_at,
            'max_uses' => $inviteCode->max_uses,
            'remaining_uses' => $inviteCode->remainingUses(),
            'can_use' => $inviteCode->canUse(),
            'status' => $inviteCode->status,
            'status_label' => $inviteCode->getStatusLabel(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'owner_id' => 'required|integer|exists:users,id',
            'target_dealer_level_id' => 'nullable|integer|exists:dealer_levels,id',
            'max_uses' => 'nullable|integer|min:1|max:10000',
            'reward_amount' => 'nullable|numeric|min:0',
            'new_user_bonus' => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date|after:now',
            'expire_days' => 'nullable|integer|min:1|max:3650',
            'remark' => 'nullable|string|max:500',
            'created_by' => 'nullable|integer|exists:users,id',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $owner = User::find($request->input('owner_id'));
        if (!$owner) {
            return $this->error('邀请码所有者不存在');
        }
        $expiresAt = null;
        if ($request->filled('expires_at')) {
            $expiresAt = $request->input('expires_at');
        } elseif ($request->filled('expire_days')) {
            $expiresAt = now()->addDays($request->input('expire_days'));
        }
        $inviteCode = InviteCode::createForUser(
            $request->input('owner_id'),
            $request->input('max_uses', 1),
            $request->input('target_dealer_level_id'),
            $request->input('reward_amount', 0),
            $request->input('new_user_bonus', 0),
            $expiresAt,
            $request->input('created_by', $request->input('owner_id')),
            $request->input('remark')
        );
        return $this->success($inviteCode->load(['owner', 'targetDealerLevel']), '邀请码创建成功', 201);
    }

    public function batchCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'owner_id' => 'required|integer|exists:users,id',
            'count' => 'required|integer|min:1|max:100',
            'target_dealer_level_id' => 'nullable|integer|exists:dealer_levels,id',
            'max_uses' => 'nullable|integer|min:1|max:10000',
            'reward_amount' => 'nullable|numeric|min:0',
            'new_user_bonus' => 'nullable|numeric|min:0',
            'expire_days' => 'nullable|integer|min:1|max:3650',
            'remark' => 'nullable|string|max:500',
            'created_by' => 'nullable|integer|exists:users,id',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $count = $request->input('count');
        $expireDays = $request->input('expire_days', 365);
        $expiresAt = now()->addDays($expireDays);
        $codes = [];
        DB::beginTransaction();
        try {
            for ($i = 0; $i < $count; $i++) {
                $code = InviteCode::createForUser(
                    $request->input('owner_id'),
                    $request->input('max_uses', 1),
                    $request->input('target_dealer_level_id'),
                    $request->input('reward_amount', 0),
                    $request->input('new_user_bonus', 0),
                    $expiresAt,
                    $request->input('created_by', $request->input('owner_id')),
                    $request->input('remark')
                );
                $codes[] = $code;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('批量创建失败：' . $e->getMessage(), 500);
        }
        return $this->success([
            'count' => count($codes),
            'codes' => collect($codes)->pluck('code')->toArray(),
        ], "成功创建{$count}个邀请码", 201);
    }

    public function update(Request $request, $id)
    {
        $inviteCode = InviteCode::find($id);
        if (!$inviteCode) {
            return $this->error('邀请码不存在', 404);
        }
        $validator = Validator::make($request->all(), [
            'target_dealer_level_id' => 'nullable|integer|exists:dealer_levels,id',
            'max_uses' => 'nullable|integer|min:' . $inviteCode->used_count,
            'reward_amount' => 'nullable|numeric|min:0',
            'new_user_bonus' => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date',
            'remark' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $inviteCode->update($request->only([
            'target_dealer_level_id', 'max_uses', 'reward_amount',
            'new_user_bonus', 'expires_at', 'remark',
        ]));
        $inviteCode->checkAndUpdateStatus();
        return $this->success($inviteCode, '邀请码更新成功');
    }

    public function destroy($id)
    {
        $inviteCode = InviteCode::find($id);
        if (!$inviteCode) {
            return $this->error('邀请码不存在', 404);
        }
        if ($inviteCode->used_count > 0) {
            return $this->error('该邀请码已被使用，无法删除');
        }
        $inviteCode->delete();
        return $this->success(null, '邀请码删除成功');
    }

    public function toggleStatus($id)
    {
        $inviteCode = InviteCode::find($id);
        if (!$inviteCode) {
            return $this->error('邀请码不存在', 404);
        }
        if ($inviteCode->status == InviteCode::STATUS_ACTIVE) {
            $inviteCode->disable();
            $message = '邀请码已禁用';
        } elseif ($inviteCode->status == InviteCode::STATUS_DISABLED) {
            if (!$inviteCode->enable()) {
                return $this->error('邀请码已过期或已用完，无法启用');
            }
            $message = '邀请码已启用';
        } else {
            return $this->error('当前状态不支持切换');
        }
        return $this->success($inviteCode, $message);
    }

    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $code = strtoupper(trim($request->input('code')));
        $inviteCode = InviteCode::where('code', $code)->first();
        if (!$inviteCode) {
            return $this->success([
                'valid' => false,
                'reason' => '邀请码不存在，请检查输入是否正确',
            ]);
        }
        $inviteCode->checkAndUpdateStatus();
        $canUse = $inviteCode->canUse();
        $reason = '';
        if (!$canUse) {
            if ($inviteCode->status == InviteCode::STATUS_DISABLED) {
                $reason = '邀请码已被禁用，请联系管理员';
            } elseif ($inviteCode->status == InviteCode::STATUS_USED_UP) {
                $reason = '邀请码已达使用上限，无法继续使用';
            } elseif ($inviteCode->status == InviteCode::STATUS_EXPIRED) {
                $reason = '邀请码已过期，请使用新的邀请码';
            } else {
                $reason = '邀请码不可用';
            }
        }
        return $this->success([
            'valid' => $canUse,
            'reason' => $reason,
            'code' => $inviteCode->code,
            'owner' => $inviteCode->owner ? [
                'id' => $inviteCode->owner->id,
                'nickname' => $inviteCode->owner->nickname ?? $inviteCode->owner->username,
                'avatar' => $inviteCode->owner->avatar,
            ] : null,
            'target_level' => $inviteCode->targetDealerLevel ? [
                'id' => $inviteCode->targetDealerLevel->id,
                'name' => $inviteCode->targetDealerLevel->name,
                'level' => $inviteCode->targetDealerLevel->level,
            ] : null,
            'new_user_bonus' => $inviteCode->new_user_bonus,
            'remaining_uses' => $inviteCode->remainingUses(),
            'expires_at' => $inviteCode->expires_at,
        ]);
    }

    public function stats(Request $request)
    {
        $query = InviteCode::query();
        if ($request->has('owner_id')) {
            $query->where('owner_id', $request->input('owner_id'));
        }
        $stats = [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', InviteCode::STATUS_ACTIVE)->count(),
            'used_up' => (clone $query)->where('status', InviteCode::STATUS_USED_UP)->count(),
            'expired' => (clone $query)->where('status', InviteCode::STATUS_EXPIRED)->count(),
            'disabled' => (clone $query)->where('status', InviteCode::STATUS_DISABLED)->count(),
            'total_used_count' => (clone $query)->sum('used_count'),
            'total_max_uses' => (clone $query)->sum('max_uses'),
            'total_reward_amount' => (clone $query)->sum('reward_amount'),
            'total_new_user_bonus' => (clone $query)->sum('new_user_bonus'),
        ];
        $stats['usage_rate'] = $stats['total_max_uses'] > 0
            ? round($stats['total_used_count'] / $stats['total_max_uses'] * 100, 2)
            : 0;
        return $this->success($stats);
    }
}
