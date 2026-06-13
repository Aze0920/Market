<?php
/**
 * KeyNest 虚拟商品交易平台
 * 内部前台入口，由 public/index.php 加载。
 */

error_reporting(0);
ini_set('display_errors', 0);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    session_start();
}

$rootPath = dirname(__DIR__);
require_once $rootPath . '/config/install.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|woff|woff2|ttf|map|svg)$/', $path)) {
    return false;
}

if ($path !== '/install.php') {
    keynest_require_installed(false);
}

if ($path === '/' || $path === '/index.php') {
    include $rootPath . '/templates/index.html';
    exit;
}

// 主域名商铺路由：/shop/{identifier}
if (preg_match('#^/shop/([a-zA-Z0-9_\-]{1,50})$#', $path, $matches)) {
    $shopIdentifier = $matches[1];
    // 设置商铺标识供前端使用
    $_GET['shop_identifier'] = $shopIdentifier;
    include $rootPath . '/templates/shop.html';
    exit;
}

if ($path === '/admin' || $path === '/admin/') {
    include $rootPath . '/admin/index.php';
    exit;
}

if ($path === '/admin.php') {
    header('Location: /admin/', true, 302);
    exit;
}

http_response_code(404);
echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1></body></html>';
