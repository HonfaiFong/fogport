<?php

namespace app\exception;

use support\Log;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Exception\ExceptionHandler;

/**
 * 全局异常处理器。
 *
 * 统一把异常转换为 PROJECT_SPEC.md 7.1 约定的响应格式：
 *   { "code": 0, "message": "ok", "trace_id": "019a...", "data": {} }
 *
 * 安全要求：
 * - 生产环境绝不透出堆栈与原始异常消息（防内部结构泄漏）
 * - trace_id 由 TraceIdMiddleware 写入请求头，便于日志串联
 */
class Handler extends ExceptionHandler
{
    /** 业务异常属于可预期失败，不记录 error 级日志 */
    public $dontReport = [
        BusinessException::class,
    ];

    public function render(Request $request, Throwable $exception): Response
    {
        $traceId = (string) $request->header('x-trace-id', '');

        // 优先尊重异常自带的 render()（如框架的 PageNotFoundException、自定义业务异常）。
        // 框架默认行为即如此，不可跳过：否则无路由的 404 会被误判成 500。
        if (method_exists($exception, 'render') && ($response = $exception->render($request))) {
            return $response;
        }

        if ($exception instanceof BusinessException) {
            return $this->json($exception->getCode(), $exception->getMessage(), $traceId, $exception->getHttpStatus());
        }

        // 未预期异常：记录完整堆栈，但只向客户端返回安全提示
        Log::error('Unhandled exception', [
            'trace_id' => $traceId,
            'exception' => (string) $exception,
            'path' => $request->path(),
        ]);

        $message = $this->debug
            ? $exception->getMessage()
            : '服务器内部错误，请稍后重试';

        return $this->json(ErrorCode::INTERNAL_ERROR, $message, $traceId, 500);
    }

    private function json(int $code, string $message, string $traceId, int $httpStatus): Response
    {
        return new Response($httpStatus, ['Content-Type' => 'application/json'], json_encode([
            'code' => $code,
            'message' => $message,
            'trace_id' => $traceId,
            'data' => new \stdClass(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}