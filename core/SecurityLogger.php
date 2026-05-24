<?php
/**
 * 安全与运行日志类
 */
class SecurityLogger {
    private $logPath;
    private $enabled;
    private $sensitiveKeys;
    
    public function __construct() {
        $this->logPath = dirname(__DIR__) . '/logs';
        $this->enabled = true;
        $this->sensitiveKeys = [
            'password', 'password_confirm', 'key', 'secret', 'token', 'fresh_token', 'csrf_token',
            'smtp_password', 'resend_api_key', 'captcha_secret_key', 'adminPayKey', 'api_key',
            'authorization', 'cookie', 'set-cookie'
        ];
        
        // 确保日志目录存在
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }
    
    public function log($level, $message, $context = [], $channel = 'security') {
        if (!$this->enabled) return;
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userId = $_SESSION['user_id'] ?? 'guest';
        $context = $this->sanitizeContext($context);
        
        $logEntry = sprintf(
            "[%s] [%s] [IP: %s] [USER: %s] %s %s\n",
            $timestamp,
            strtoupper($level),
            $ip,
            $userId,
            $message,
            !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        
        $safeChannel = preg_replace('/[^a-zA-Z0-9_-]/', '', $channel) ?: 'security';
        $filename = $this->logPath . '/' . $safeChannel . '_' . date('Y-m-d') . '.log';
        file_put_contents($filename, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function sanitizeContext($context) {
        if (!is_array($context)) {
            return $context;
        }

        $clean = [];
        foreach ($context as $key => $value) {
            $keyText = strtolower((string)$key);
            $isSensitive = false;
            foreach ($this->sensitiveKeys as $sensitiveKey) {
                if ($keyText === strtolower($sensitiveKey) || strpos($keyText, strtolower($sensitiveKey)) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $clean[$key] = '[FILTERED]';
            } elseif (is_array($value)) {
                $clean[$key] = $this->sanitizeContext($value);
            } elseif (is_string($value) && strlen($value) > 1200) {
                $clean[$key] = substr($value, 0, 1200) . '...[TRUNCATED]';
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }
    
    public function info($message, $context = [], $channel = 'security') {
        $this->log('info', $message, $context, $channel);
    }
    
    public function warning($message, $context = [], $channel = 'security') {
        $this->log('warning', $message, $context, $channel);
    }
    
    public function error($message, $context = [], $channel = 'security') {
        $this->log('error', $message, $context, $channel);
    }

    public function logApiRequest($level, $context = []) {
        $this->log($level, 'API request', $context, 'api');
    }

    public function logPhpError($error) {
        $this->log('error', 'PHP runtime error', $error, 'php_error');
    }
    
    // 记录登录尝试
    public function logLoginAttempt($username, $success, $reason = '') {
        $this->log($success ? 'info' : 'warning', 'Login attempt', [
            'username' => $username,
            'success' => $success,
            'reason' => $reason
        ]);
    }
    
    // 记录速率限制触发
    public function logRateLimit($username, $ip) {
        $this->log('warning', 'Rate limit exceeded', [
            'username' => $username,
            'ip' => $ip
        ]);
    }
    
    // 记录会话异常
    public function logSessionAnomaly($userId, $reason) {
        $this->log('warning', 'Session anomaly detected', [
            'user_id' => $userId,
            'reason' => $reason
        ]);
    }
    
    // 记录权限违规
    public function logPermissionViolation($userId, $action) {
        $this->log('error', 'Permission violation', [
            'user_id' => $userId,
            'action' => $action
        ]);
    }
    
    // 记录敏感操作
    public function logSensitiveAction($userId, $action, $details = []) {
        $this->log('info', 'Sensitive action', array_merge([
            'user_id' => $userId,
            'action' => $action
        ], $details));
    }
}
