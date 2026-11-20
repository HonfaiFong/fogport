<?php

/**
 * RustFS（S3 兼容）对象存储配置。
 *
 * RustFS 由独立设备提供，不在本地 compose 中启动，地址通过 .env 注入。
 * 客户端直传流程：HTTP 进程用本配置签发 Presigned URL，手机端直接上传到 RustFS。
 */
return [
    'default' => 'rustfs',

    'disks' => [
        'rustfs' => [
            'driver' => 's3',

            // 服务端访问地址：应用生成 Presigned URL、执行服务端操作时使用
            'endpoint' => env('RUSTFS_ENDPOINT', 'http://127.0.0.1:9000'),

            // 客户端可访问地址：必须能被手机端直接访问，不能填容器内网名
            'public_endpoint' => env('RUSTFS_PUBLIC_ENDPOINT', env('RUSTFS_ENDPOINT', 'http://127.0.0.1:9000')),

            'access_key' => env('RUSTFS_ACCESS_KEY', ''),
            'secret_key' => env('RUSTFS_SECRET_KEY', ''),
            'bucket' => env('RUSTFS_BUCKET', 'fogport-media'),
            'region' => env('RUSTFS_REGION', 'us-east-1'),

            // RustFS/MinIO 类服务必须使用 path-style 寻址，不能用 virtual-hosted-style
            'use_path_style_endpoint' => filter_var(env('RUSTFS_USE_PATH_STYLE', true), FILTER_VALIDATE_BOOL),

            // Presigned URL 有效期（秒），客户端上传窗口
            'presign_ttl' => 900,
        ],
    ],
];