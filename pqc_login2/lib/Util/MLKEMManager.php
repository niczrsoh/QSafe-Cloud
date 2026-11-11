<?php
namespace OCA\PQCLogin\Util;

class MLKEMManager {
    public const ALGORITHM = 'ML-KEM-512';
    public const PUBLIC_KEY_RAW_BYTES = 800;
    public const PUBLIC_KEY_DER_BYTES = 822;
    public const CIPHERTEXT_BYTES = 768;
    public const SHARED_SECRET_BYTES = 32;

    private const SPKI_DER_PREFIX_HEX = '30820332300b06096086480165030404010382032100';

    public function isAvailable(): bool {
        $bin = $this->opensslCommand();
        if ($bin === null) {
            return false;
        }

        $output = [];
        $code = 1;
        @exec($bin . ' list -kem-algorithms 2>/dev/null', $output, $code);
        if ($code !== 0) {
            return false;
        }
        $text = implode("\n", $output);
        return stripos($text, self::ALGORITHM) !== false || stripos($text, 'MLKEM512') !== false;
    }

    public function normalizePublicKeyB64(string $publicKeyB64): ?string {
        $clean = preg_replace('/\s+/', '', $publicKeyB64);
        if ($clean === null || $clean === '') {
            return null;
        }
        $bytes = base64_decode($clean, true);
        if ($bytes === false) {
            return null;
        }

        if (strlen($bytes) === self::PUBLIC_KEY_RAW_BYTES) {
            return base64_encode($bytes);
        }

        if (strlen($bytes) === self::PUBLIC_KEY_DER_BYTES) {
            $raw = $this->rawPublicKeyFromDer($bytes);
            return $raw === null ? null : base64_encode($raw);
        }

        return null;
    }

    public function deviceIdFromPublicKeyB64(string $publicKeyB64): ?string {
        $normalized = $this->normalizePublicKeyB64($publicKeyB64);
        if ($normalized === null) {
            return null;
        }
        $raw = base64_decode($normalized, true);
        if ($raw === false || strlen($raw) !== self::PUBLIC_KEY_RAW_BYTES) {
            return null;
        }
        return hash('sha256', $raw);
    }

    public function encapsulate(string $publicKeyB64): array {
        $normalized = $this->normalizePublicKeyB64($publicKeyB64);
        if ($normalized === null) {
            throw new \RuntimeException('Invalid ML-KEM-512 public key. Expected 800-byte raw PQCMicro public key as Base64.');
        }
        $rawPublicKey = base64_decode($normalized, true);
        if ($rawPublicKey === false || strlen($rawPublicKey) !== self::PUBLIC_KEY_RAW_BYTES) {
            throw new \RuntimeException('Invalid ML-KEM-512 public key encoding.');
        }

        $tmpBase = tempnam(sys_get_temp_dir(), 'pqc_mlkem_');
        if ($tmpBase === false) {
            throw new \RuntimeException('Unable to create temporary ML-KEM files.');
        }

        $pubFile = $tmpBase . '.pub.der';
        $ctFile = $tmpBase . '.ct.bin';
        $ssFile = $tmpBase . '.ss.bin';

        try {
            file_put_contents($pubFile, $this->derFromRawPublicKey($rawPublicKey));

            $cmd = sprintf(
                '%s pkeyutl -encap -inkey %s -pubin -keyform DER -secret %s -out %s 2>&1',
                $this->opensslCommand() ?? 'openssl',
                escapeshellarg($pubFile),
                escapeshellarg($ssFile),
                escapeshellarg($ctFile)
            );
            $output = [];
            $code = 1;
            exec($cmd, $output, $code);
            if ($code !== 0) {
                throw new \RuntimeException('ML-KEM encapsulation failed: ' . trim(implode("\n", $output)));
            }

            $ciphertext = file_get_contents($ctFile);
            $sharedSecret = file_get_contents($ssFile);
            if ($ciphertext === false || strlen($ciphertext) !== self::CIPHERTEXT_BYTES) {
                throw new \RuntimeException('ML-KEM ciphertext size mismatch.');
            }
            if ($sharedSecret === false || strlen($sharedSecret) !== self::SHARED_SECRET_BYTES) {
                throw new \RuntimeException('ML-KEM shared secret size mismatch.');
            }

            return [
                'ciphertext_b64' => base64_encode($ciphertext),
                'ciphertext_hex_preview' => substr(bin2hex($ciphertext), 0, 96),
                'shared_secret_hex' => bin2hex($sharedSecret),
                'shared_secret_preview' => substr(bin2hex($sharedSecret), 0, 16) . '...',
                'public_key_b64' => $normalized,
                'public_key_raw_bytes' => self::PUBLIC_KEY_RAW_BYTES,
                'public_key_der_bytes' => self::PUBLIC_KEY_DER_BYTES,
                'ciphertext_bytes' => self::CIPHERTEXT_BYTES,
                'shared_secret_bytes' => self::SHARED_SECRET_BYTES,
            ];
        } finally {
            @unlink($tmpBase);
            @unlink($pubFile);
            @unlink($ctFile);
            @unlink($ssFile);
        }
    }

    public function computeProof(string $sharedSecretHex, string $challenge, string $username, string $deviceId): string {
        $sharedSecret = hex2bin($sharedSecretHex);
        if ($sharedSecret === false || strlen($sharedSecret) !== self::SHARED_SECRET_BYTES) {
            return '';
        }
        return hash('sha256', $sharedSecret . $challenge . strtolower($username) . strtolower($deviceId));
    }

    private function opensslCommand(): ?string {
        $hostLoader = '/var/lib/snapd/hostfs/lib/ld-linux-aarch64.so.1';
        $hostOpenSsl = '/var/lib/snapd/hostfs/usr/bin/openssl';
        $hostLibPath = '/var/lib/snapd/hostfs/lib/aarch64-linux-gnu:/var/lib/snapd/hostfs/usr/lib/aarch64-linux-gnu';

        $candidates = [];
        if (is_file($hostLoader) && is_file($hostOpenSsl)) {
            $candidates[] = escapeshellarg($hostLoader)
                . ' --library-path ' . escapeshellarg($hostLibPath)
                . ' ' . escapeshellarg($hostOpenSsl);
        }
        $candidates[] = 'openssl';

        foreach ($candidates as $cmd) {
            $output = [];
            $code = 1;
            @exec($cmd . ' pkeyutl -help 2>&1', $output, $code);
            $help = implode("\n", $output);
            if ($code === 0 && strpos($help, '-encap') !== false) {
                return $cmd;
            }
        }

        return null;
    }

    private function derFromRawPublicKey(string $rawPublicKey): string {
        return hex2bin(self::SPKI_DER_PREFIX_HEX) . $rawPublicKey;
    }

    private function rawPublicKeyFromDer(string $derPublicKey): ?string {
        $prefix = hex2bin(self::SPKI_DER_PREFIX_HEX);
        if ($prefix === false || substr($derPublicKey, 0, strlen($prefix)) !== $prefix) {
            return null;
        }
        $raw = substr($derPublicKey, strlen($prefix));
        return strlen($raw) === self::PUBLIC_KEY_RAW_BYTES ? $raw : null;
    }
}
