<?php

class SubdomainHelper {
    public static function configEnabled($config) {
        $value = $config['subdomain_enabled'] ?? false;
        return $value === true || $value === 1 || $value === '1';
    }

    public static function normalizeBaseDomain($configValue) {
        $value = strtolower(trim((string)$configValue));
        $value = preg_replace('#^https?://#', '', $value);
        $value = preg_replace('#/.*$#', '', $value);
        $value = preg_replace('/^\*\./', '', $value);
        $value = preg_replace('/^www\./', '', $value);
        return trim($value, '.');
    }

    public static function parseBaseDomainsInput($input) {
        $domains = [];
        foreach (preg_split('/[\r\n,;]+/', (string)$input) as $part) {
            $normalized = self::normalizeBaseDomain($part);
            if ($normalized !== '' && !in_array($normalized, $domains, true)) {
                $domains[] = $normalized;
            }
        }
        return $domains;
    }

    public static function getDomainPlans(array $config) {
        $plans = [];
        $seen = [];
        if (!empty($config['subdomain_domain_plans']) && is_array($config['subdomain_domain_plans'])) {
            foreach ($config['subdomain_domain_plans'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $domain = self::normalizeBaseDomain($item['domain'] ?? '');
                if ($domain === '' || isset($seen[$domain])) {
                    continue;
                }
                $seen[$domain] = true;
                $plans[] = [
                    'domain' => $domain,
                    'monthly_price' => max(0.01, floatval($item['monthly_price'] ?? ($config['subdomain_monthly_price'] ?? 10))),
                    'description' => trim((string)($item['description'] ?? '')),
                ];
            }
        }
        if (!empty($plans)) {
            return $plans;
        }
        foreach (self::getLegacyBaseDomains($config) as $domain) {
            if (isset($seen[$domain])) {
                continue;
            }
            $seen[$domain] = true;
            $plans[] = [
                'domain' => $domain,
                'monthly_price' => max(0.01, floatval($config['subdomain_monthly_price'] ?? 10)),
                'description' => '',
            ];
        }
        return $plans;
    }

    public static function getDomainPlan(array $config, $domain) {
        $domain = self::normalizeBaseDomain($domain);
        foreach (self::getDomainPlans($config) as $plan) {
            if (($plan['domain'] ?? '') === $domain) {
                return $plan;
            }
        }
        return null;
    }

    public static function monthlyPriceForDomain(array $config, $domain) {
        $plan = self::getDomainPlan($config, $domain);
        if ($plan) {
            return floatval($plan['monthly_price']);
        }
        return max(0.01, floatval($config['subdomain_monthly_price'] ?? 10));
    }

    private static function getLegacyBaseDomains(array $config) {
        $domains = [];
        if (!empty($config['subdomain_base_domains']) && is_array($config['subdomain_base_domains'])) {
            foreach ($config['subdomain_base_domains'] as $domain) {
                $normalized = self::normalizeBaseDomain($domain);
                if ($normalized !== '' && !in_array($normalized, $domains, true)) {
                    $domains[] = $normalized;
                }
            }
        }
        $legacy = self::normalizeBaseDomain($config['subdomain_base_domain'] ?? '');
        if ($legacy !== '' && !in_array($legacy, $domains, true)) {
            array_unshift($domains, $legacy);
        }
        return array_values($domains);
    }

    public static function getBaseDomains(array $config) {
        $plans = self::getDomainPlans($config);
        if (!empty($plans)) {
            return array_values(array_map(fn($plan) => $plan['domain'], $plans));
        }
        return self::getLegacyBaseDomains($config);
    }

    public static function parseDomainPlansInput($json) {
        $items = is_array($json) ? $json : json_decode((string)$json, true);
        if (!is_array($items)) {
            return [];
        }
        $plans = [];
        $seen = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $domain = self::normalizeBaseDomain($item['domain'] ?? '');
            if ($domain === '' || isset($seen[$domain])) {
                continue;
            }
            $seen[$domain] = true;
            $plans[] = [
                'domain' => $domain,
                'monthly_price' => max(0.01, floatval($item['monthly_price'] ?? 10)),
                'description' => trim((string)($item['description'] ?? '')),
            ];
        }
        return $plans;
    }

    public static function resolveBaseDomainChoice(array $config, $requested = '') {
        $domains = self::getBaseDomains($config);
        if (empty($domains)) {
            return '';
        }
        $requested = self::normalizeBaseDomain($requested);
        if ($requested !== '' && in_array($requested, $domains, true)) {
            return $requested;
        }
        return $domains[0];
    }

    public static function validatePrefix($prefix) {
        $prefix = strtolower(trim((string)$prefix));
        if ($prefix === '') {
            return '请输入二级域名前缀';
        }
        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $prefix)) {
            return '二级域名前缀格式不正确，仅支持字母、数字和连字符';
        }
        $reserved = ['www', 'admin', 'api', 'mail', 'ftp', 'static', 'cdn', 'pay', 'payment', 'auth', 'oauth', 'test', 'dev', 'staging', 'localhost'];
        if (in_array($prefix, $reserved, true)) {
            return '该前缀为系统保留，请更换';
        }
        return null;
    }

    public static function monthSeconds($months) {
        return max(1, intval($months)) * 30 * 86400;
    }

    public static function isExpired(array $subdomain) {
        if (($subdomain['status'] ?? '') !== 'approved') {
            return false;
        }
        $expiresAt = intval($subdomain['expires_at'] ?? 0);
        return $expiresAt > 0 && $expiresAt <= time();
    }

    public static function isActive(array $subdomain) {
        if (($subdomain['status'] ?? '') !== 'approved') {
            return false;
        }
        if (!empty($subdomain['disabled']) || ($subdomain['status'] ?? '') === 'disabled') {
            return false;
        }
        $expiresAt = intval($subdomain['expires_at'] ?? 0);
        if ($expiresAt === 0) {
            return true;
        }
        return $expiresAt > time();
    }

    public static function fullHost($prefix, $baseDomain) {
        $baseDomain = self::normalizeBaseDomain($baseDomain);
        return strtolower(trim((string)$prefix)) . '.' . $baseDomain;
    }

    public static function extractPrefixFromHost($host, $baseDomains) {
        if (is_string($baseDomains)) {
            $baseDomains = self::getBaseDomains(['subdomain_base_domain' => $baseDomains]);
        }
        if (!is_array($baseDomains)) {
            return null;
        }
        foreach ($baseDomains as $baseDomain) {
            $prefix = self::extractPrefixFromHostForDomain($host, $baseDomain);
            if ($prefix !== null) {
                return [
                    'prefix' => $prefix,
                    'base_domain' => self::normalizeBaseDomain($baseDomain),
                ];
            }
        }
        return null;
    }

    private static function extractPrefixFromHostForDomain($host, $baseDomain) {
        $host = strtolower(trim((string)$host));
        $host = preg_replace('/:\d+$/', '', $host);
        $baseDomain = self::normalizeBaseDomain($baseDomain);
        if ($baseDomain === '' || $host === '') {
            return null;
        }
        if ($host === $baseDomain || $host === 'www.' . $baseDomain) {
            return null;
        }
        $suffix = '.' . $baseDomain;
        if (!str_ends_with($host, $suffix)) {
            return null;
        }
        $prefix = substr($host, 0, -strlen($suffix));
        if ($prefix === '' || strpos($prefix, '.') !== false) {
            return null;
        }
        return $prefix;
    }

    public static function subdomainPublicConfig(array $config) {
        $plans = self::getDomainPlans($config);
        $domains = array_map(fn($plan) => $plan['domain'], $plans);
        $primary = $domains[0] ?? '';
        $primaryPlan = $plans[0] ?? null;
        return [
            'enabled' => self::configEnabled($config),
            'domain_plans' => $plans,
            'base_domains' => $domains,
            'base_domain' => $primary,
            'wildcard_domain' => $primary !== '' ? '*.' . $primary : '',
            'monthly_price' => floatval($primaryPlan['monthly_price'] ?? ($config['subdomain_monthly_price'] ?? 10)),
        ];
    }

    public static function hasRenewalPending(array $subdomain) {
        return ($subdomain['status'] ?? '') === 'approved' && intval($subdomain['pending_months'] ?? 0) > 0;
    }

    public static function canRenew(array $subdomain) {
        if (empty($subdomain)) {
            return false;
        }
        if (($subdomain['status'] ?? '') !== 'approved' || !empty($subdomain['disabled'])) {
            return false;
        }
        return intval($subdomain['pending_months'] ?? 0) <= 0;
    }

    public static function decorateSubdomainRecord(array $subdomain, $baseDomain = '') {
        $baseDomain = self::normalizeBaseDomain($baseDomain !== '' ? $baseDomain : ($subdomain['base_domain'] ?? ''));
        $subdomain['base_domain'] = $baseDomain;
        $subdomain['full_domain'] = $baseDomain !== '' ? self::fullHost($subdomain['prefix'] ?? '', $baseDomain) : '';
        $subdomain['is_expired'] = self::isExpired($subdomain);
        $subdomain['is_active'] = self::isActive($subdomain);
        $subdomain['renewal_pending'] = self::hasRenewalPending($subdomain);
        $subdomain['can_renew'] = self::canRenew($subdomain);
        return $subdomain;
    }

    public static function submitApplication($db, $userId, $prefix, $months, array $options = []) {
        $user = $db->getUserById($userId);
        if (!$user) {
            return ['success' => false, 'message' => '用户不存在'];
        }
        $config = $db->getSystemConfig();
        if (!self::configEnabled($config)) {
            return ['success' => false, 'message' => '二级域名功能未开启'];
        }
        if (($user['merchant_status'] ?? 'none') !== 'approved') {
            return ['success' => false, 'message' => '请先完成商家认证后再申请二级域名'];
        }

        $prefix = strtolower(trim((string)$prefix));
        $months = max(1, min(36, intval($months)));
        $error = self::validatePrefix($prefix);
        if ($error) {
            return ['success' => false, 'message' => $error];
        }

        $baseDomain = self::resolveBaseDomainChoice($config, $options['base_domain'] ?? '');
        if ($baseDomain === '') {
            return ['success' => false, 'message' => '后台尚未配置二级域名主域名'];
        }

        $existing = $db->getSellerSubdomainByUserId($userId);
        if ($existing && !in_array($existing['status'] ?? '', ['rejected'], true)) {
            return ['success' => false, 'message' => '您已有二级域名记录，无法重复申请'];
        }

        $occupied = $db->getSellerSubdomainByPrefix($prefix);
        if ($occupied && ($occupied['user_id'] ?? '') !== $userId) {
            return ['success' => false, 'message' => '该前缀已被占用'];
        }

        $now = time();
        $subdomain = [
            'user_id' => $userId,
            'prefix' => $prefix,
            'base_domain' => $baseDomain,
            'status' => 'pending',
            'pending_months' => $months,
            'expires_at' => 0,
            'last_price_paid' => max(0, floatval($options['price_paid'] ?? 0)),
            'disabled' => false,
            'created_at' => $now,
        ];
        if ($existing && ($existing['status'] ?? '') === 'rejected') {
            $subdomain['id'] = $existing['id'];
        } elseif ($occupied && ($occupied['user_id'] ?? '') === $userId) {
            $subdomain['id'] = $occupied['id'];
        }

        if (!$db->saveSellerSubdomain($subdomain)) {
            return ['success' => false, 'message' => '提交失败，请稍后重试'];
        }

        return [
            'success' => true,
            'message' => '已提交二级域名申请，请等待管理员审核通过后生效',
            'subdomain' => $subdomain,
        ];
    }

    public static function submitRenewal($db, $userId, $months, array $options = []) {
        $user = $db->getUserById($userId);
        if (!$user) {
            return ['success' => false, 'message' => '用户不存在'];
        }
        $config = $db->getSystemConfig();
        if (!self::configEnabled($config)) {
            return ['success' => false, 'message' => '二级域名功能未开启'];
        }
        if (($user['merchant_status'] ?? 'none') !== 'approved') {
            return ['success' => false, 'message' => '请先完成商家认证'];
        }

        $existing = $db->getSellerSubdomainByUserId($userId);
        if (!$existing || !self::canRenew($existing)) {
            if ($existing && intval($existing['pending_months'] ?? 0) > 0) {
                return ['success' => false, 'message' => '您已有待审核的续费/开通申请，请等待管理员处理'];
            }
            return ['success' => false, 'message' => '当前状态不可续费'];
        }

        $months = max(1, min(36, intval($months)));
        $baseDomain = self::normalizeBaseDomain($existing['base_domain'] ?? self::resolveBaseDomainChoice($config, $options['base_domain'] ?? ''));
        $existing['base_domain'] = $baseDomain;
        if (($existing['status'] ?? '') !== 'approved') {
            return ['success' => false, 'message' => '当前状态不可续费'];
        }
        $existing['pending_months'] = $months;
        $existing['last_price_paid'] = floatval($existing['last_price_paid'] ?? 0) + max(0, floatval($options['price_paid'] ?? 0));

        if (!$db->saveSellerSubdomain($existing)) {
            return ['success' => false, 'message' => '续费提交失败，请稍后重试'];
        }

        return [
            'success' => true,
            'message' => '续费申请已提交，请等待管理员审核通过后延长到期时间',
            'subdomain' => $existing,
        ];
    }
}
