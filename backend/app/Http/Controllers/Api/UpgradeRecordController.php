<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DealerLevel;
use App\Models\UpgradeRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UpgradeRecordController extends Controller
{
    private const SORT_ALLOWED = [
        'created_at', 'updated_at', 'achievement_at_upgrade', 'reward_bonus',
        'status', 'reviewed_at', 'rewarded_at', 'upgrade_type', 'id',
    ];

    public function index(Request $request)
    {
        if ($denied = $this->requireAuth($request)) {
            return $denied;
        }

        $currentId = $this->currentUserId($request);
        $isAdmin = $this->isAdmin($request);

        $query = UpgradeRecord::with(['user', 'oldLevel', 'newLevel', 'operator', 'inviteCode', 'reviewer']);

        if ($request->has('user_id')) {
            $userId = (int)$request->input('user_id');
            if (!$isAdmin && $userId !== $currentId) {
                return $this->error('无权限查询该用户的升级记录', 403);
            }
            $query->byUser($userId);
        } elseif (!$isAdmin) {
            $query->byUser($currentId);
        }

        if ($request->has('upgrade_type')) {
            $type = $request->input('upgrade_type');
            if (is_array($type)) {
                $type = array_map('intval', $type);
                $query->whereIn('upgrade_type', $type);
            } else {
                $query->byType((int)$type);
            }
        }
        if ($request->has('new_level_id')) {
            $query->where('new_level_id', (int)$request->input('new_level_id'));
        }
        if ($request->has('old_level_id')) {
            $query->where('old_level_id', (int)$request->input('old_level_id'));
        }
        if ($request->has('is_rewarded')) {
            $query->where('is_rewarded', $request->boolean('is_rewarded'));
        }
        if ($request->has('has_bonus')) {
            if ($request->boolean('has_bonus')) {
                $query->reward();
            } else {
                $query->where('reward_bonus', 0);
            }
        }
        if ($request->has('status')) {
            $status = $request->input('status');
            $query->byStatus($status);
        }
        if ($request->has('not_rejected') && $request->boolean('not_rejected')) {
            $query->notRejected();
        }
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = (string)$request->input('start_date');
            $endDate = (string)$request->input('end_date');
            if ($startDate !== '' && $endDate !== '') {
                $query->inDateRange($startDate, $endDate);
            }
        }
        if ($request->has('keyword')) {
            $keyword = '%' . $request->input('keyword') . '%';
            $query->where(function ($q) use ($keyword) {
                $q->whereHas('user', function ($sub) use ($keyword) {
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
            return $this->error('查询升级记录列表失败，请稍后重试', 500);
        }
    }

    public function show(Request $request, $id)
    {
        if ($denied = $this->requireAuth($request)) {
            return $denied;
        }

        $record = UpgradeRecord::with(['user', 'oldLevel', 'newLevel', 'operator', 'inviteCode', 'reviewer'])->find((int)$id);
        if (!$record) {
            return $this->error('升级记录不存在', 404);
        }

        if (!$this->isAdmin($request) && (int)$record->user_id !== $this->currentUserId($request)) {
            return $this->error('无权限查看该升级记录', 403);
        }

        $data = $record->toArray();
        $data['status_label'] = $record->getStatusLabel();
        $data['status_tag_type'] = $record->getStatusTagType();
        $data['upgrade_type_label'] = $record->getUpgradeTypeLabel();
        $data['is_upgrade'] = $record->isUpgrade();
        $data['is_downgrade'] = $record->isDowngrade();
        $data['can_approve'] = $record->canApprove();
        $data['can_reject'] = $record->canReject();
        $data['can_reward'] = $record->canReward();
        return $this->success($data);
    }

    public function userHistory(Request $request, $userId)
    {
        if ($denied = $this->requireAuth($request)) {
            return $denied;
        }

        $targetUserId = (int)$userId;
        if ($denied = $this->checkDataScope($request, $targetUserId, '无权限查看该用户的升级历史')) {
            return $denied;
        }

        $user = User::find($targetUserId);
        if (!$user) {
            return $this->error('用户不存在', 404);
        }
        $limit = (int)$request->input('limit', 20);
        $limit = min(100, max(1, $limit));
        try {
            $records = UpgradeRecord::getUserUpgradeHistory($targetUserId, $limit);
            $currentLevel = $user->dealerLevel;
            $eligibleLevel = $user->findEligibleLevel();
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('查询升级历史失败，请稍后重试', 500);
        }
        return $this->success([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'nickname' => $user->nickname,
                'current_level' => $currentLevel ? [
                    'id' => $currentLevel->id,
                    'name' => $currentLevel->name,
                    'level' => $currentLevel->level,
                ] : null,
                'total_achievement' => $user->total_achievement,
                'total_invite_count' => $user->total_invite_count,
            ],
            'eligible_level' => $eligibleLevel && (!$currentLevel || $eligibleLevel->isHigherThan($currentLevel)) ? [
                'id' => $eligibleLevel->id,
                'name' => $eligibleLevel->name,
                'level' => $eligibleLevel->level,
                'min_achievement' => $eligibleLevel->min_achievement,
                'min_invite_count' => $eligibleLevel->min_invite_count,
            ] : null,
            'history' => $records,
            'upgrade_count' => $records->count(),
        ]);
    }

    public function manualUpgrade(Request $request)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'new_level_id' => 'required|integer|exists:dealer_levels,id',
            'reward_bonus' => 'nullable|numeric|min:0',
            'operator_id' => 'nullable|integer|exists:users,id',
            'remark' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $userId = (int)$request->input('user_id');
        $newLevelId = (int)$request->input('new_level_id');
        $user = User::find($userId);
        if (!$user) {
            return $this->error('用户不存在', 404);
        }
        $oldLevelId = $user->dealer_level_id;
        if ($oldLevelId == $newLevelId) {
            return $this->error('新等级与当前等级相同');
        }
        try {
            $record = UpgradeRecord::recordUpgrade(
                $userId,
                $oldLevelId,
                $newLevelId,
                UpgradeRecord::TYPE_ADMIN,
                $request->filled('reward_bonus') ? (float)$request->input('reward_bonus') : null,
                $request->filled('operator_id') ? (int)$request->input('operator_id') : null,
                null,
                $request->input('remark')
            );
            if (!$record) {
                return $this->error('升级失败，等级未发生变化');
            }
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('升级操作失败，请稍后重试', 500);
        }
        return $this->success(
            $record->load(['user', 'oldLevel', 'newLevel', 'operator']),
            '升级操作成功',
            201
        );
    }

    public function checkAutoUpgrade(Request $request)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
            'dry_run' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $dryRun = $request->boolean('dry_run', false);
        $query = User::with('dealerLevel');
        if ($request->has('user_ids')) {
            $ids = array_map('intval', $request->input('user_ids'));
            $query->whereIn('id', $ids);
        }
        try {
            $users = $query->get();
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('加载用户数据失败，请稍后重试', 500);
        }
        $results = [
            'checked_count' => 0,
            'upgraded_count' => 0,
            'skipped_count' => 0,
            'upgrades' => [],
            'skipped' => [],
        ];
        try {
            DB::beginTransaction();
            foreach ($users as $user) {
                $results['checked_count']++;
                $eligibleLevel = $user->findEligibleLevel();
                $currentLevel = $user->dealerLevel;
                if (!$eligibleLevel) {
                    $results['skipped_count']++;
                    $results['skipped'][] = [
                        'user_id' => $user->id,
                        'username' => $user->username,
                        'reason' => '未达到任何等级要求',
                        'achievement' => $user->total_achievement,
                        'invite_count' => $user->total_invite_count,
                    ];
                    continue;
                }
                if ($currentLevel && !$eligibleLevel->isHigherThan($currentLevel)) {
                    $results['skipped_count']++;
                    $results['skipped'][] = [
                        'user_id' => $user->id,
                        'username' => $user->username,
                        'reason' => '当前等级已是最高可达到的等级',
                        'current_level' => $currentLevel->name,
                        'eligible_level' => $eligibleLevel->name,
                    ];
                    continue;
                }
                $oldLevelId = $currentLevel ? $currentLevel->id : null;
                $upgradeInfo = [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'nickname' => $user->nickname,
                    'old_level' => $currentLevel ? $currentLevel->name : null,
                    'new_level' => $eligibleLevel->name,
                    'achievement' => $user->total_achievement,
                    'invite_count' => $user->total_invite_count,
                    'reward_bonus' => $eligibleLevel->reward_bonus,
                ];
                if (!$dryRun) {
                    $record = UpgradeRecord::recordUpgrade(
                        $user->id,
                        $oldLevelId,
                        $eligibleLevel->id,
                        UpgradeRecord::TYPE_AUTO,
                        null,
                        null,
                        null,
                        '系统自动检查升级'
                    );
                    if ($record) {
                        $upgradeInfo['record_id'] = $record->id;
                    }
                }
                $results['upgraded_count']++;
                $results['upgrades'][] = $upgradeInfo;
            }
            if (!$dryRun) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->safeLogException($e);
            return $this->error('自动升级检查失败，请稍后重试', 500);
        }
        $results['dry_run'] = $dryRun;
        return $this->success($results, $dryRun ? '预检查完成' : '自动升级检查完成');
    }

    public function markRewarded(Request $request, $id)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $record = UpgradeRecord::find((int)$id);
        if (!$record) {
            return $this->error('升级记录不存在', 404);
        }
        if ($record->is_rewarded) {
            return $this->error('奖励已发放');
        }
        if ($record->reward_bonus <= 0) {
            return $this->error('该记录无升级奖励');
        }
        if ($record->isRejected()) {
            return $this->error('该升级记录已被拒绝，无法发放奖励');
        }
        $validator = Validator::make($request->all(), [
            'operator_id' => 'nullable|integer|exists:users,id',
            'remark' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        try {
            $record->markRewarded(
                $request->filled('operator_id') ? (int)$request->input('operator_id') : null,
                $request->input('remark')
            );
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('奖励发放失败，请稍后重试', 500);
        }
        return $this->success($record->load(['reviewer']), '奖励已发放');
    }

    public function batchMarkRewarded(Request $request)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'record_ids' => 'required|array|min:1',
            'record_ids.*' => 'integer|exists:upgrade_records,id',
            'operator_id' => 'nullable|integer|exists:users,id',
            'remark' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $operatorId = $request->filled('operator_id') ? (int)$request->input('operator_id') : null;
        $remark = $request->input('remark');
        try {
            $records = UpgradeRecord::whereIn('id', $request->input('record_ids'))
                ->where('is_rewarded', false)
                ->where('reward_bonus', '>', 0)
                ->where('status', '!=', UpgradeRecord::STATUS_REJECTED)
                ->get();
            $count = 0;
            foreach ($records as $record) {
                if ($record->markRewarded($operatorId, $remark)) {
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('批量发放升级奖励失败，请稍后重试', 500);
        }
        return $this->success(['updated_count' => $count], "成功发放{$count}条升级奖励");
    }

    public function markAllPendingRewarded(Request $request)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'operator_id' => 'nullable|integer|exists:users,id',
            'remark' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $operatorId = $request->filled('operator_id') ? (int)$request->input('operator_id') : null;
        $remark = $request->input('remark');
        try {
            $records = UpgradeRecord::unrewarded()
                ->reward()
                ->where('status', '!=', UpgradeRecord::STATUS_REJECTED)
                ->get();
            $count = 0;
            foreach ($records as $record) {
                if ($record->markRewarded($operatorId, $remark ?: '批量发放全部待处理奖励')) {
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('发放全部待处理奖励失败，请稍后重试', 500);
        }
        return $this->success(['updated_count' => $count], "成功发放所有待处理的{$count}条升级奖励");
    }

    public function approve(Request $request, $id)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $record = UpgradeRecord::find((int)$id);
        if (!$record) {
            return $this->error('升级记录不存在', 404);
        }
        if (!$record->canApprove()) {
            return $this->error('当前状态不可审核通过，仅待审核记录可操作');
        }
        $validator = Validator::make($request->all(), [
            'reviewer_id' => 'nullable|integer|exists:users,id',
            'remark' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        try {
            $record->approve(
                $request->filled('reviewer_id') ? (int)$request->input('reviewer_id') : null,
                $request->input('remark')
            );
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('审核通过失败，请稍后重试', 500);
        }
        return $this->success($record->fresh()->load(['reviewer']), '审核通过');
    }

    public function reject(Request $request, $id)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $record = UpgradeRecord::find((int)$id);
        if (!$record) {
            return $this->error('升级记录不存在', 404);
        }
        if (!$record->canReject()) {
            return $this->error('当前状态不可审核拒绝，仅待审核记录可操作');
        }
        $validator = Validator::make($request->all(), [
            'reviewer_id' => 'nullable|integer|exists:users,id',
            'remark' => 'required|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        try {
            $record->reject(
                $request->filled('reviewer_id') ? (int)$request->input('reviewer_id') : null,
                $request->input('remark')
            );
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('审核拒绝失败，请稍后重试', 500);
        }
        return $this->success($record->fresh()->load(['reviewer']), '审核拒绝');
    }

    public function batchApprove(Request $request)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'record_ids' => 'required|array|min:1',
            'record_ids.*' => 'integer|exists:upgrade_records,id',
            'reviewer_id' => 'nullable|integer|exists:users,id',
            'remark' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $reviewerId = $request->filled('reviewer_id') ? (int)$request->input('reviewer_id') : null;
        $remark = $request->input('remark');
        try {
            $records = UpgradeRecord::whereIn('id', $request->input('record_ids'))
                ->pending()
                ->get();
            $count = 0;
            foreach ($records as $record) {
                if ($record->approve($reviewerId, $remark ?: '批量审核通过')) {
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('批量审核通过失败，请稍后重试', 500);
        }
        return $this->success(['updated_count' => $count], "成功审核通过{$count}条升级记录");
    }

    public function batchReject(Request $request)
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'record_ids' => 'required|array|min:1',
            'record_ids.*' => 'integer|exists:upgrade_records,id',
            'reviewer_id' => 'nullable|integer|exists:users,id',
            'remark' => 'required|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $reviewerId = $request->filled('reviewer_id') ? (int)$request->input('reviewer_id') : null;
        $remark = $request->input('remark');
        try {
            $records = UpgradeRecord::whereIn('id', $request->input('record_ids'))
                ->pending()
                ->get();
            $count = 0;
            foreach ($records as $record) {
                if ($record->reject($reviewerId, $remark)) {
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('批量审核拒绝失败，请稍后重试', 500);
        }
        return $this->success(['updated_count' => $count], "成功审核拒绝{$count}条升级记录");
    }

    public function stats(Request $request)
    {
        if ($denied = $this->requireAuth($request)) {
            return $denied;
        }

        $filters = [];
        $startDate = (string)$request->input('start_date', '');
        $endDate = (string)$request->input('end_date', '');
        if ($startDate !== '' && $endDate !== '') {
            $filters['start_date'] = $startDate;
            $filters['end_date'] = $endDate;
        }
        if ($request->has('upgrade_type')) {
            $filters['upgrade_type'] = $request->input('upgrade_type');
        }

        $isAdmin = $this->isAdmin($request);
        $currentId = $this->currentUserId($request);
        if (!$isAdmin) {
            $filters['user_id'] = $currentId;
        }

        try {
            $statsQuery = UpgradeRecord::query();
            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                $statsQuery->inDateRange($filters['start_date'], $filters['end_date']);
            }
            if (!empty($filters['upgrade_type'])) {
                $statsQuery->byType((int)$filters['upgrade_type']);
            }
            if (!$isAdmin) {
                $statsQuery->byUser($currentId);
            }

            $stats = UpgradeRecord::getUpgradeStats($filters);

            $pendingRewardsQuery = UpgradeRecord::unrewarded()->reward();
            if (!$isAdmin) {
                $pendingRewardsQuery->byUser($currentId);
            }
            $pendingRewardsTotal = $pendingRewardsQuery->sum('reward_bonus');

            $levelStatsQuery = UpgradeRecord::select(
                'new_level_id',
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(reward_bonus), 0) as total_bonus')
            );
            if (!$isAdmin) {
                $levelStatsQuery->byUser($currentId);
            }
            $levelUpgradeStats = $levelStatsQuery
                ->groupBy('new_level_id')
                ->with('newLevel')
                ->get()
                ->map(function ($item) {
                    return [
                        'level_id' => $item->new_level_id,
                        'level_name' => $item->newLevel ? $item->newLevel->name : '未知',
                        'upgrade_count' => $item->count,
                        'total_bonus' => (float)$item->total_bonus,
                    ];
                });
        } catch (\Throwable $e) {
            $this->safeLogException($e);
            return $this->error('查询升级统计失败，请稍后重试', 500);
        }
        return $this->success([
            'filters' => $filters,
            'overall' => $stats,
            'pending_rewards_total' => (float)$pendingRewardsTotal,
            'by_level' => $levelUpgradeStats,
        ]);
    }
}
