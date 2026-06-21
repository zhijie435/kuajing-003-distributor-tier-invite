<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;

class Controller extends BaseController
{
    use ApiResponse;

    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_ADMIN = 'admin';
    const ROLE_USER = 'user';

    protected function currentUser(Request $request): ?User
    {
        $userId = $request->header('X-User-Id') ?: $request->input('__current_user_id');
        if (!$userId) {
            return null;
        }
        return User::find((int)$userId);
    }

    protected function currentUserId(Request $request): ?int
    {
        $user = $this->currentUser($request);
        return $user ? $user->id : null;
    }

    protected function currentUserRole(Request $request): string
    {
        $role = $request->header('X-User-Role') ?: $request->input('__current_user_role', self::ROLE_USER);
        $allowed = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_USER];
        return in_array($role, $allowed, true) ? $role : self::ROLE_USER;
    }

    protected function isSuperAdmin(Request $request): bool
    {
        return $this->currentUserRole($request) === self::ROLE_SUPER_ADMIN;
    }

    protected function isAdmin(Request $request): bool
    {
        return in_array($this->currentUserRole($request), [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true);
    }

    protected function sanitizeSort(string $sortField, string $sortOrder, array $allowedFields): array
    {
        if (!in_array($sortField, $allowedFields, true)) {
            $sortField = $allowedFields[0] ?? 'created_at';
        }
        $sortOrder = strtolower($sortOrder);
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'desc';
        }
        return [$sortField, $sortOrder];
    }

    protected function checkDataScope(Request $request, int $targetUserId, string $violationMessage = '无权限访问该数据'): ?\Illuminate\Http\JsonResponse
    {
        if ($this->isAdmin($request)) {
            return null;
        }
        $currentId = $this->currentUserId($request);
        if ($currentId === null || $currentId !== $targetUserId) {
            return $this->error($violationMessage, 403);
        }
        return null;
    }

    protected function requireAuth(Request $request): ?\Illuminate\Http\JsonResponse
    {
        if (!$this->currentUser($request)) {
            return $this->error('请先登录', 401);
        }
        return null;
    }

    protected function requireAdmin(Request $request): ?\Illuminate\Http\JsonResponse
    {
        if (!$this->currentUser($request)) {
            return $this->error('请先登录', 401);
        }
        if (!$this->isAdmin($request)) {
            return $this->error('需要管理员权限', 403);
        }
        return null;
    }

    protected function safeLogException(\Throwable $e): string
    {
        try {
            report($e);
        } catch (\Throwable $_) {
        }
        return $e->getMessage();
    }
}
