<?php
/**
 * 安全验证辅助类
 * 提供后端统一的安全验证逻辑
 */
class SecurityValidator {
    private $logger;
    
    public function __construct() {
        $this->logger = $GLOBALS['api_logger'] ?? null;
    }

    /**
     * 验证用户名
     * @param string $username 用户名
     * @return array ['valid' => bool, 'message' => string, 'value' => string]
     */
    public function validateUsername($username) {
        $username = trim((string)$username);
        
        if ($username === '') {
            return ['valid' => false, 'message' => '用户名不能为空', 'value' => ''];
        }
        
        if (mb_strlen($username, 'UTF-8') < 2) {
            return ['valid' => false, 'message' => '用户名至少2个字符', 'value' => ''];
        }
        
        if (mb_strlen($username, 'UTF-8') > 30) {
            return ['valid' => false, 'message' => '用户名最多30个字符', 'value' => ''];
        }
        
        // 检查可疑字符
        $dangerousChars = ['<', '>', '"', "'", '`', '\\', '/', '\0', '\n', '\r', '\t'];
        foreach ($dangerousChars as $char) {
            if (strpos($username, $char) !== false) {
                $this->logSuspicious('username_invalid_char', ['char' => $char]);
                return ['valid' => false, 'message' => '用户名包含非法字符', 'value' => ''];
            }
        }
        
        // 检查是否全是数字（防止混淆ID）
        if (preg_match('/^\d+$/', $username)) {
            return ['valid' => false, 'message' => '用户名不能全是数字', 'value' => ''];
        }
        
        // 检查是否包含敏感词
        $sensitiveWords = ['admin', 'administrator', 'system', 'root', 'test', 'null', 'undefined'];
        $usernameLower = strtolower($username);
        foreach ($sensitiveWords as $word) {
            if ($usernameLower === $word) {
                return ['valid' => false, 'message' => '该用户名已被保留', 'value' => ''];
            }
        }
        
        return ['valid' => true, 'message' => '', 'value' => htmlspecialchars($username, ENT_QUOTES, 'UTF-8')];
    }

    /**
     * 验证邮箱
     * @param string $email 邮箱
     * @return array ['valid' => bool, 'message' => string, 'value' => string]
     */
    public function validateEmail($email) {
        $email = trim((string)$email);
        
        if ($email === '') {
            return ['valid' => false, 'message' => '邮箱不能为空', 'value' => ''];
        }
        
        if (mb_strlen($email, 'UTF-8') > 190) {
            return ['valid' => false, 'message' => '邮箱长度超出限制', 'value' => ''];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => '邮箱格式不正确', 'value' => ''];
        }
        
        // 检查危险域名
        $dangerousDomains = ['test.com', 'example.com', 'localhost', '127.0.0.1'];
        $domain = substr($email, strpos($email, '@') + 1);
        foreach ($dangerousDomains as $badDomain) {
            if (strtolower($domain) === strtolower($badDomain)) {
                return ['valid' => false, 'message' => '不支持该邮箱域名', 'value' => ''];
            }
        }
        
        return ['valid' => true, 'message' => '', 'value' => strtolower($email)];
    }

    /**
     * 验证密码强度
     * @param string $password 密码
     * @return array ['valid' => bool, 'message' => string, 'strength' => int]
     */
    public function validatePassword($password) {
        if ($password === '') {
            return ['valid' => false, 'message' => '密码不能为空', 'strength' => 0];
        }
        
        if (strlen($password) < 8) {
            return ['valid' => false, 'message' => '密码至少8位', 'strength' => 0];
        }
        
        if (strlen($password) > 128) {
            return ['valid' => false, 'message' => '密码最多128位', 'strength' => 0];
        }
        
        // 检查是否包含字母
        if (!preg_match('/[A-Za-z]/', $password)) {
            return ['valid' => false, 'message' => '密码需包含字母', 'strength' => 1];
        }
        
        // 检查是否包含数字
        if (!preg_match('/\d/', $password)) {
            return ['valid' => false, 'message' => '密码需包含数字', 'strength' => 2];
        }
        
        // 计算密码强度
        $strength = 0;
        if (strlen($password) >= 8) $strength++;
        if (strlen($password) >= 12) $strength++;
        if (preg_match('/[A-Z]/', $password)) $strength++;
        if (preg_match('/[a-z]/', $password)) $strength++;
        if (preg_match('/\d/', $password)) $strength++;
        if (preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) $strength++;
        
        return ['valid' => true, 'message' => '', 'strength' => min($strength, 5)];
    }

    /**
     * 验证商品标题
     * @param string $title 标题
     * @return array ['valid' => bool, 'message' => string, 'value' => string]
     */
    public function validateProductTitle($title) {
        $title = trim((string)$title);
        
        if ($title === '') {
            return ['valid' => false, 'message' => '标题不能为空', 'value' => ''];
        }
        
        if (mb_strlen($title, 'UTF-8') > 100) {
            return ['valid' => false, 'message' => '标题最多100个字符', 'value' => ''];
        }
        
        // 检查可疑内容
        $suspiciousPatterns = ['<script', 'javascript:', 'onclick', 'onerror', 'eval('];
        $titleLower = strtolower($title);
        foreach ($suspiciousPatterns as $pattern) {
            if (strpos($titleLower, $pattern) !== false) {
                $this->logSuspicious('title_suspicious_content', ['pattern' => $pattern]);
                return ['valid' => false, 'message' => '标题包含非法内容', 'value' => ''];
            }
        }
        
        return ['valid' => true, 'message' => '', 'value' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8')];
    }

    /**
     * 验证商品价格
     * @param mixed $price 价格
     * @return array ['valid' => bool, 'message' => string, 'value' => float]
     */
    public function validateProductPrice($price) {
        $price = floatval($price);
        
        if ($price <= 0) {
            return ['valid' => false, 'message' => '价格必须大于0', 'value' => 0];
        }
        
        if ($price > 999999) {
            return ['valid' => false, 'message' => '价格不能超过999999', 'value' => 0];
        }
        
        // 检查是否为有效数字
        if (!is_numeric($price)) {
            return ['valid' => false, 'message' => '价格格式不正确', 'value' => 0];
        }
        
        return ['valid' => true, 'message' => '', 'value' => round($price, 2)];
    }

    /**
     * 验证商品库存数量
     * @param mixed $quantity 数量
     * @return array ['valid' => bool, 'message' => string, 'value' => int]
     */
    public function validateQuantity($quantity) {
        $quantity = intval($quantity);
        
        if ($quantity < 1) {
            return ['valid' => false, 'message' => '数量至少为1', 'value' => 0];
        }
        
        if ($quantity > 10000) {
            return ['valid' => false, 'message' => '单次最多10000个', 'value' => 0];
        }
        
        return ['valid' => true, 'message' => '', 'value' => $quantity];
    }

    /**
     * 验证商品分类
     * @param string $category 分类
     * @return array ['valid' => bool, 'message' => string, 'value' => string]
     */
    public function validateProductCategory($category) {
        $allowedCategories = ['游戏账号', '流媒体', '软件许可', '其他'];
        
        if (!in_array($category, $allowedCategories, true)) {
            return ['valid' => false, 'message' => '无效的商品分类', 'value' => '其他'];
        }
        
        return ['valid' => true, 'message' => '', 'value' => $category];
    }

    /**
     * 验证商品描述
     * @param string $description 描述
     * @return array ['valid' => bool, 'message' => string, 'value' => string]
     */
    public function validateProductDescription($description) {
        $description = trim((string)$description);
        
        if (mb_strlen($description, 'UTF-8') > 5000) {
            return ['valid' => false, 'message' => '描述最多5000个字符', 'value' => ''];
        }
        
        // 过滤危险内容
        $description = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $description);
        $description = preg_replace('/on\w+\s*=/i', '', $description);
        $description = preg_replace('/javascript:/i', '', $description);
        
        return ['valid' => true, 'message' => '', 'value' => $description];
    }

    /**
     * 验证图片URL
     * @param string $url 图片URL
     * @return array ['valid' => bool, 'message' => string, 'value' => string]
     */
    public function validateImageUrl($url) {
        $url = trim((string)$url);
        
        if ($url === '') {
            return ['valid' => true, 'message' => '', 'value' => '']; // 空URL允许
        }
        
        // 只允许本地路径或特定格式
        if (!preg_match('/^\/uploads\/[a-zA-Z0-9_\/.-]+\.(png|jpg|jpeg|gif|webp)(\?.*)?$/i', $url)) {
            $this->logSuspicious('invalid_image_url', ['url' => substr($url, 0, 100)]);
            return ['valid' => false, 'message' => '图片路径格式不正确', 'value' => ''];
        }
        
        // 检查路径遍历
        if (strpos($url, '..') !== false) {
            $this->logSuspicious('path_traversal_attempt', ['url' => $url]);
            return ['valid' => false, 'message' => '图片路径包含非法字符', 'value' => ''];
        }
        
        return ['valid' => true, 'message' => '', 'value' => $url];
    }

    /**
     * 验证商铺名称
     * @param string $name 商铺名称
     * @return array ['valid' => bool, 'message' => string, 'value' => string]
     */
    public function validateShopName($name) {
        $name = trim((string)$name);
        
        if ($name === '') {
            return ['valid' => true, 'message' => '', 'value' => '']; // 空名称允许
        }
        
        if (mb_strlen($name, 'UTF-8') > 50) {
            return ['valid' => false, 'message' => '商铺名称最多50个字符', 'value' => ''];
        }
        
        // 检查可疑内容
        $suspiciousPatterns = ['<script', 'javascript:', 'onclick', 'onerror', '<', '>'];
        $nameLower = strtolower($name);
        foreach ($suspiciousPatterns as $pattern) {
            if (strpos($nameLower, $pattern) !== false) {
                $this->logSuspicious('shop_name_suspicious', ['pattern' => $pattern]);
                return ['valid' => false, 'message' => '商铺名称包含非法内容', 'value' => ''];
            }
        }
        
        return ['valid' => true, 'message' => '', 'value' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8')];
    }

    /**
     * 验证商铺公告
     * @param string $announcement 公告内容
     * @return array ['valid' => bool, 'message' => string, 'value' => string]
     */
    public function validateShopAnnouncement($announcement) {
        $announcement = trim((string)$announcement);
        
        if ($announcement === '') {
            return ['valid' => true, 'message' => '', 'value' => ''];
        }
        
        if (mb_strlen($announcement, 'UTF-8') > 500) {
            return ['valid' => false, 'message' => '公告最多500个字符', 'value' => ''];
        }
        
        // 过滤危险内容
        $announcement = htmlspecialchars($announcement, ENT_QUOTES, 'UTF-8');
        
        return ['valid' => true, 'message' => '', 'value' => $announcement];
    }

    /**
     * 验证自定义CSS
     * @param string $css CSS内容
     * @return array ['valid' => bool, 'message' => string, 'value' => string]
     */
    public function validateCustomCss($css) {
        $css = trim((string)$css);
        
        if ($css === '') {
            return ['valid' => true, 'message' => '', 'value' => ''];
        }
        
        if (mb_strlen($css, 'UTF-8') > 65535) {
            return ['valid' => false, 'message' => 'CSS内容过长', 'value' => ''];
        }
        
        // 检查危险内容
        $dangerousPatterns = [
            '<script', 'javascript:', 'expression(', 'behavior:', 
            'binding:', '-moz-binding:', 'onload', 'onerror', 'onclick',
            '@import', 'url("http', 'url("ftp'
        ];
        
        $cssLower = strtolower($css);
        foreach ($dangerousPatterns as $pattern) {
            if (strpos($cssLower, strtolower($pattern)) !== false) {
                $this->logSuspicious('css_dangerous_content', ['pattern' => $pattern]);
                return ['valid' => false, 'message' => 'CSS包含危险内容', 'value' => ''];
            }
        }
        
        return ['valid' => true, 'message' => '', 'value' => $css];
    }

    /**
     * 验证管理员权限
     * @return bool 是否为管理员
     */
    public function requireAdmin() {
        if (!isset($_SESSION['user_id'])) {
            if ($this->logger) {
                $this->logger->logUnauthorizedAccess('admin_action', 'not_logged_in');
            }
            return false;
        }
        
        if ($_SESSION['user_role'] !== 'admin') {
            if ($this->logger) {
                $this->logger->logPermissionViolation($_SESSION['user_id'], 'admin_action');
            }
            return false;
        }
        
        return true;
    }

    /**
     * 验证商家认证状态
     * @param array $user 用户信息
     * @return array ['verified' => bool, 'message' => string]
     */
    public function requireMerchantVerified($user) {
        if (!$user) {
            return ['verified' => false, 'message' => '用户不存在'];
        }
        
        if (!($user['qq_bound'] ?? false)) {
            return ['verified' => false, 'message' => '请先绑定第三方账号'];
        }
        
        if (!($user['merchant_verified'] ?? false)) {
            return ['verified' => false, 'message' => '商家认证未通过'];
        }
        
        return ['verified' => true, 'message' => ''];
    }

    /**
     * 验证资源所有权
     * @param string $resourceOwnerId 资源所有者ID
     * @param string $currentUserId 当前用户ID
     * @param bool $allowAdmin 是否允许管理员访问
     * @return bool 是否有权限
     */
    public function validateOwnership($resourceOwnerId, $currentUserId, $allowAdmin = true) {
        if ($resourceOwnerId === $currentUserId) {
            return true;
        }
        
        if ($allowAdmin && $_SESSION['user_role'] === 'admin') {
            return true;
        }
        
        if ($this->logger) {
            $this->logger->logPermissionViolation($currentUserId, 'access_resource_' . $resourceOwnerId);
        }
        
        return false;
    }

    /**
     * 记录可疑行为
     * @param string $event 事件类型
     * @param array $context 上下文
     */
    private function logSuspicious($event, $context = []) {
        if ($this->logger && $this->logger instanceof SecurityLogger) {
            $this->logger->logSuspiciousApiCall($event, $context);
        }
    }

    /**
     * 综合验证商品发布数据
     * @param array $data 商品数据
     * @return array ['valid' => bool, 'errors' => array, 'data' => array]
     */
    public function validateProductPublishData($data) {
        $errors = [];
        $validatedData = [];
        
        // 验证标题
        $titleResult = $this->validateProductTitle($data['title'] ?? '');
        if (!$titleResult['valid']) {
            $errors[] = $titleResult['message'];
        } else {
            $validatedData['title'] = $titleResult['value'];
        }
        
        // 验证分类
        $categoryResult = $this->validateProductCategory($data['category'] ?? '其他');
        if (!$categoryResult['valid']) {
            $errors[] = $categoryResult['message'];
        } else {
            $validatedData['category'] = $categoryResult['value'];
        }
        
        // 验证价格
        $priceResult = $this->validateProductPrice($data['price'] ?? 0);
        if (!$priceResult['valid']) {
            $errors[] = $priceResult['message'];
        } else {
            $validatedData['price'] = $priceResult['value'];
        }
        
        // 验证描述
        $descResult = $this->validateProductDescription($data['description'] ?? '');
        if (!$descResult['valid']) {
            $errors[] = $descResult['message'];
        } else {
            $validatedData['description'] = $descResult['value'];
        }
        
        // 验证图片
        $imageResult = $this->validateImageUrl($data['image'] ?? '');
        if (!$imageResult['valid']) {
            $errors[] = $imageResult['message'];
        } else {
            $validatedData['image'] = $imageResult['value'];
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validatedData
        ];
    }
}