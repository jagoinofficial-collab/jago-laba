<?php
declare(strict_types=1);

final class MarketplaceTokenVault
{
    private const KEY_BYTES = 32;

    public static function isConfigured(): bool
    {
        try {
            self::key();
            return function_exists('sodium_crypto_secretbox') || function_exists('openssl_encrypt');
        } catch (Throwable) {
            return false;
        }
    }

    public static function encrypt(string $plaintext): string
    {
        $key = self::key();
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
            return self::base64UrlEncode('s' . $nonce . $ciphertext);
        }
        if (!function_exists('openssl_encrypt')) {
            throw new LogicException('Server tidak memiliki primitive enkripsi token yang aman.');
        }
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new LogicException('Token tidak dapat dienkripsi dengan aman.');
        }
        return self::base64UrlEncode('o' . $nonce . $tag . $ciphertext);
    }

    public static function decrypt(string $encoded): string
    {
        $payload = self::base64UrlDecode($encoded);
        if ($payload === '') {
            throw new RuntimeException('Token terenkripsi tidak valid.');
        }

        $format = $payload[0];
        if ($format === 's' && function_exists('sodium_crypto_secretbox_open')) {
            $nonceBytes = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            $plaintext = sodium_crypto_secretbox_open(substr($payload, 1 + $nonceBytes), substr($payload, 1, $nonceBytes), self::key());
            if ($plaintext === false) throw new RuntimeException('Token terenkripsi tidak dapat diverifikasi.');
            return $plaintext;
        }
        if ($format !== 'o' || !function_exists('openssl_decrypt') || strlen($payload) <= 1 + 12 + 16) {
            throw new RuntimeException('Token terenkripsi tidak valid.');
        }
        $plaintext = openssl_decrypt(substr($payload, 1 + 12 + 16), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($payload, 1, 12), substr($payload, 1 + 12, 16));
        if ($plaintext === false) throw new RuntimeException('Token terenkripsi tidak dapat diverifikasi.');
        return $plaintext;
    }

    private static function key(): string
    {
        $raw = trim((string)(getenv('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY') ?: ''));
        if ($raw === '') {
            $keyFile = trim((string)(getenv('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY_FILE') ?: ''));
            if ($keyFile === '' && PHP_OS_FAMILY === 'Windows') {
                $keyFile = 'D:/xampp/apache/conf/extra/jago-laba-marketplace.key';
            }
            if ($keyFile !== '' && is_file($keyFile) && is_readable($keyFile)) {
                $contents = file_get_contents($keyFile);
                $raw = $contents === false ? '' : trim($contents);
            }
        }
        if ($raw === '') {
            throw new LogicException('Kunci enkripsi marketplace belum tersedia di environment atau file kunci server.');
        }

        $decoded = base64_decode($raw, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $decoded;
        }
        if (strlen($raw) === 32) {
            return $raw;
        }
        throw new LogicException('JL_MARKETPLACE_TOKEN_ENCRYPTION_KEY harus berupa base64 32-byte key.');
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value . str_repeat('=', (4 - strlen($value) % 4) % 4), '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Token terenkripsi tidak valid.');
        }
        return $decoded;
    }
}
