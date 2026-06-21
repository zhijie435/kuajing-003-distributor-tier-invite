<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success($data = null, string $message = '操作成功', int $code = 200): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time(),
        ], 200, ['Content-Type' => 'application/json;charset=UTF-8'], JSON_UNESCAPED_UNICODE);
    }

    protected function error(string $message = '操作失败', int $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'code' => $code,
            'message' => $message,
            'timestamp' => time(),
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        return response()->json($response, 200, ['Content-Type' => 'application/json;charset=UTF-8'], JSON_UNESCAPED_UNICODE);
    }

    protected function paginated($query, int $page = 1, int $pageSize = 20, string $message = '获取成功'): JsonResponse
    {
        $page = max(1, $page);
        $pageSize = min(100, max(1, $pageSize));
        $total = $query->count();
        $totalPages = (int)ceil($total / $pageSize);
        $list = $query->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();
        return $this->success([
            'list' => $list,
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
        ], $message);
    }
}
