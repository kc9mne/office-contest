<?php
declare(strict_types=1);

/**
 * Secrets saved from Admin (like the OpenAI key) are encrypted before they go in
 * the database. The encryption key is a file in storage/, outside the web root,
 * so a database dump alone doesn't reveal them.
 */
function secret_box_key(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $file = APP_ROOT . '/storage/app.key';
    if (is_readable($file)) {
        $decoded = base64_decode(trim((string) file_get_contents($file)), true);
        if ($decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return $key = $decoded;
        }
    }
    $key = sodium_crypto_secretbox_keygen();
    if (@file_put_contents($file, base64_encode($key) . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not create storage/app.key. Check that the storage folder is writable.');
    }
    @chmod($file, 0600);
    return $key;
}

function encrypt_secret(string $plain): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, secret_box_key()));
}

/** Returns null if the value can't be decrypted (for example storage/app.key was replaced). */
function decrypt_secret(string $stored): ?string
{
    $raw = base64_decode($stored, true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return null;
    }
    $plain = sodium_crypto_secretbox_open(
        substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        secret_box_key()
    );
    return $plain === false ? null : $plain;
}

/** "sk-…AbCd" for showing a saved key without revealing it. */
function mask_secret(string $secret): string
{
    return strlen($secret) <= 8 ? '••••' : substr($secret, 0, 3) . '…' . substr($secret, -4);
}
