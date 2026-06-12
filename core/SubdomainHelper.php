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

    public static function extractPrefixFromHost($host, $baseDomain) {
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
        $baseDomain = self::normalizeBaseDomain($config['subdomain_base_domain'] ?? '');
        return [
            'enabled' => self::configEnabled($config),
            'base_domain' => $baseDomain,
            'wildcard_domain' => $baseDomain !== '' ? '*.' . $baseDomain : '',
            'monthly_price' => max(0.01, floatval($config['subdomain_monthly_price'] ?? 10)),
        ];
    }

    public static function decorateSubdomainRecord(array $subdomain, $baseDomain) {
        $subdomain['full_domain'] = $baseDomain !== '' ? self::fullHost($subdomain['prefix'] ?? '', $baseDomain) : '';
        $subdomain['is_expired'] = self::isExpired($subdomain);
        $subdomain['is_active'] = self::isActive($subdomain);
        return $subdomain;
    }
}
