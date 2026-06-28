<?php

/**
 * Cliente SMTP mínimo en PHP puro (sin dependencias).
 * Soporta STARTTLS (587), TLS implícito (465) y AUTH LOGIN.
 * La configuración vive en config['mail'] (sobreescrito en local.php).
 */
class MailService
{
    private array $cfg;
    private ?string $lastError = null;

    public function __construct()
    {
        $config    = require base_path('app/config/config.php');
        $this->cfg = $config['mail'] ?? [];
    }

    public function isConfigured(): bool
    {
        return !empty($this->cfg['host']) && !empty($this->cfg['from']);
    }

    /** Último error de SMTP (para diagnóstico). null si el último envío fue OK. */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    // ── Plantillas ─────────────────────────────────────────────────────────────

    public function sendPasswordReset(string $toEmail, string $toName, string $link): bool
    {
        $subject  = 'Restablece tu contraseña — Mi Llamamiento';
        $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

        $html = '<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#1f2937">'
              . '<h2 style="margin:0 0 12px;font-size:20px">Mi Llamamiento</h2>'
              . '<p style="margin:0 0 16px;line-height:1.5">Hola ' . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') . ', recibimos una solicitud para restablecer la contraseña de tu cuenta.</p>'
              . '<p style="margin:24px 0"><a href="' . $safeLink . '" style="background:#2563eb;color:#fff;padding:13px 22px;border-radius:12px;text-decoration:none;display:inline-block;font-weight:600">Restablecer contraseña</a></p>'
              . '<p style="margin:0 0 6px;line-height:1.5">O copia este enlace en tu navegador:</p>'
              . '<p style="word-break:break-all;color:#2563eb;margin:0 0 24px">' . $safeLink . '</p>'
              . '<p style="color:#6b7280;font-size:13px;line-height:1.5;margin:0">Este enlace caduca en 1 hora. Si no solicitaste el cambio, ignora este mensaje y tu contraseña seguirá igual.</p>'
              . '</div>';

        $text = "Mi Llamamiento\n\n"
              . "Hola $toName, recibimos una solicitud para restablecer tu contraseña.\n\n"
              . "Abre este enlace para elegir una nueva (caduca en 1 hora):\n$link\n\n"
              . "Si no lo solicitaste, ignora este mensaje.\n";

        return $this->send($toEmail, $toName, $subject, $html, $text);
    }

    // ── Envío ──────────────────────────────────────────────────────────────────

    public function send(string $toEmail, string $toName, string $subject, string $html, string $text): bool
    {
        $this->lastError = null;
        if (!$this->isConfigured()) {
            $this->lastError = 'SMTP no configurado (falta host o from en config[mail]).';
            error_log('MailService: ' . $this->lastError . ' Destino: ' . $toEmail);
            return false;
        }
        try {
            return $this->smtpSend($toEmail, $toName, $subject, $html, $text);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('MailService: fallo al enviar a ' . $toEmail . ': ' . $e->getMessage());
            return false;
        }
    }

    private function smtpSend(string $toEmail, string $toName, string $subject, string $html, string $text): bool
    {
        $host      = (string) $this->cfg['host'];
        $port      = (int) ($this->cfg['port'] ?? 587);
        $enc       = strtolower((string) ($this->cfg['encryption'] ?? 'tls'));
        $user      = (string) ($this->cfg['username'] ?? '');
        $pass      = (string) ($this->cfg['password'] ?? '');
        $fromEmail = (string) $this->cfg['from'];
        $fromName  = (string) ($this->cfg['from_name'] ?? 'Mi Llamamiento');
        $timeout   = (int) ($this->cfg['timeout'] ?? 15);
        $ehlo      = $this->ehloName($fromEmail);

        $transport = ($enc === 'ssl') ? "ssl://$host:$port" : "tcp://$host:$port";
        $ctx = stream_context_create(['ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
            'SNI_enabled'      => true,
            'peer_name'        => $host,
        ]]);

        $conn = @stream_socket_client($transport, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$conn) {
            throw new \RuntimeException("conexión fallida: $errstr ($errno)");
        }
        stream_set_timeout($conn, $timeout);

        try {
            $this->expect($conn, [220]);
            $this->cmd($conn, "EHLO $ehlo", [250]);

            if ($enc === 'tls') {
                $this->cmd($conn, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('negociación STARTTLS fallida');
                }
                $this->cmd($conn, "EHLO $ehlo", [250]);
            }

            if ($user !== '') {
                $this->cmd($conn, 'AUTH LOGIN', [334]);
                $this->cmd($conn, base64_encode($user), [334]);
                $this->cmd($conn, base64_encode($pass), [235]);
            }

            $this->cmd($conn, "MAIL FROM:<$fromEmail>", [250]);
            $this->cmd($conn, "RCPT TO:<$toEmail>", [250, 251]);
            $this->cmd($conn, 'DATA', [354]);

            $message = $this->buildMessage($fromEmail, $fromName, $toEmail, $toName, $subject, $html, $text);
            $message = preg_replace('/^\./m', '..', $message); // dot-stuffing
            fwrite($conn, $message . "\r\n.\r\n");
            $this->expect($conn, [250]);

            fwrite($conn, "QUIT\r\n");
        } finally {
            fclose($conn);
        }
        return true;
    }

    // ── Protocolo ──────────────────────────────────────────────────────────────

    private function cmd($conn, string $command, array $codes): void
    {
        fwrite($conn, $command . "\r\n");
        $this->expect($conn, $codes);
    }

    private function expect($conn, array $codes): void
    {
        [$code, $raw] = $this->readReply($conn);
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException('respuesta SMTP inesperada: ' . trim($raw));
        }
    }

    /** Lee una respuesta SMTP, manejando respuestas multilínea (250-... / 250 ...). */
    private function readReply($conn): array
    {
        $data = '';
        while (($line = fgets($conn, 515)) !== false) {
            $data .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        if ($data === '') {
            throw new \RuntimeException('sin respuesta del servidor SMTP (timeout?)');
        }
        return [(int) substr($data, 0, 3), $data];
    }

    // ── Construcción del mensaje (multipart/alternative) ────────────────────────

    private function buildMessage(string $fromEmail, string $fromName, string $toEmail, string $toName, string $subject, string $html, string $text): string
    {
        $boundary = 'b' . bin2hex(random_bytes(8));
        $date     = gmdate('D, d M Y H:i:s') . ' +0000';
        $msgId    = '<' . bin2hex(random_bytes(12)) . '@' . $this->ehloName($fromEmail) . '>';
        $toHeader = $toName !== '' ? $this->encodeHeader($toName) . " <$toEmail>" : $toEmail;

        $headers = [
            'Date: ' . $date,
            'Message-ID: ' . $msgId,
            'From: ' . $this->encodeHeader($fromName) . " <$fromEmail>",
            'To: ' . $toHeader,
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = "--$boundary\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($text)) . "\r\n"
              . "--$boundary\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($html)) . "\r\n"
              . "--$boundary--\r\n";

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function encodeHeader(string $text): string
    {
        if (preg_match('/[^\x20-\x7e]/', $text)) {
            return '=?UTF-8?B?' . base64_encode($text) . '?=';
        }
        return $text;
    }

    private function ehloName(string $fromEmail): string
    {
        $domain = substr(strrchr($fromEmail, '@') ?: '', 1);
        return $domain !== '' ? $domain : 'localhost';
    }
}
