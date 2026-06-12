<?php
class KeyNestMailer {
    public static function send($to, $subject, $html, $config) {
        $provider = $config['email_provider'] ?? 'smtp';
        if ($provider === 'resend') {
            return self::sendResend($to, $subject, $html, $config);
        }
        return self::sendSmtp($to, $subject, $html, $config);
    }

    public static function defaultTemplate() {
        return '<div style="margin:0;padding:28px;background:#f3f6fb;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Arial,sans-serif;color:#1f2937"><div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 18px 45px rgba(15,23,42,.12);border:1px solid #e5e7eb"><div style="padding:26px 30px;background:linear-gradient(135deg,#6d5dfc,#8b5cf6);color:#fff"><div style="font-size:14px;opacity:.9">{{site_name}}</div><div style="font-size:24px;font-weight:800;margin-top:6px">{{title}}</div></div><div style="padding:30px"><p style="margin:0 0 14px;font-size:15px;line-height:1.8;color:#4b5563">{{message}}</p><div style="margin:22px 0;padding:20px;border-radius:18px;background:#f8fafc;border:1px dashed #c7d2fe;text-align:center"><div style="font-size:13px;color:#64748b;margin-bottom:8px">验证码</div><div style="font-size:34px;letter-spacing:8px;font-weight:900;color:#4f46e5">{{code}}</div></div><p style="margin:0;font-size:13px;line-height:1.8;color:#94a3b8">{{footer}}</p></div></div></div>';
    }

    public static function renderTemplate($config, $vars) {
        $template = trim((string)($config['email_template_html'] ?? '')) ?: self::defaultTemplate();
        $safe = [];
        foreach ($vars as $key => $value) {
            $safe['{{' . $key . '}}'] = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }
        return strtr($template, $safe);
    }

    public static function renderNotificationCard($config, array $vars) {
        $siteName = htmlspecialchars((string)($vars['site_name'] ?? $config['site_name'] ?? 'KeyNest'), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars((string)($vars['title'] ?? '系统通知'), ENT_QUOTES, 'UTF-8');
        $badge = htmlspecialchars((string)($vars['badge'] ?? '通知'), ENT_QUOTES, 'UTF-8');
        $message = nl2br(htmlspecialchars((string)($vars['message'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $footer = htmlspecialchars((string)($vars['footer'] ?? '如非本人操作，请尽快登录账号查看详情。'), ENT_QUOTES, 'UTF-8');
        $time = htmlspecialchars((string)($vars['time'] ?? date('Y-m-d H:i:s')), ENT_QUOTES, 'UTF-8');
        $accentMap = [
            'primary' => ['#6d5dfc', '#8b5cf6', '#eef2ff'],
            'warning' => ['#f59e0b', '#ef4444', '#fff7ed'],
            'success' => ['#10b981', '#059669', '#ecfdf5'],
            'danger' => ['#ef4444', '#dc2626', '#fef2f2'],
            'info' => ['#3b82f6', '#6366f1', '#eff6ff'],
        ];
        $accent = (string)($vars['accent'] ?? 'primary');
        $colors = $accentMap[$accent] ?? $accentMap['primary'];

        $detailsHtml = '';
        if (!empty($vars['details']) && is_array($vars['details'])) {
            foreach ($vars['details'] as $row) {
                if (!is_array($row)) continue;
                $label = htmlspecialchars((string)($row['label'] ?? ''), ENT_QUOTES, 'UTF-8');
                $value = htmlspecialchars((string)($row['value'] ?? ''), ENT_QUOTES, 'UTF-8');
                if ($label === '' && $value === '') continue;
                $detailsHtml .= '<tr><td style="padding:10px 0;color:#64748b;font-size:13px;width:96px;vertical-align:top;white-space:nowrap">' . $label . '</td><td style="padding:10px 0;color:#0f172a;font-size:14px;font-weight:600;word-break:break-word">' . $value . '</td></tr>';
            }
        }

        $highlightHtml = '';
        if (!empty($vars['highlight_value'])) {
            $hlLabel = htmlspecialchars((string)($vars['highlight_label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $hlValue = htmlspecialchars((string)$vars['highlight_value'], ENT_QUOTES, 'UTF-8');
            $highlightHtml = '<div style="margin:20px 0;padding:20px;border-radius:18px;background:' . $colors[2] . ';border:1px dashed ' . $colors[0] . ';text-align:center">'
                . ($hlLabel !== '' ? '<div style="font-size:13px;color:#64748b;margin-bottom:8px">' . $hlLabel . '</div>' : '')
                . '<div style="font-size:30px;font-weight:900;color:' . $colors[0] . ';letter-spacing:3px;word-break:break-all;line-height:1.4">' . $hlValue . '</div></div>';
        }

        $detailsBlock = $detailsHtml !== ''
            ? '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:18px 0 8px;border-top:1px solid #eef2f7;border-bottom:1px solid #eef2f7">' . $detailsHtml . '</table>'
            : '';

        return '<div style="margin:0;padding:28px;background:#f3f6fb;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Arial,sans-serif;color:#1f2937">'
            . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 18px 45px rgba(15,23,42,.12);border:1px solid #e5e7eb">'
            . '<div style="padding:26px 30px;background:linear-gradient(135deg,' . $colors[0] . ',' . $colors[1] . ');color:#fff">'
            . '<div style="display:inline-block;padding:6px 12px;border-radius:999px;background:rgba(255,255,255,.18);font-size:12px;font-weight:700;letter-spacing:.4px">' . $badge . '</div>'
            . '<div style="font-size:14px;opacity:.92;margin-top:12px">' . $siteName . '</div>'
            . '<div style="font-size:24px;font-weight:800;margin-top:6px;line-height:1.35">' . $title . '</div>'
            . '</div>'
            . '<div style="padding:30px">'
            . ($message !== '' ? '<p style="margin:0 0 6px;font-size:15px;line-height:1.8;color:#4b5563">' . $message . '</p>' : '')
            . $detailsBlock
            . $highlightHtml
            . '<p style="margin:18px 0 0;font-size:12px;line-height:1.7;color:#94a3b8">' . $footer . '<br>发送时间：' . $time . '</p>'
            . '</div></div></div>';
    }

    public static function sendNotification($to, $subject, $config, array $cardVars) {
        $email = trim((string)$to);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => '收件邮箱无效'];
        }
        $cardVars['site_name'] = $cardVars['site_name'] ?? ($config['site_name'] ?? 'KeyNest');
        $html = self::renderNotificationCard($config, $cardVars);
        return self::send($email, $subject, $html, $config);
    }

    private static function sendResend($to, $subject, $html, $config) {
        $apiKey = trim((string)($config['resend_api_key'] ?? ''));
        $fromEmail = trim((string)($config['resend_from_email'] ?? ''));
        $fromName = trim((string)($config['resend_from_name'] ?? 'KeyNest'));
        if ($apiKey === '' || $fromEmail === '') {
            return ['success' => false, 'message' => 'Resend API Key 或发件人邮箱未配置'];
        }
        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => '服务器未启用 cURL，无法使用 Resend'];
        }
        $payload = json_encode([
            'from' => $fromName !== '' ? $fromName . ' <' . $fromEmail . '>' : $fromEmail,
            'to' => [$to],
            'subject' => $subject,
            'html' => $html
        ], JSON_UNESCAPED_UNICODE);
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 20
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode((string)$body, true);
        if ($body === false || $code < 200 || $code >= 300) {
            $resendMessage = '';
            if (is_array($decoded)) {
                $resendMessage = $decoded['message'] ?? $decoded['error'] ?? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            return [
                'success' => false,
                'message' => $error ?: ('Resend 返回错误：HTTP ' . $code . ' ' . mb_substr($resendMessage ?: (string)$body, 0, 500))
            ];
        }
        $emailId = is_array($decoded) ? ($decoded['id'] ?? '') : '';
        return ['success' => true, 'message' => $emailId ? ('邮件已发送，Resend ID：' . $emailId) : '邮件已提交到 Resend'];
    }

    private static function readSmtp($socket) {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $data;
    }

    private static function smtpCmd($socket, $command, $expect) {
        if ($command !== null) fwrite($socket, $command . "\r\n");
        $response = self::readSmtp($socket);
        $code = intval(substr($response, 0, 3));
        if (!in_array($code, (array)$expect, true)) {
            return ['success' => false, 'message' => 'SMTP 返回异常：' . trim($response)];
        }
        return ['success' => true, 'response' => $response];
    }

    private static function sendSmtp($to, $subject, $html, $config) {
        $host = trim((string)($config['smtp_host'] ?? ''));
        $port = intval($config['smtp_port'] ?? 465);
        $username = trim((string)($config['smtp_username'] ?? ''));
        $password = (string)($config['smtp_password'] ?? '');
        // 尝试解密 SMTP 密码（支持加密存储）
        if ($password !== '' && $password !== 'N/A') {
            $key = getenv('KEYNEST_ENCRYPTION_KEY') ?: 'KeyNestDefaultEncKey2024!';
            $data = base64_decode($password, true);
            if ($data !== false && strlen($data) >= 17) {
                $iv = substr($data, 0, 16);
                $encrypted = substr($data, 16);
                $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
                if ($decrypted !== false) {
                    $password = $decrypted;
                }
            }
        }
        $secure = strtolower(trim((string)($config['smtp_secure'] ?? 'ssl')));
        $fromEmail = trim((string)($config['resend_from_email'] ?? '')) ?: $username;
        $fromName = trim((string)($config['resend_from_name'] ?? 'KeyNest'));
        if ($host === '' || $port <= 0 || $username === '' || $password === '' || $fromEmail === '') {
            return ['success' => false, 'message' => 'SMTP 主机、端口、账号、密码和发件人邮箱不能为空'];
        }
        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
        $socket = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$socket) return ['success' => false, 'message' => '连接 SMTP 失败：' . $errstr];
        stream_set_timeout($socket, 20);
        $steps = [self::smtpCmd($socket, null, 220), self::smtpCmd($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), 250)];
        if ($secure === 'tls') {
            $steps[] = self::smtpCmd($socket, 'STARTTLS', 220);
            $last = end($steps);
            if (!$last['success'] || !stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return ['success' => false, 'message' => '启用 TLS 加密失败'];
            }
            $steps[] = self::smtpCmd($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), 250);
        }
        foreach ($steps as $step) {
            if (!$step['success']) {
                fclose($socket);
                return $step;
            }
        }
        foreach ([['AUTH LOGIN', 334], [base64_encode($username), 334], [base64_encode($password), 235], ['MAIL FROM:<' . $fromEmail . '>', 250], ['RCPT TO:<' . $to . '>', [250, 251]], ['DATA', 354]] as $item) {
            $res = self::smtpCmd($socket, $item[0], $item[1]);
            if (!$res['success']) {
                fclose($socket);
                return $res;
            }
        }
        $headers = [
            'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64'
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n.";
        $res = self::smtpCmd($socket, $message, 250);
        self::smtpCmd($socket, 'QUIT', 221);
        fclose($socket);
        return $res['success'] ? ['success' => true, 'message' => '邮件已发送'] : $res;
    }
}
