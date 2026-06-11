<?php

class PushService
{
    private string $publicKey;
    private string $privateKeyPem;
    private string $subject;

    public function __construct(private readonly PDO $db)
    {
        $config              = require base_path('app/config/config.php');
        $this->publicKey     = $config['vapid']['public_key'];
        $this->privateKeyPem = $config['vapid']['private_key_pem'];
        $this->subject       = $config['vapid']['subject'] ?? 'mailto:admin@localhost';
    }

    // ── Public API ────────────────────────────────────────────────────────────

    public function isPersonalArea(string $areaId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT wat.is_personal
             FROM work_area_instances wai
             JOIN work_area_templates wat ON wat.id = wai.template_id
             WHERE wai.id = ?'
        );
        $stmt->execute([$areaId]);
        $row = $stmt->fetch();
        return $row && (bool) $row['is_personal'];
    }

    public function sendToUser(string $userId, string $title, string $body): void
    {
        $stmt = $this->db->prepare(
            'SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $sub) {
            $this->deliver($sub, $title, $body);
        }
    }

    /** Send to all active members of an area, optionally excluding one user (e.g. the creator). */
    public function sendToAreaMembers(string $areaId, ?string $excludeUserId, string $title, string $body): void
    {
        $sql = 'SELECT DISTINCT ps.endpoint, ps.p256dh, ps.auth
                FROM push_subscriptions ps
                JOIN work_area_members wam ON wam.user_id = ps.user_id
                WHERE wam.work_area_id = ? AND wam.status = "active"';
        $params = [$areaId];
        if ($excludeUserId) {
            $sql    .= ' AND ps.user_id != ?';
            $params[] = $excludeUserId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $sub) {
            $this->deliver($sub, $title, $body);
        }
    }

    // ── Delivery ──────────────────────────────────────────────────────────────

    private function deliver(array $sub, string $title, string $body): void
    {
        try {
            $payload = json_encode(['title' => $title, 'body' => $body], JSON_UNESCAPED_UNICODE);

            [$ciphertext, $senderPub, $salt] = $this->encrypt($payload, $sub['p256dh'], $sub['auth']);

            // aes128gcm record: salt || rs(4) || idlen(1) || sender_pub(65) || ciphertext+tag
            $record = $salt
                . pack('N', 4096)
                . chr(65)
                . $senderPub
                . $ciphertext;

            $this->post($sub['endpoint'], $record, $this->vapidHeader($sub['endpoint']));
        } catch (\Throwable $e) {
            error_log('[PushService] deliver failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    // ── Encryption (RFC 8291 / aes128gcm) ────────────────────────────────────

    private function encrypt(string $plaintext, string $p256dhB64, string $authB64): array
    {
        $receiverPub = $this->b64d($p256dhB64); // 65-byte uncompressed EC point
        $authSecret  = $this->b64d($authB64);   // 16-byte auth secret

        // Ephemeral sender key pair
        $senderKey     = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $det           = openssl_pkey_get_details($senderKey);
        $senderPub     = "\x04"
                       . str_pad($det['ec']['x'], 32, "\x00", STR_PAD_LEFT)
                       . str_pad($det['ec']['y'], 32, "\x00", STR_PAD_LEFT);

        // ECDH shared secret
        $receiverKeyObj = $this->ecPublicKeyFromBytes($receiverPub);
        $sharedSecret   = openssl_pkey_derive($receiverKeyObj, $senderKey);

        // IKM: HKDF-Extract(salt=auth_secret, ikm=shared_secret) then Expand
        $prk      = hash_hmac('sha256', $sharedSecret, $authSecret, true);
        $ikmInfo  = "WebPush: info\x00" . $receiverPub . $senderPub;
        $ikm      = substr(hash_hmac('sha256', $ikmInfo . "\x01", $prk, true), 0, 32);

        $salt  = random_bytes(16);
        $cek   = $this->hkdf($salt, $ikm, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = $this->hkdf($salt, $ikm, "Content-Encoding: nonce\x00", 12);

        // AES-128-GCM: append 0x02 record delimiter (no padding)
        $tag        = '';
        $ciphertext = openssl_encrypt(
            $plaintext . "\x02",
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );

        return [$ciphertext . $tag, $senderPub, $salt];
    }

    private function hkdf(string $salt, string $ikm, string $info, int $len): string
    {
        $prk = hash_hmac('sha256', $ikm, $salt, true); // Extract
        $t   = '';
        $okm = '';
        for ($i = 1; strlen($okm) < $len; $i++) {
            $t    = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
            $okm .= $t;
        }
        return substr($okm, 0, $len);
    }

    private function ecPublicKeyFromBytes(string $keyBytes): \OpenSSLAsymmetricKey
    {
        // DER SubjectPublicKeyInfo for P-256
        $algId  = "\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"   // OID ecPublicKey
                . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";       // OID prime256v1
        $bitStr = "\x03\x42\x00" . $keyBytes;
        $inner  = $algId . $bitStr;
        $der    = "\x30" . chr(strlen($inner)) . $inner;
        $pem    = "-----BEGIN PUBLIC KEY-----\n"
                . chunk_split(base64_encode($der), 64, "\n")
                . "-----END PUBLIC KEY-----\n";
        return openssl_pkey_get_public($pem);
    }

    // ── VAPID (RFC 8292) ──────────────────────────────────────────────────────

    private function vapidHeader(string $endpoint): string
    {
        $parts   = parse_url($endpoint);
        $audience = $parts['scheme'] . '://' . $parts['host'];
        $header  = $this->b64e(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = $this->b64e(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => $this->subject,
        ]));
        $unsigned = "$header.$payload";
        openssl_sign($unsigned, $derSig, $this->privateKeyPem, OPENSSL_ALGO_SHA256);
        $jwt = "$unsigned." . $this->b64e($this->derToRaw64($derSig));
        return "vapid t=$jwt,k={$this->publicKey}";
    }

    private function derToRaw64(string $der): string
    {
        // Parse DER SEQUENCE { INTEGER r, INTEGER s } → 64-byte raw r||s
        $pos = 2;
        if (ord($der[1]) === 0x81) $pos = 3; // 2-byte length
        $pos++;                               // skip INTEGER tag for r
        $rLen = ord($der[$pos++]);
        $r    = substr($der, $pos, $rLen);
        $pos += $rLen;
        $pos++;                               // skip INTEGER tag for s
        $sLen = ord($der[$pos++]);
        $s    = substr($der, $pos, $sLen);
        return str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT)
             . str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    }

    // ── HTTP ─────────────────────────────────────────────────────────────────

    private function post(string $url, string $body, string $authorization): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => [
                "Authorization: $authorization",
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: 86400',
                'Content-Length: ' . strlen($body),
            ],
        ]);
        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);
        error_log('[PushService] POST ' . substr($url, 0, 60) . ' → HTTP ' . $status . ($curlErr ? " cURL error: $curlErr" : '') . ($response ? " body: $response" : ''));
        // 410 Gone = subscription expired; clean it up
        if ($status === 410) {
            $this->db->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?')->execute([$url]);
        }
    }

    // ── Base64url ─────────────────────────────────────────────────────────────

    private function b64e(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function b64d(string $data): string
    {
        $pad = (4 - strlen($data) % 4) % 4;
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', $pad));
    }
}
