<?php
class KeyNestMailer {
    public static function send($to, $subject, $html, $config) {
        $provider = $config['email_provider'] ?? 'smtp';
        if ($provider === 'resend') {
            return self::sendResend($to, $subject, $html, $config);
        }
        return self::sendSmtp($to, $subject, $html, $config);
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
        if ($body === false || $code < 200 || $code >= 300) {
            return ['success' => false, 'message' => $error ?: ('Resend 返回错误：HTTP ' . $code . ' ' . mb_substr((string)$body, 0, 200))];
        }
        return ['success' => true, 'message' => '邮件已发送'];
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
