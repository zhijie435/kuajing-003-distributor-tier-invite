<?php
class Response
{
    public static function json($data = null, $message = 'success', $code = 0)
    {
        echo json_encode([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success($data = null, $message = '操作成功')
    {
        self::json($data, $message, 0);
    }

    public static function error($message = '操作失败', $code = 1, $data = null)
    {
        self::json($data, $message, $code);
    }

    public static function paginate($list, $total, $page = 1, $pageSize = 10)
    {
        self::json([
            'list' => $list,
            'total' => (int)$total,
            'page' => (int)$page,
            'pageSize' => (int)$pageSize,
            'totalPages' => ceil($total / $pageSize)
        ], 'success', 0);
    }
}
