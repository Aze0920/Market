<?php

class SubdomainHelper {
    public static function normalizeBaseDomain($configValue) {
        $value = strtolower(trim((string)$configValue));
        $value = preg_replace('/^\*\./', '', $value);
        return trim($value, '.');
    }

    public static function extractPrefixFromHost($host, $baseDomain) {
        $host = strtolower(trim((string)$host));
        $baseDomain = self::normalizeBaseDomain($baseDomain);
        if ($baseDomain === '' || $host === '' || $host === $baseDomain || $host === 'www.' . $baseDomain) {
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

    public static function validatePrefix($prefix) {
        $prefix = strtolower(trim((string)$prefix));
        if ($prefix === '') {
            return '请输入二级域名前缀';
        }
        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $prefix)) {
            return '二级域名前缀格式不正确，仅支持字母、数字和连字符，且不能以连字符开头或结尾';
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
        $expiresAt = intval($subdomain['expires_at'] ?? 0);
        return ($subdomain['status'] ?? '') === 'approved' && $expiresAt > 0 && $expiresAt <= time();
    }

    public static function isActive(array $subdomain) {
        if (($subdomain['status'] ?? '') !== 'approved') {
            return false;
        }
        if (($subdomain['disabled'] ?? false) || !empty($subdomain['disabled_at'])) {
            return false;
        }
        $expiresAt = intval($subdomain['expires_at'] ?? 0);
        return $expiresAt > time();
    }

    public static function fullHost($prefix, $baseDomain) {
        $baseDomain = self::normalizeBaseDomain($baseDomain);
        return strtolower(trim((string)$prefix)) . '.' . $baseDomain;
    }
}
