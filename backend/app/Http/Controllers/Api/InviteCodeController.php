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
    private const SORT_ALLOWED = [
        'created_at', 'updated_at', 'expires_at', 'used_count', 'max_uses',
        'reward_amount', 'new_user_bonus', 'status', 'id',
    ];

    public function index(Request $request)
    {
        if ($denied = $this->requireAuth($request)) {
            return $denied;
        }

        $currentId = $this->currentUserId($request);
        $isAdmin = $this->isAdmin($request);

        $query = InviteCode::with(['owner', 'targetDealerLevel', 'createdBy']);

        if ($request->has('owner_id')) {
            $ownerId = (int)$request->input('owner_id');
            if (!$isAdmin && $ownerId !== $currentId) {
                return $this->error('无权限查询其他用户的邀请码', 403);
            }
            $query->where('owner_id', $ownerId);
        } elseif (!$isAdmin) {
            $query->where('owner_id', $currentId);
        }

        if ($request->has('created_by')) {
            $createdBy = (int)$request->input('created_by');
            if (!$isAdmin && $createdBy !== $currentId) {
                return $this->error('无权限按该创建人查询', 403);
            }
            $query->where('created_by', $createdBy);
        }

        if ($request->has('target_dealer_level_id')) {
            $query->where('target_dealer_level_id', (int)$request->input('target_dealer_level_id'));
        }
        if ($request->has('status')) {
            $status = $request->input('status');
            if (is_array($status)) {
                $status = array_map('intval', $status);
                $query->whereIn('status', $status);
            } else {
                $query->where('status', (int)$status);
            }
        }
        if ($request->has('code')) {
            $codeKeyword = (string)$request->input('code');
            if ($codeKeyword !== '') {
                $query->where('code', 'like', '%' . strtoupper($codeKeyword) . '%');
            }
        }
        if ($request->has('can_use') && $request->boolean('can_use')) {
            $query->where('status', InviteCode::STATUS_ACTIVE)
                  ->where('used_count', '<', DB::raw('max_uses'))
                  ->where(function ($q) {
                      $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                  });
        }

        [$sortField, $sortOrder] = $this->sanitizeSort(
            (string)$request->input('sort_by', 'created_at'),
            (string)$request->input('sort_order', 'desc'),
            self::SORT_ALLOWED
        );
        $query->orderBy($sortField, $sortOrder);

        try {
            return $this->paginated(
                $query,
                (int)$request->input('page', 1),
                (int)$request->input('page_size', 20)
            );
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('查询邀请码列表失败，请稍后重试', 500);
        }
    }

    public function show(Request $request, $id)
    {
        if ($denied = $this->requireAuth($request)) {
            return $denied;
        }

        $code = InviteCode::with(['owner', 'targetDealerLevel', 'createdBy', 'inviteChains.invitee'])->find((int)$id);
        if (!$code) {
            return $this->error('邀请码不存在', 404);
        }

        $currentId = $this->currentUserId($request);
        if (!$this->isAdmin($request)
            && (int)$code->owner_id !== $currentId
            && (int)$code->created_by !== $currentId) {
            return $this->error('无权限查看该邀请码详情', 403);
        }

        return $this->success($code);
    }

    public function findByCode(Request $request, $code)
    {
        if ($denied = $this->requireAuth($request)) {
            return $denied;
        }

        $code = strtoupper(trim((string)$code));
        if ($code === '') {
            return $this->error('邀请码不能为空', 422);
        }

        try {
            $inviteCode = InviteCode::with(['owner', 'targetDealerLevel'])
                ->where('code', $code)
                ->first();
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('查询邀请码失败，请稍后重试', 500);
        }

        if (!$inviteCode) {
            return $this->error('邀请码不存在', 404);
        }

        try {
            $inviteCode->checkAndUpdateStatus();
        } catch (\Throwable $e) {
            $this->safeLogException($e);
        }

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
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

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
            $expiresAt = now()->addDays((int)$request->input('expire_days'));
        }
        $createdBy = $request->input('created_by', $this->currentUserId($request) ?? $request->input('owner_id'));

        try {
            DB::beginTransaction();
            $inviteCode = InviteCode::createForUser(
                (int)$request->input('owner_id'),
                (int)$request->input('max_uses', 1),
                $request->filled('target_dealer_level_id') ? (int)$request->input('target_dealer_level_id') : null,
                (float)$request->input('reward_amount', 0),
                (float)$request->input('new_user_bonus', 0),
                $expiresAt,
                (int)$createdBy,
                $request->input('remark')
            );
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->safeLogException($e);
            return $this->error('邀请码创建失败，请稍后重试', 500);
        }
        return $this->success($inviteCode->load(['owner', 'targetDealerLevel']), '邀请码创建成功', 201);
    }

    public function batchCreate(Request $request)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

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
        $count = (int)$request->input('count');
        $expireDays = (int)$request->input('expire_days', 365);
        $expiresAt = now()->addDays($expireDays);
        $createdBy = $request->input('created_by', $this->currentUserId($request) ?? $request->input('owner_id'));
        $codes = [];
        try {
            DB::beginTransaction();
            for ($i = 0; $i < $count; $i++) {
                $code = InviteCode::createForUser(
                    (int)$request->input('owner_id'),
                    (int)$request->input('max_uses', 1),
                    $request->filled('target_dealer_level_id') ? (int)$request->input('target_dealer_level_id') : null,
                    (float)$request->input('reward_amount', 0),
                    (float)$request->input('new_user_bonus', 0),
                    $expiresAt,
                    (int)$createdBy,
                    $request->input('remark')
                );
                $codes[] = $code;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->safeLogException($e);
            return $this->error('批量创建邀请码失败，请稍后重试', 500);
        }
        return $this->success([
            'count' => count($codes),
            'codes' => collect($codes)->pluck('code')->toArray(),
        ], "成功创建{$count}个邀请码", 201);
    }

    public function update(Request $request, $id)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $inviteCode = InviteCode::find((int)$id);
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
        try {
            $inviteCode->update($request->only([
                'target_dealer_level_id', 'max_uses', 'reward_amount',
                'new_user_bonus', 'expires_at', 'remark',
            ]));
            $inviteCode->checkAndUpdateStatus();
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('邀请码更新失败，请稍后重试', 500);
        }
        return $this->success($inviteCode, '邀请码更新成功');
    }

    public function destroy(Request $request, $id)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $inviteCode = InviteCode::find((int)$id);
        if (!$inviteCode) {
            return $this->error('邀请码不存在', 404);
        }
        if ($inviteCode->used_count > 0) {
            return $this->error('该邀请码已被使用，无法删除');
        }
        try {
            $inviteCode->delete();
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('邀请码删除失败，请稍后重试', 500);
        }
        return $this->success(null, '邀请码删除成功');
    }

    public function toggleStatus(Request $request, $id)
    {
        if ($denied = $this->requireAuth($request)) {
            return $denied;
        }

        $inviteCode = InviteCode::find((int)$id);
        if (!$inviteCode) {
            return $this->error('邀请码不存在', 404);
        }

        $currentId = $this->currentUserId($request);
        $isAdmin = $this->isAdmin($request);
        if (!$isAdmin
            && (int)$inviteCode->owner_id !== $currentId
            && (int)$inviteCode->created_by !== $currentId) {
            return $this->error('无权限操作该邀请码', 403);
        }

        $message = '';
        try {
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
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('邀请码状态切换失败，请稍后重试', 500);
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
        $code = strtoupper(trim((string)$request->input('code')));
        if ($code === '') {
            return $this->success([
                'valid' => false,
                'reason' => '邀请码不能为空',
            ]);
        }
        try {
            $inviteCode = InviteCode::where('code', $code)->first();
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('校验邀请码失败，请稍后重试', 500);
        }
        if (!$inviteCode) {
            return $this->success([
                'valid' => false,
                'reason' => '邀请码不存在，请检查输入是否正确',
            ]);
        }
        try {
            $inviteCode->checkAndUpdateStatus();
        } catch (\Throwable $e) {
            $this->safeLogException($e);
        }
        $canUse = $inviteCode->canUse();
        $reason = '';
        if (!$canUse) {
            $reason = match ($inviteCode->status) {
                InviteCode::STATUS_DISABLED => '邀请码已被禁用，请联系管理员',
                InviteCode::STATUS_USED_UP => '邀请码已达使用上限，无法继续使用',
                InviteCode::STATUS_EXPIRED => '邀请码已过期，请使用新的邀请码',
                default => '邀请码不可用',
            };
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
        if ($denied = $this->requireAuth($request)) {
            return $denied;
        }

        $query = InviteCode::query();
        $isAdmin = $this->isAdmin($request);
        $currentId = $this->currentUserId($request);

        if ($request->has('owner_id')) {
            $ownerId = (int)$request->input('owner_id');
            if (!$isAdmin && $ownerId !== $currentId) {
                return $this->error('无权限查询其他用户的邀请码统计', 403);
            }
            $query->where('owner_id', $ownerId);
        } elseif (!$isAdmin) {
            $query->where('owner_id', $currentId);
        }

        try {
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
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('邀请码统计查询失败，请稍后重试', 500);
        }
        return $this->success($stats);
    }
}
