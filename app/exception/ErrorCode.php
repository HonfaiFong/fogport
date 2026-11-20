<?php

namespace app\exception;

/**
 * 统一业务错误码。
 *
 * 约定：0 表示成功；错误码按模块分段，便于定位与前端分流。
 * 段位划分：1xxxx 通用 / 2xxxx Identity / 3xxxx Safety / 4xxxx Can / 5xxxx Chat / 6xxxx Notification
 */
final class ErrorCode
{
    public const SUCCESS = 0;

    // ===== 通用 1xxxx =====
    public const INVALID_PARAM = 10001;
    public const UNAUTHORIZED = 10002;
    public const FORBIDDEN = 10003;
    public const NOT_FOUND = 10004;
    public const RATE_LIMITED = 10005;
    public const INTERNAL_ERROR = 10006;

    // ===== Identity 2xxxx =====
    public const SMS_CODE_INVALID = 20001;
    public const SMS_SEND_TOO_FREQUENT = 20002;
    public const TOKEN_EXPIRED = 20003;
    public const TOKEN_REVOKED = 20004;
    public const ACCOUNT_BANNED = 20005;
    public const PERSONA_NOT_FOUND = 20006;
    public const PERSONA_LIMIT_EXCEEDED = 20007;

    // ===== Safety 3xxxx =====
    public const CONTENT_BLOCKED = 30001;
    public const ALREADY_BLOCKED = 30002;
    public const CANNOT_BLOCK_SELF = 30003;
    public const REPORT_DUPLICATED = 30004;

    // ===== Can 4xxxx =====
    public const CAN_NOT_FOUND = 40001;
    public const CAN_DELETED = 40002;
    public const COMMENT_NOT_ALLOWED = 40003;
    public const MEDIA_TYPE_INVALID = 40004;
    public const MEDIA_SIZE_EXCEEDED = 40005;

    // ===== Chat 5xxxx =====
    public const CONVERSATION_NOT_FOUND = 50001;
    public const CONVERSATION_CLOSED = 50002;
    public const SEQ_CONFLICT = 50003;
    public const WS_TICKET_INVALID = 50004;
    public const FLASH_QUEUE_EMPTY = 50005;

    // ===== Notification 6xxxx =====
    public const PUSH_TOKEN_INVALID = 60001;
}