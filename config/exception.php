<?php
/**
 * 全局异常处理器注册。
 *
 * 空字符串键表示全局默认处理器，负责把所有异常收敛为
 * PROJECT_SPEC.md 7.1 约定的统一 JSON 响应格式。
 */
return [
    '' => app\exception\Handler::class,
];