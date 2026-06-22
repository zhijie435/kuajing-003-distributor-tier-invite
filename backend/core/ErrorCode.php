<?php

class ErrorCode
{
    const SYSTEM_ERROR = 1000;
    const PARAM_INVALID = 2000;
    const PARAM_MISSING = 2001;
    const PARAM_TYPE_ERROR = 2002;
    const AUTH_TOKEN_MISSING = 3000;
    const AUTH_TOKEN_INVALID = 3001;
    const AUTH_TOKEN_EXPIRED = 3002;
    const AUTH_IP_NOT_ALLOWED = 3003;
    const PERMISSION_DENIED = 4000;
    const PERMISSION_CARRIER_VIEW = 4001;
    const PERMISSION_CARRIER_EDIT = 4002;
    const PERMISSION_TRACKING_VIEW = 4003;
    const PERMISSION_TRACKING_ROLLBACK = 4004;
    const PERMISSION_CONFIG_VIEW = 4005;
    const PERMISSION_CONFIG_MANAGE = 4006;
    const RESOURCE_NOT_FOUND = 5000;
    const CARRIER_NOT_FOUND = 5001;
    const CARRIER_DISABLED = 5002;
    const TRACKING_NOT_FOUND = 5003;
    const CONFIG_NOT_FOUND = 5004;
    const BUSINESS_ERROR = 6000;
    const CARRIER_CODE_DUPLICATE = 6001;
    const CALLBACK_SIGNATURE_INVALID = 6002;
    const CALLBACK_DISABLED = 6003;
    const TRACKING_ALREADY_EXISTS = 6004;
    const ROLLBACK_NO_DATA = 6005;
    const DATABASE_ERROR = 7000;
    const DATABASE_CONNECT_FAILED = 7001;
    const DATABASE_QUERY_FAILED = 7002;
    const DATABASE_TRANSACTION_FAILED = 7003;
    const EXTERNAL_SYSTEM_ERROR = 8000;
    const CARRIER_API_ERROR = 8001;
    const CARRIER_API_TIMEOUT = 8002;

    private static array $messages = [
        self::SYSTEM_ERROR => '系统错误',
        self::PARAM_INVALID => '参数无效',
        self::PARAM_MISSING => '参数缺失',
        self::PARAM_TYPE_ERROR => '参数类型错误',
        self::AUTH_TOKEN_MISSING => '认证令牌缺失',
        self::AUTH_TOKEN_INVALID => '认证令牌无效',
        self::AUTH_TOKEN_EXPIRED => '认证令牌已过期',
        self::AUTH_IP_NOT_ALLOWED => 'IP地址不在白名单中',
        self::PERMISSION_DENIED => '权限不足',
        self::PERMISSION_CARRIER_VIEW => '无承运商查看权限',
        self::PERMISSION_CARRIER_EDIT => '无承运商编辑权限',
        self::PERMISSION_TRACKING_VIEW => '无轨迹查看权限',
        self::PERMISSION_TRACKING_ROLLBACK => '无轨迹回滚权限',
        self::PERMISSION_CONFIG_VIEW => '无配置查看权限',
        self::PERMISSION_CONFIG_MANAGE => '无配置管理权限',
        self::RESOURCE_NOT_FOUND => '资源不存在',
        self::CARRIER_NOT_FOUND => '承运商不存在',
        self::CARRIER_DISABLED => '承运商已停用',
        self::TRACKING_NOT_FOUND => '轨迹记录不存在',
        self::CONFIG_NOT_FOUND => '配置不存在',
        self::BUSINESS_ERROR => '业务错误',
        self::CARRIER_CODE_DUPLICATE => '承运商编码已存在',
        self::CALLBACK_SIGNATURE_INVALID => '回调签名验证失败',
        self::CALLBACK_DISABLED => '回调功能已停用',
        self::TRACKING_ALREADY_EXISTS => '轨迹记录已存在',
        self::ROLLBACK_NO_DATA => '没有可回滚的数据',
        self::DATABASE_ERROR => '数据库错误',
        self::DATABASE_CONNECT_FAILED => '数据库连接失败',
        self::DATABASE_QUERY_FAILED => '数据库查询失败',
        self::DATABASE_TRANSACTION_FAILED => '数据库事务失败',
        self::EXTERNAL_SYSTEM_ERROR => '外部系统错误',
        self::CARRIER_API_ERROR => '承运商API错误',
        self::CARRIER_API_TIMEOUT => '承运商API超时',
    ];

    public static function getMessage(int $code): string
    {
        return self::$messages[$code] ?? '未知错误';
    }
}
