<?php
/**
 * 安全与运行日志类
 * 记录可疑API调用、权限违规、异常行为等安全事件
 */
class SecurityLogger {
    private $logPath;
    private $enabled;
    private $sensitiveKeys;
    private $suspiciousPatterns;
    
    public function __construct() {
        $this->logPath = dirname(__DIR__) . '/logs';
        $this->enabled = true;
        $this->sensitiveKeys = [
            'password', 'password_confirm', 'key', 'secret', 'token', 'fresh_token', 'csrf_token',
            'smtp_password', 'resend_api_key', 'captcha_secret_key', 'adminPayKey', 'api_key',
            'authorization', 'cookie', 'set-cookie'
        ];
        
        // 可疑行为检测模式
        $this->suspiciousPatterns = [
            // SQL注入特征
            'sql_injection' => [
                'patterns' => ["'", '"', '--', ';', '/*', '*/', 'xp_', 'sp_', 'exec', 'execute', 'select', 'insert', 'update', 'delete', 'drop', 'union', 'or 1=1', 'and 1=1'],
                'threshold' => 3
            ],
            // 路径遍历特征
            'path_traversal' => [
                'patterns' => ['../', '..\\', '/etc/', '/var/', '/proc/', 'c:\\', 'file://', 'php://'],
                'threshold' => 1
            ],
            // XSS特征
            'xss_attempt' => [
                'patterns' => ['<script', 'javascript:', 'onerror=', 'onload=', 'onclick=', '<iframe', 'document.cookie', 'eval('],
                'threshold' => 1
            ],
            // 命令注入特征
            'command_injection' => [
                'patterns' => ['|', '&&', '$(', '`', '; ls', '; cat', '; rm', '; wget', '; curl', 'system(', 'exec(', 'shell_exec'],
                'threshold' => 2
            ],
            // 异常高频请求
            'rapid_requests' => [
                'threshold' => 20,
                'window' => 60
            ]
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
        ], 'suspicious');
    }
    
    // 记录敏感操作
    public function logSensitiveAction($userId, $action, $details = []) {
        $this->log('info', 'Sensitive action', array_merge([
            'user_id' => $userId,
            'action' => $action
        ], $details));
    }

    /**
     * 检测并记录可疑输入
     * @param string $input 用户输入内容
     * @param string $source 输入来源（如 'username', 'password', 'search' 等）
     * @return array 检测结果 ['is_suspicious' => bool, 'type' => string|null, 'matches' => array]
     */
    public function detectSuspiciousInput($input, $source = 'unknown') {
        if (!is_string($input) || $input === '') {
            return ['is_suspicious' => false, 'type' => null, 'matches' => []];
        }

        $inputLower = strtolower($input);
        $detectedType = null;
        $matchedPatterns = [];

        foreach ($this->suspiciousPatterns as $type => $config) {
            if (!isset($config['patterns'])) continue;
            
            $matchCount = 0;
            $matches = [];
            
            foreach ($config['patterns'] as $pattern) {
                if (strpos($inputLower, strtolower($pattern)) !== false) {
                    $matchCount++;
                    $matches[] = $pattern;
                }
            }
            
            if ($matchCount >= ($config['threshold'] ?? 1)) {
                $detectedType = $type;
                $matchedPatterns = $matches;
                break;
            }
        }

        if ($detectedType !== null) {
            $this->log('warning', 'Suspicious input detected', [
                'source' => $source,
                'type' => $detectedType,
                'matches' => $matchedPatterns,
                'input_preview' => substr($input, 0, 100)
            ], 'suspicious');
        }

        return [
            'is_suspicious' => $detectedType !== null,
            'type' => $detectedType,
            'matches' => $matchedPatterns
        ];
    }

    /**
     * 记录可疑API调用
     * @param string $action API动作
     * @param array $context 上下文信息
     */
    public function logSuspiciousApiCall($action, $context = []) {
        $this->log('warning', 'Suspicious API call', array_merge([
            'action' => $action,
            'timestamp' => time()
        ], $context), 'suspicious');
    }

    /**
     * 记录管理员操作
     * @param string $action 操作类型
     * @param array $details 操作详情
     */
    public function logAdminAction($action, $details = []) {
        $userId = $_SESSION['user_id'] ?? 'unknown';
        $this->log('info', 'Admin action', array_merge([
            'user_id' => $userId,
            'action' => $action,
            'timestamp' => time()
        ], $details), 'admin');
    }

    /**
     * 记录文件操作
     * @param string $operation 操作类型（read/write/delete）
     * @param string $path 文件路径
     * @param bool $success 是否成功
     */
    public function logFileOperation($operation, $path, $success) {
        $this->log($success ? 'info' : 'warning', 'File operation', [
            'operation' => $operation,
            'path' => $path,
            'success' => $success
        ], 'file');
    }

    /**
     * 记录异常访问尝试（如访问受限资源）
     * @param string $resource 受限资源
     * @param string $reason 原因
     */
    public function logUnauthorizedAccess($resource, $reason) {
        $userId = $_SESSION['user_id'] ?? 'guest';
        $this->log('error', 'Unauthorized access attempt', [
            'user_id' => $userId,
            'resource' => $resource,
            'reason' => $reason
        ], 'suspicious');
    }
}
