<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InviteChain;
use App\Models\InviteCode;
use App\Models\UpgradeRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InviteChainController extends Controller
{
    private const SORT_ALLOWED = [
        'created_at', 'updated_at', 'depth', 'reward_amount', 'total_commission',
        'status', 'confirmed_at', 'cancelled_at', 'rewarded_at', 'id',
    ];

    public function index(Request $request)
    {
        if ($denied = $this->requireAuth($request)) {
            return $denied;
        }

        $currentId = $this->currentUserId($request);
        $isAdmin = $this->isAdmin($request);

        $query = InviteChain::with(['inviter', 'invitee', 'inviteCode', 'operator']);

        $scopedToSelf = false;
        if ($request->has('inviter_id')) {
            $inviterId = (int)$request->input('inviter_id');
            if (!$isAdmin && $inviterId !== $currentId) {
                return $this->error('无权限按该邀请人查询', 403);
            }
            $query->where('inviter_id', $inviterId);
            $scopedToSelf = $scopedToSelf || ($inviterId === $currentId);
        }
        if ($request->has('invitee_id')) {
            $inviteeId = (int)$request->input('invitee_id');
            if (!$isAdmin && $inviteeId !== $currentId) {
                return $this->error('无权限按该被邀请人查询', 403);
            }
            $query->where('invitee_id', $inviteeId);
            $scopedToSelf = $scopedToSelf || ($inviteeId === $currentId);
        }
        if (!$isAdmin && !$scopedToSelf) {
            $query->where(function ($q) use ($currentId) {
                $q->where('inviter_id', $currentId)
                  ->orWhere('invitee_id', $currentId);
            });
        }

        if ($request->has('depth')) {
            $depth = $request->input('depth');
            if (is_array($depth)) {
                $depth = array_map('intval', $depth);
                $query->whereIn('depth', $depth);
            } else {
                $query->where('depth', (int)$depth);
            }
        }
        if ($request->has('is_direct')) {
            if ($request->boolean('is_direct')) {
                $query->direct();
            } else {
                $query->indirect();
            }
        }
        if ($request->has('is_rewarded')) {
            $query->where('is_rewarded', $request->boolean('is_rewarded'));
        }
        if ($request->has('status')) {
            $status = $request->input('status');
            $query->byStatus($status);
        }
        if ($request->has('not_cancelled') && $request->boolean('not_cancelled')) {
            $query->notCancelled();
        }
        if ($request->has('keyword')) {
            $keyword = '%' . $request->input('keyword') . '%';
            $query->where(function ($q) use ($keyword) {
                $q->whereHas('inviter', function ($sub) use ($keyword) {
                    $sub->where('username', 'like', $keyword)
                        ->orWhere('nickname', 'like', $keyword);
                })->orWhereHas('invitee', function ($sub) use ($keyword) {
                    $sub->where('username', 'like', $keyword)
                        ->orWhere('nickname', 'like', $keyword);
                })->orWhere('remark', 'like', $keyword);
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
            return $this->error('查询邀请记录列表失败，请稍后重试', 500);
        }
    }

    public function show(Request $request, $id)
    {
        if ($denied = $this->requireAuth($request)) {
            return $denied;
        }

        $chain = InviteChain::with(['inviter', 'invitee.dealerLevel', 'inviteCode', 'operator'])->find((int)$id);
        if (!$chain) {
            return $this->error('邀请记录不存在', 404);
        }

        $currentId = $this->currentUserId($request);
        if (!$this->isAdmin($request)
            && (int)$chain->inviter_id !== $currentId
            && (int)$chain->invitee_id !== $currentId) {
            return $this->error('无权限查看该邀请记录', 403);
        }

        $data = $chain->toArray();
        $data['status_label'] = $chain->getStatusLabel();
        $data['status_tag_type'] = $chain->getStatusTagType();
        $data['is_direct'] = $chain->isDirectInvite();
        $data['can_confirm'] = $chain->canConfirm();
        $data['can_cancel'] = $chain->canCancel();
        $data['can_reward'] = !$chain->is_rewarded && $chain->reward_amount > 0 && $chain->isConfirmed();
        return $this->success($data);
    }

    public function useInviteCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'user_id' => 'required|integer|exists:users,id',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $code = strtoupper(trim((string)$request->input('code')));
        if ($code === '') {
            return $this->error('邀请码不能为空', 422);
        }
        try {
            $inviteCode = InviteCode::where('code', $code)->first();
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('邀请码校验失败，请稍后重试', 500);
        }
        if (!$inviteCode) {
            return $this->error('邀请码不存在，请检查输入是否正确', 404);
        }
        try {
            $inviteCode->checkAndUpdateStatus();
        } catch (\Throwable $e) {
            $this->safeLogException($e);
        }
        if (!$inviteCode->canUse()) {
            $reason = match ($inviteCode->status) {
                InviteCode::STATUS_DISABLED => '邀请码已被禁用，请联系管理员',
                InviteCode::STATUS_USED_UP => '邀请码已达使用上限，无法继续使用',
                InviteCode::STATUS_EXPIRED => '邀请码已过期，请使用新的邀请码',
                default => '邀请码不可用',
            };
            return $this->error($reason, 400, [
                'status' => $inviteCode->status,
                'status_label' => $inviteCode->getStatusLabel(),
            ]);
        }
        $userId = (int)$request->input('user_id');
        $user = User::find($userId);
        if (!$user) {
            return $this->error('用户不存在', 404);
        }
        if ($user->inviter_id) {
            return $this->error('该用户已有邀请人，每位用户只能绑定一位邀请人', 400, [
                'inviter_id' => $user->inviter_id,
            ]);
        }
        if ($user->id == $inviteCode->owner_id) {
            return $this->error('不能使用自己的邀请码，请换一个邀请码试试', 400);
        }
        try {
            DB::beginTransaction();
            $user->inviter_id = $inviteCode->owner_id;
            $user->save();
            InviteChain::createInviteChain(
                $inviteCode->owner_id,
                $userId,
                $inviteCode->id,
                $inviteCode->reward_amount
            );
            $inviteCode->markUsed();
            if ($inviteCode->target_dealer_level_id && !$user->dealer_level_id) {
                $oldLevelId = $user->dealer_level_id;
                UpgradeRecord::recordUpgrade(
                    $userId,
                    $oldLevelId,
                    $inviteCode->target_dealer_level_id,
                    UpgradeRecord::TYPE_INVITE_CODE,
                    null,
                    null,
                    null,
                    $inviteCode->id,
                    '使用邀请码升级'
                );
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->safeLogException($e);
            return $this->error('邀请码使用失败，请稍后重试', 500);
        }
        $user = $user->fresh()->load('dealerLevel');
        $inviteCode = $inviteCode->fresh();
        return $this->success([
            'user_id' => $userId,
            'inviter_id' => $inviteCode->owner_id,
            'invite_path' => $user->invite_path,
            'target_level' => $inviteCode->targetDealerLevel ? [
                'id' => $inviteCode->targetDealerLevel->id,
                'name' => $inviteCode->targetDealerLevel->name,
            ] : null,
            'remaining_uses' => $inviteCode->remainingUses(),
            'used_count' => $inviteCode->used_count,
            'max_uses' => $inviteCode->max_uses,
            'new_dealer_level' => $user->dealerLevel ? [
                'id' => $user->dealerLevel->id,
                'name' => $user->dealerLevel->name,
            ] : null,
        ], '邀请码使用成功');
    }

    public function createDirectInvite(Request $request)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'inviter_id' => 'required|integer|exists:users,id',
            'invitee_id' => 'required|integer|exists:users,id',
            'invite_code_id' => 'nullable|integer|exists:invite_codes,id',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $inviterId = (int)$request->input('inviter_id');
        $inviteeId = (int)$request->input('invitee_id');
        if ($inviterId == $inviteeId) {
            return $this->error('邀请人和被邀请人不能相同');
        }
        $invitee = User::find($inviteeId);
        if (!$invitee) {
            return $this->error('被邀请人不存在', 404);
        }
        if ($invitee->inviter_id) {
            return $this->error('被邀请人已有邀请人');
        }
        try {
            DB::beginTransaction();
            $invitee->inviter_id = $inviterId;
            $invitee->save();
            $chain = InviteChain::createInviteChain(
                $inviterId,
                $inviteeId,
                $request->filled('invite_code_id') ? (int)$request->input('invite_code_id') : null
            );
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->safeLogException($e);
            return $this->error('创建邀请关系失败，请稍后重试', 500);
        }
        return $this->success($chain, '邀请关系创建成功', 201);
    }

    public function getInviterLineage(Request $request, $userId)
    {
        if ($denied = $this->requireAuth($request)) {
            return $denied;
        }

        $targetUserId = (int)$userId;
        if ($denied = $this->checkDataScope($request, $targetUserId, '无权限查看该用户的邀请链路')) {
            return $denied;
        }

        $user = User::with('inviter.dealerLevel')->find($targetUserId);
        if (!$user) {
            return $this->error('用户不存在', 404);
        }
        $lineage = [];
        $currentUser = $user;
        $depth = 0;
        try {
            while ($currentUser && $currentUser->inviter) {
                $depth++;
                $inviter = $currentUser->inviter;
                $lineage[] = [
                    'depth' => $depth,
                    'user_id' => $inviter->id,
                    'username' => $inviter->username,
                    'nickname' => $inviter->nickname,
                    'avatar' => $inviter->avatar,
                    'dealer_level' => $inviter->dealerLevel ? [
                        'id' => $inviter->dealerLevel->id,
                        'name' => $inviter->dealerLevel->name,
                        'level' => $inviter->dealerLevel->level,
                    ] : null,
                    'total_achievement' => $inviter->total_achievement,
                    'total_invite_count' => $inviter->total_invite_count,
                ];
                $currentUser = $inviter;
                $currentUser->load('inviter.dealerLevel');
                if ($depth > 20) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('查询邀请链路失败，请稍后重试', 500);
        }
        return $this->success([
            'user_id' => $targetUserId,
            'depth' => $depth,
            'invite_path' => $user->invite_path,
            'lineage' => $lineage,
        ]);
    }

    public function getInviteTree(Request $request, $userId)
    {
        if ($denied = $this->requireAuth($request)) {
            return $denied;
        }

        $targetUserId = (int)$userId;
        if ($denied = $this->checkDataScope($request, $targetUserId, '无权限查看该用户的邀请树')) {
            return $denied;
        }

        $user = User::with('dealerLevel')->find($targetUserId);
        if (!$user) {
            return $this->error('用户不存在', 404);
        }
        $maxDepth = (int)$request->input('max_depth', 3);
        $maxDepth = min(5, max(1, $maxDepth));
        try {
            $tree = $this->buildTree($targetUserId, 0, $maxDepth);
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('查询邀请树失败，请稍后重试', 500);
        }
        return $this->success([
            'root_user' => [
                'id' => $user->id,
                'username' => $user->username,
                'nickname' => $user->nickname,
                'avatar' => $user->avatar,
                'dealer_level' => $user->dealerLevel ? [
                    'id' => $user->dealerLevel->id,
                    'name' => $user->dealerLevel->name,
                    'level' => $user->dealerLevel->level,
                ] : null,
                'total_achievement' => $user->total_achievement,
                'total_invite_count' => $user->total_invite_count,
                'created_at' => $user->created_at,
            ],
            'max_depth' => $maxDepth,
            'tree' => $tree,
        ]);
    }

    private function buildTree(int $userId, int $currentDepth, int $maxDepth): array
    {
        if ($currentDepth >= $maxDepth) {
            return [];
        }
        $invitees = User::with('dealerLevel')
            ->where('inviter_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
        $result = [];
        foreach ($invitees as $invitee) {
            $children = $this->buildTree($invitee->id, $currentDepth + 1, $maxDepth);
            $result[] = [
                'user' => [
                    'id' => $invitee->id,
                    'username' => $invitee->username,
                    'nickname' => $invitee->nickname,
                    'avatar' => $invitee->avatar,
                    'dealer_level' => $invitee->dealerLevel ? [
                        'id' => $invitee->dealerLevel->id,
                        'name' => $invitee->dealerLevel->name,
                        'level' => $invitee->dealerLevel->level,
                    ] : null,
                    'total_achievement' => $invitee->total_achievement,
                    'total_invite_count' => $invitee->total_invite_count,
                    'created_at' => $invitee->created_at,
                ],
                'depth' => $currentDepth + 1,
                'children_count' => count($children),
                'children' => $children,
            ];
        }
        return $result;
    }

    public function getInviteStats(Request $request, $userId)
    {
        if ($denied = $this->requireAuth($request)) {
            return $denied;
        }

        $targetUserId = (int)$userId;
        if ($denied = $this->checkDataScope($request, $targetUserId, '无权限查看该用户的邀请统计')) {
            return $denied;
        }

        $user = User::find($targetUserId);
        if (!$user) {
            return $this->error('用户不存在', 404);
        }
        try {
            $stats = InviteChain::getInviteStats($targetUserId);
            $directInvitees = User::with('dealerLevel')
                ->where('inviter_id', $targetUserId)
                ->orderBy('created_at', 'desc')
                ->limit((int)$request->input('recent_limit', 10))
                ->get();
            $totalAchievementFromDownline = DB::table('invite_chains')
                ->join('users', 'invite_chains.invitee_id', '=', 'users.id')
                ->where('invite_chains.inviter_id', $targetUserId)
                ->sum('users.total_achievement');
            $levelDistribution = User::select(
                'dealer_level_id',
                DB::raw('COUNT(*) as count')
            )
            ->whereIn('id', function ($query) use ($targetUserId) {
                $query->select('invitee_id')
                    ->from('invite_chains')
                    ->where('inviter_id', $targetUserId);
            })
            ->groupBy('dealer_level_id')
            ->pluck('count', 'dealer_level_id')
            ->toArray();
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('查询邀请统计失败，请稍后重试', 500);
        }
        return $this->success([
            'user_id' => $targetUserId,
            'user_invite_count' => $user->total_invite_count,
            'user_achievement' => $user->total_achievement,
            'chain_stats' => $stats,
            'total_downline_achievement' => $totalAchievementFromDownline,
            'level_distribution' => $levelDistribution,
            'recent_invitees' => $directInvitees->map(function ($u) {
                return [
                    'id' => $u->id,
                    'username' => $u->username,
                    'nickname' => $u->nickname,
                    'avatar' => $u->avatar,
                    'dealer_level' => $u->dealerLevel ? $u->dealerLevel->name : null,
                    'total_achievement' => $u->total_achievement,
                    'created_at' => $u->created_at,
                ];
            }),
        ]);
    }

    public function markRewarded(Request $request, $id)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $chain = InviteChain::find((int)$id);
        if (!$chain) {
            return $this->error('邀请记录不存在', 404);
        }
        if ($chain->is_rewarded) {
            return $this->error('奖励已发放');
        }
        if (!$chain->isConfirmed()) {
            return $this->error('当前状态不可发放奖励，请先确认邀请关系');
        }
        $validator = Validator::make($request->all(), [
            'operator_id' => 'nullable|integer|exists:users,id',
            'remark' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        try {
            $chain->markRewarded(
                $request->filled('operator_id') ? (int)$request->input('operator_id') : null,
                $request->input('remark')
            );
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('奖励发放失败，请稍后重试', 500);
        }
        return $this->success($chain->load(['operator']), '奖励已发放');
    }

    public function batchMarkRewarded(Request $request)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'chain_ids' => 'required|array|min:1',
            'chain_ids.*' => 'integer|exists:invite_chains,id',
            'operator_id' => 'nullable|integer|exists:users,id',
            'remark' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $operatorId = $request->filled('operator_id') ? (int)$request->input('operator_id') : null;
        $remark = $request->input('remark');
        try {
            $chains = InviteChain::whereIn('id', $request->input('chain_ids'))
                ->where('is_rewarded', false)
                ->get();
            $count = 0;
            foreach ($chains as $chain) {
                if ($chain->markRewarded($operatorId, $remark)) {
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('批量发放奖励失败，请稍后重试', 500);
        }
        return $this->success(['updated_count' => $count], "成功标记{$count}条记录为已发放");
    }

    public function confirm(Request $request, $id)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $chain = InviteChain::find((int)$id);
        if (!$chain) {
            return $this->error('邀请记录不存在', 404);
        }
        if (!$chain->canConfirm()) {
            return $this->error('当前状态不可确认，仅待确认记录可操作');
        }
        $validator = Validator::make($request->all(), [
            'operator_id' => 'nullable|integer|exists:users,id',
            'remark' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        try {
            $chain->confirm(
                $request->filled('operator_id') ? (int)$request->input('operator_id') : null,
                $request->input('remark')
            );
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('确认邀请关系失败，请稍后重试', 500);
        }
        return $this->success($chain->fresh()->load(['operator']), '邀请关系已确认');
    }

    public function cancel(Request $request, $id)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $chain = InviteChain::find((int)$id);
        if (!$chain) {
            return $this->error('邀请记录不存在', 404);
        }
        if (!$chain->canCancel()) {
            return $this->error('当前状态不可取消，已取消或已发奖记录无法操作');
        }
        $validator = Validator::make($request->all(), [
            'operator_id' => 'nullable|integer|exists:users,id',
            'remark' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        try {
            $chain->cancel(
                $request->filled('operator_id') ? (int)$request->input('operator_id') : null,
                $request->input('remark')
            );
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('取消邀请关系失败，请稍后重试', 500);
        }
        return $this->success($chain->fresh()->load(['operator']), '邀请关系已取消');
    }

    public function batchConfirm(Request $request)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'chain_ids' => 'required|array|min:1',
            'chain_ids.*' => 'integer|exists:invite_chains,id',
            'operator_id' => 'nullable|integer|exists:users,id',
            'remark' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $operatorId = $request->filled('operator_id') ? (int)$request->input('operator_id') : null;
        $remark = $request->input('remark');
        try {
            $chains = InviteChain::whereIn('id', $request->input('chain_ids'))
                ->pending()
                ->get();
            $count = 0;
            foreach ($chains as $chain) {
                if ($chain->confirm($operatorId, $remark ?: '批量审核确认')) {
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('批量确认邀请记录失败，请稍后重试', 500);
        }
        return $this->success(['updated_count' => $count], "成功确认{$count}条邀请记录");
    }

    public function batchCancel(Request $request)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'chain_ids' => 'required|array|min:1',
            'chain_ids.*' => 'integer|exists:invite_chains,id',
            'operator_id' => 'nullable|integer|exists:users,id',
            'remark' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $operatorId = $request->filled('operator_id') ? (int)$request->input('operator_id') : null;
        $remark = $request->input('remark');
        try {
            $chains = InviteChain::whereIn('id', $request->input('chain_ids'))
                ->whereIn('status', [InviteChain::STATUS_PENDING, InviteChain::STATUS_CONFIRMED])
                ->where('is_rewarded', false)
                ->get();
            $count = 0;
            foreach ($chains as $chain) {
                if ($chain->cancel($operatorId, $remark)) {
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('批量取消邀请记录失败，请稍后重试', 500);
        }
        return $this->success(['updated_count' => $count], "成功取消{$count}条邀请记录");
    }
}
