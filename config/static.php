<?php
/**
 * 静态文件服务配置。
 *
 * 本项目是纯移动端 JSON API + WebSocket 后端，不托管任何静态资源，
 * 因此显式关闭静态文件服务。保留本文件是因为 webman 启动流程会读取
 * config('static') 相关配置，直接删除会破坏内核初始化。
 *
 * 所有媒体资源统一走 RustFS 直传 + Presigned URL，不经过应用服务器。
 */
return [
    'enable' => false,
    'middleware' => [],
];