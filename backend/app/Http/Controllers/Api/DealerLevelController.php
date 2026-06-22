<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DealerLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DealerLevelController extends Controller
{
    public function index(Request $request)
    {
        $query = DealerLevel::withCount('users');
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        if ($request->has('keyword')) {
            $keyword = '%' . $request->input('keyword') . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', $keyword)
                  ->orWhere('code', 'like', $keyword)
                  ->orWhere('description', 'like', $keyword);
            });
        }
        $query->orderBy('level', 'asc');
        return $this->paginated(
            $query,
            $request->input('page', 1),
            $request->input('page_size', 20)
        );
    }

    public function all(Request $request)
    {
        $query = DealerLevel::enabled()->ordered();
        if ($request->has('with_users') && $request->boolean('with_users')) {
            $query->withCount('users');
        }
        $levels = $query->get();
        return $this->success($levels);
    }

    public function show($id)
    {
        $level = DealerLevel::withCount('users')->find($id);
        if (!$level) {
            return $this->error('等级不存在', 404);
        }
        return $this->success($level);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:30|unique:dealer_levels,code',
            'level' => 'required|integer|unique:dealer_levels,level',
            'icon' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'min_achievement' => 'nullable|numeric|min:0',
            'min_invite_count' => 'nullable|integer|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'reward_bonus' => 'nullable|numeric|min:0',
            'privileges' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $level = DealerLevel::create($request->only([
            'name', 'code', 'level', 'icon', 'description',
            'min_achievement', 'min_invite_count', 'commission_rate',
            'reward_bonus', 'privileges', 'is_active',
        ]));
        $level = $level->fresh()->loadCount('users');
        return $this->success($level, '等级创建成功', 201);
    }

    public function update(Request $request, $id)
    {
        $level = DealerLevel::find($id);
        if (!$level) {
            return $this->error('等级不存在', 404);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:50',
            'code' => 'sometimes|string|max:30|unique:dealer_levels,code,' . $id,
            'level' => 'sometimes|integer|unique:dealer_levels,level,' . $id,
            'icon' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'min_achievement' => 'nullable|numeric|min:0',
            'min_invite_count' => 'nullable|integer|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'reward_bonus' => 'nullable|numeric|min:0',
            'privileges' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return $this->error('参数验证失败', 422, $validator->errors());
        }
        $level->update($request->only([
            'name', 'code', 'level', 'icon', 'description',
            'min_achievement', 'min_invite_count', 'commission_rate',
            'reward_bonus', 'privileges', 'is_active',
        ]));
        $level = $level->fresh()->loadCount('users');
        return $this->success($level, '等级更新成功');
    }

    public function destroy($id)
    {
        $level = DealerLevel::find($id);
        if (!$level) {
            return $this->error('等级不存在', 404);
        }
        if ($level->users()->count() > 0) {
            return $this->error('该等级下存在用户，无法删除');
        }
        $level->delete();
        return $this->success(null, '等级删除成功');
    }

    public function toggleActive($id)
    {
        $level = DealerLevel::find($id);
        if (!$level) {
            return $this->error('等级不存在', 404);
        }
        $level->is_active = !$level->is_active;
        $level->save();
        $level = $level->fresh()->loadCount('users');
        return $this->success($level, $level->is_active ? '已启用' : '已禁用');
    }

    public function stats()
    {
        $levels = DealerLevel::enabled()
            ->ordered()
            ->withCount('users')
            ->get();
        $stats = $levels->map(function ($level) {
            return [
                'id' => $level->id,
                'name' => $level->name,
                'code' => $level->code,
                'level' => $level->level,
                'user_count' => $level->users_count,
                'min_achievement' => $level->min_achievement,
                'min_invite_count' => $level->min_invite_count,
            ];
        });
        $totalUsers = $levels->sum('users_count');
        return $this->success([
            'levels' => $stats,
            'total_users' => $totalUsers,
            'level_count' => $levels->count(),
        ]);
    }
}
