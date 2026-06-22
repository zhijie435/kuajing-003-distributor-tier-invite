<?php

class Permission
{
    public const CARRIER_VIEW = 'carrier:view';
    public const CARRIER_EDIT = 'carrier:edit';
    public const TRACKING_VIEW = 'tracking:view';
    public const TRACKING_ROLLBACK = 'tracking:rollback';
    public const CONFIG_VIEW = 'config:view';
    public const CONFIG_MANAGE = 'config:manage';

    private static ?array $currentUser = null;
    private static ?array $userPermissions = null;

    private static function init(): void
    {
        if (self::$currentUser !== null) {
            return;
        }
        $token = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
        if (empty($token)) {
            self::$currentUser = null;
            self::$userPermissions = [];
            return;
        }
        global $config;
        $authConfig = $config['auth'] ?? [];
        $roles = $authConfig['roles'] ?? [];
        $tokens = $authConfig['tokens'] ?? [];
        $role = $tokens[$token] ?? null;
        if ($role === null || !isset($roles[$role])) {
            self::$currentUser = null;
            self::$userPermissions = [];
            return;
        }
        self::$currentUser = [
            'role' => $role,
            'token' => $token,
        ];
        self::$userPermissions = $roles[$role]['permissions'] ?? [];
    }

    public static function isSuperAdmin(): bool
    {
        self::init();
        return (self::$currentUser['role'] ?? '') === 'super_admin';
    }

    public static function hasPermission(string $permission): bool
    {
        self::init();
        if (self::isSuperAdmin()) {
            return true;
        }
        return in_array($permission, self::$userPermissions ?? [], true);
    }

    public static function checkPermission(string $permission, int $errorCode): void
    {
        if (!self::hasPermission($permission)) {
            throw new BusinessException($errorCode);
        }
    }

    public static function checkCarrierView(): void
    {
        self::checkPermission(self::CARRIER_VIEW, ErrorCode::PERMISSION_CARRIER_VIEW);
    }

    public static function checkCarrierEdit(): void
    {
        self::checkPermission(self::CARRIER_EDIT, ErrorCode::PERMISSION_CARRIER_EDIT);
    }

    public static function checkTrackingView(): void
    {
        self::checkPermission(self::TRACKING_VIEW, ErrorCode::PERMISSION_TRACKING_VIEW);
    }

    public static function checkTrackingRollback(): void
    {
        self::checkPermission(self::TRACKING_ROLLBACK, ErrorCode::PERMISSION_TRACKING_ROLLBACK);
    }

    public static function checkConfigView(): void
    {
        self::checkPermission(self::CONFIG_VIEW, ErrorCode::PERMISSION_CONFIG_VIEW);
    }

    public static function checkConfigManage(): void
    {
        self::checkPermission(self::CONFIG_MANAGE, ErrorCode::PERMISSION_CONFIG_MANAGE);
    }

    public static function getDataScopeFilter(string $tableAlias = ''): string
    {
        self::init();
        if (self::isSuperAdmin()) {
            return '1=1';
        }
        $prefix = $tableAlias ? $tableAlias . '.' : '';
        return $prefix . 'status = 1';
    }

    public static function maskSensitiveData(array $data, array $sensitiveFields): array
    {
        if (self::isSuperAdmin() || self::hasPermission(self::CONFIG_MANAGE)) {
            return $data;
        }
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = self::maskValue($data[$field]);
            }
        }
        return $data;
    }

    private static function maskValue(string $value): string
    {
        $length = mb_strlen($value);
        if ($length <= 4) {
            return '****';
        }
        if ($length <= 8) {
            return mb_substr($value, 0, 2) . '****' . mb_substr($value, -2);
        }
        return mb_substr($value, 0, 4) . '****' . mb_substr($value, -4);
    }

    public static function authenticateByHeader(): bool
    {
        self::init();
        return self::$currentUser !== null;
    }

    public static function getCurrentUser(): ?array
    {
        self::init();
        return self::$currentUser;
    }

    public static function validateIpWhitelist(string $ip): bool
    {
        global $config;
        $whitelist = $config['auth']['ip_whitelist'] ?? [];
        if (empty($whitelist)) {
            return true;
        }
        return in_array($ip, $whitelist, true);
    }
}
