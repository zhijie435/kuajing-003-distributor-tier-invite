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
    public function index(Request $request)
    {
        $query = UpgradeRecord::with(['user', 'oldLevel', 'newLevel', 'operator', 'inviteCode']);
        if ($request->has('user_id')) {
            $query->byUser($request->input('user_id'));
        }
        if ($request->has('upgrade_type')) {
            $type = $request->input('upgrade_type');
            if (is_array($type)) {
                $query->whereIn('upgrade_type', $type);
            } else {
                $query->byType($type);
            }
        }
        if ($request->has('new_level_id')) {
            $query->where('new_level_id', $request->input('new_level_id'));
        }
        if ($request->has('old_level_id')) {
            $query->where('old_level_id', $request->input('old_level_id'));
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
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->inDateRange($request->input('start_date'), $request->input('end_date'));
        }
        if ($request->has('keyword')) {
            $keyword = '%' . $request->input('keyword') . '%';
            $query->whereHas('user', function ($q) use ($keyword) {
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

    public function show($id)
    {
        $record = UpgradeRecord::with(['user', 'oldLevel', 'newLevel', 'operator', 'inviteCode'])->find($id);
        if (!$record) {
            return $this->error('升级记录不存在', 404);
        }
        return $this->success($record);
    }

    public function userHistory(Request $request, $userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return $this->error('用户不存在', 404);
        }
        $limit = $request->input('limit', 20);
        $records = UpgradeRecord::getUserUpgradeHistory($userId, $limit);
        $currentLevel = $user->dealerLevel;
        $eligibleLevel = $user->findEligibleLevel();
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
        $userId = $request->input('user_id');
        $newLevelId = $request->input('new_level_id');
        $user = User::find($userId);
        $oldLevelId = $user->dealer_level_id;
        if ($oldLevelId == $newLevelId) {
            return $this->error('新等级与当前等级相同');
        }
        $record = UpgradeRecord::recordUpgrade(
            $userId,
            $oldLevelId,
            $newLevelId,
            UpgradeRecord::TYPE_ADMIN,
            $request->input('reward_bonus'),
            $request->input('operator_id'),
            null,
            $request->input('remark')
        );
        if (!$record) {
            return $this->error('升级失败');
        }
        return $this->success(
            $record->load(['user', 'oldLevel', 'newLevel', 'operator']),
            '升级操作成功',
            201
        );
    }

    public function checkAutoUpgrade(Request $request)
    {
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
            $query->whereIn('id', $request->input('user_ids'));
        }
        $users = $query->get();
        $results = [
            'checked_count' => 0,
            'upgraded_count' => 0,
            'skipped_count' => 0,
            'upgrades' => [],
            'skipped' => [],
        ];
        DB::beginTransaction();
        try {
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
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('自动升级检查失败：' . $e->getMessage(), 500);
        }
        $results['dry_run'] = $dryRun;
        return $this->success($results, $dryRun ? '预检查完成' : '自动升级检查完成');
    }

    public function markRewarded($id)
    {
        $record = UpgradeRecord::find($id);
        if (!$record) {
            return $this->error('升级记录不存在', 404);
        }
        if ($record->is_rewarded) {
            return $this->error('奖励已发放');
        }
        if ($record->reward_bonus <= 0) {
            return $this->error('该记录无升级奖励');
        }
        $record->markRewarded();
        return $this->success($record, '奖励已发放');
    }

    public function batchMarkRewarded(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'record_ids' => 'required|array|min:1',
            'record_ids.*' => 'integer|exists:upgrade_records,id',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $count = UpgradeRecord::whereIn('id', $request->input('record_ids'))
            ->where('is_rewarded', false)
            ->where('reward_bonus', '>', 0)
            ->update([
                'is_rewarded' => true,
                'rewarded_at' => now(),
            ]);
        return $this->success(['updated_count' => $count], "成功发放{$count}条升级奖励");
    }

    public function markAllPendingRewarded(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'operator_id' => 'nullable|integer|exists:users,id',
        ]);
        $count = UpgradeRecord::unrewarded()
            ->reward()
            ->update([
                'is_rewarded' => true,
                'rewarded_at' => now(),
            ]);
        return $this->success(['updated_count' => $count], "成功发放所有待处理的{$count}条升级奖励");
    }

    public function stats(Request $request)
    {
        $filters = [];
        if ($request->has('start_date') && $request->has('end_date')) {
            $filters['start_date'] = $request->input('start_date');
            $filters['end_date'] = $request->input('end_date');
        }
        if ($request->has('upgrade_type')) {
            $filters['upgrade_type'] = $request->input('upgrade_type');
        }
        $stats = UpgradeRecord::getUpgradeStats($filters);
        $pendingRewardsTotal = UpgradeRecord::unrewarded()->reward()->sum('reward_bonus');
        $levelUpgradeStats = UpgradeRecord::select(
            'new_level_id',
            DB::raw('COUNT(*) as count'),
            DB::raw('COALESCE(SUM(reward_bonus), 0) as total_bonus')
        )
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
        return $this->success([
            'filters' => $filters,
            'overall' => $stats,
            'pending_rewards_total' => (float)$pendingRewardsTotal,
            'by_level' => $levelUpgradeStats,
        ]);
    }
}
