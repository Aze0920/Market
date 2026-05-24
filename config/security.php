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
        'password_min_length' => 6,
        'enable_ip_check' => true,
    ],
    
    // CORS设置
    'cors' => [
        'enabled' => true,
        'allowed_origins' => [], // 空数组表示允许所有来源，或填入具体域名
        'allowed_methods' => ['GET', 'POST'],
        'allowed_headers' => ['Content-Type', 'X-Requested-With'],
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
