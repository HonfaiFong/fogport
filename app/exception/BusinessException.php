<?php

namespace app\exception;

use RuntimeException;
use Throwable;

/**
 * 业务异常基类。
 *
 * 用于表达「可预期的业务失败」（如参数非法、资源不存在、权限不足），
 * 与程序缺陷导致的未预期异常区分开：
 * - 本类不写入 error 级日志，仅按需 debug 记录
 * - 由 Handler 统一转为 JSON 响应，不透出堆栈
 *
 * 注意：框架内建了 Webman\Exception\BusinessException（基于 error code 的字符串错误码），
 * 本项目需要整数错误码 + HTTP 状态，因此自定义，不复用框架那个。
 */
class BusinessException extends RuntimeException
{
    /**
     * @param int $code ErrorCode 中的业务错误码
     * @param string $message 面向用户的安全提示（严禁包含内部细节）
     * @param int $httpStatus 对应的 HTTP 状态码
     * @param Throwable|null $previous
     */
    public function __construct(
        int $code = ErrorCode::INVALID_PARAM,
        string $message = '',
        protected int $httpStatus = 200,
        ?Throwable $previous = null
    ) {
        parent::__construct($message ?: self::defaultMessage($code), $code, $previous);
    }

    /**
     * 对应的 HTTP 状态码。
     *
     * 业务错误默认返回 200，由响应体的 code 字段区分；
     * 鉴权/限流类才使用 401/403/429，便于网关与客户端中间件统一处理。
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    private static function defaultMessage(int $code): string
    {
        return match ($code) {
            ErrorCode::UNAUTHORIZED, ErrorCode::TOKEN_EXPIRED, ErrorCode::TOKEN_REVOKED => '登录已失效，请重新登录',
            ErrorCode::FORBIDDEN => '无权执行该操作',
            ErrorCode::NOT_FOUND => '资源不存在',
            ErrorCode::RATE_LIMITED, ErrorCode::SMS_SEND_TOO_FREQUENT => '操作过于频繁，请稍后再试',
            ErrorCode::ACCOUNT_BANNED => '账号已被封禁',
            ErrorCode::CONTENT_BLOCKED => '内容包含不当信息',
            default => '请求处理失败',
        };
    }
}