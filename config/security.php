<?php
/**
 * 安全配置文件
 */

// 生产环境配置
return [
    // 安全设置
    'security' => [
        'enable_rate_limit' => true,
        'rate_limit_max_attempts' => 5,
        'rate_limit_window' => 300, // 5分钟
        'session_lifetime' => 86400, // 24小时
        'require_strong_password' => true,
        'password_min_length' => 8,
        'enable_ip_check' => true,
    ],
    
    // CORS设置 — 本配置由 api/index.php 读取并生效
    // 注意：留空数组不允许任何来源；填写具体域名白名单列表
    'cors' => [
        'enabled' => true,
        'allowed_origins' => ['http://localhost', 'https://localhost'], // 填入允许的域名，前端会自动补上当前 HTTP_HOST
        'allowed_methods' => ['GET', 'POST'],
        'allowed_headers' => ['Content-Type', 'X-Requested-With', 'X-CSRF-Token'],
    ],
    
    // API设置
    'api' => [
        'timeout' => 30,
        'max_upload_size' => 5 * 1024 * 1024, // 5MB
        'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf'],
    ],
    
    // 日志设置
    'logging' => [
        'enabled' => true,
        'level' => 'error', // debug, info, warning, error
        'path' => __DIR__ . '/../logs',
    ],
    
    // 管理员设置
    'admin' => [
        'default_username' => 'admin',
        'force_change_password' => true, // 首次登录强制修改密码
        'max_login_attempts' => 5,
    ],
];
