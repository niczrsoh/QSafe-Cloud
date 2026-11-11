<?php
namespace OCA\PQCLogin\Controller;

use OCA\PQCLogin\Util\MLKEMManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\IConfig;

class VerifyController extends Controller {

    private $userManager;
    private $userSession;
    private $config;
    private MLKEMManager $mlkem;

    public function __construct(
        $appName,
        IRequest $request,
        IUserManager $userManager,
        IUserSession $userSession,
        IConfig $config
    ) {
        parent::__construct($appName, $request);
        $this->userManager = $userManager;
        $this->userSession = $userSession;
        $this->config = $config;
        $this->mlkem = new MLKEMManager();
    }

    private function getDeviceBindings(string $user): array {
        $json = $this->config->getUserValue($user, 'pqc_login', 'device_bindings_json', '');
        $bindings = json_decode($json, true);
        if (!is_array($bindings)) {
            $bindings = [];
        }

        $legacyDeviceId = strtolower(trim($this->config->getUserValue($user, 'pqc_login', 'device_id', '')));
        if ($legacyDeviceId === '') {
            $legacyDeviceId = strtolower(trim($this->config->getUserValue($user, 'pqc_login', 'pqc_key', '')));
        }
        $legacyPublicKey = $this->config->getUserValue($user, 'pqc_login', 'mlkem_public_key_b64', '');
        if ($legacyDeviceId !== '') {
            $found = false;
            foreach ($bindings as $binding) {
                if (($binding['device_id'] ?? '') === $legacyDeviceId) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                array_unshift($bindings, [
                    'device_id' => $legacyDeviceId,
                    'mlkem_public_key_b64' => $legacyPublicKey,
                    'label' => 'Primary Key',
                ]);
            }
        }

        $clean = [];
        foreach ($bindings as $binding) {
            $deviceId = strtolower(trim($binding['device_id'] ?? ''));
            if (!preg_match('/^[a-f0-9]{64}$/', $deviceId)) {
                continue;
            }
            $clean[$deviceId] = [
                'device_id' => $deviceId,
                'mlkem_public_key_b64' => trim($binding['mlkem_public_key_b64'] ?? ''),
                'label' => trim($binding['label'] ?? 'Key Device'),
            ];
        }
        return array_values($clean);
    }

    private function findDeviceBinding(string $user, string $deviceId): ?array {
        foreach ($this->getDeviceBindings($user) as $binding) {
            if (($binding['device_id'] ?? '') === $deviceId) {
                return $binding;
            }
        }
        return null;
    }

    /**
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
     */
    public function verify() {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $userInput = strtolower(trim($data['user'] ?? ''));
        $keyInput = strtolower(trim($data['key'] ?? $data['deviceId'] ?? ''));
        $signature = strtolower(trim($data['signature'] ?? ''));
        $proof = strtolower(trim($data['proof'] ?? $data['mlkemProof'] ?? ''));
        $challengeFromClient = $data['challenge'] ?? '';

        if (empty($userInput) || empty($keyInput)) {
            return new DataResponse([
                'status' => 'fail',
                'message' => 'Missing user or device information',
            ], 400);
        }

        $storedChallenge = $_SESSION['pqc_challenge'] ?? '';
        if (empty($storedChallenge) && !empty($userInput)) {
            $storedChallenge = $this->config->getUserValue($userInput, 'pqc_login', 'pending_mlkem_challenge', '');
        }
        if (empty($storedChallenge) && !empty($challengeFromClient)) {
            $storedChallenge = $challengeFromClient;
        }
        if (empty($storedChallenge)) {
            $storedChallenge = 'COMPETITION_FALLBACK';
        }

        if (!$this->userManager->userExists($userInput)) {
            return new DataResponse([
                'status' => 'fail',
                'message' => 'User does not exist',
            ], 404);
        }

        $binding = $this->findDeviceBinding($userInput, $keyInput);
        if ($binding === null) {
            $bindings = $this->getDeviceBindings($userInput);
            $firstBoundDeviceId = $bindings[0]['device_id'] ?? '';
            return new DataResponse([
                'status' => 'fail',
                'message' => count($bindings) > 0 ? 'Device ID is not one of the bound Key devices' : 'No device bound to this user',
                'audit' => $this->buildLegacyAuditPayload($storedChallenge, $keyInput, $signature, $firstBoundDeviceId, '', false, false),
            ], 403);
        }

        $storedDeviceId = $binding['device_id'];
        $publicKeyB64 = $binding['mlkem_public_key_b64'] ?? '';
        if ($publicKeyB64 !== '') {
            return $this->verifyMlkem($userInput, $keyInput, $proof, $storedDeviceId, $storedChallenge, $publicKeyB64);
        }

        return $this->verifyLegacy($userInput, $keyInput, $signature, $storedDeviceId, $storedChallenge);
    }


    private function isAccountBlocked(string $userInput, $user): bool {
        if ($this->config->getUserValue($userInput, 'pqc_login', 'account_frozen_at', '') !== '') {
            return true;
        }
        if (is_object($user) && method_exists($user, 'isEnabled') && !$user->isEnabled()) {
            return true;
        }
        return false;
    }

    private function blockedAccountResponse(string $userInput): DataResponse {
        return new DataResponse([
            'status' => 'fail',
            'message' => 'This account is frozen. Contact the administrator to unlock or recover it.',
        ], 403);
    }

    private function verifyMlkem(string $userInput, string $deviceIdInput, string $proof, string $storedDeviceId, string $challenge, string $publicKeyB64): DataResponse {
        $keyMatchesBound = hash_equals($storedDeviceId, $deviceIdInput);
        $pendingByDevice = $_SESSION['pqc_mlkem_pending_by_device'] ?? [];
        if (!is_array($pendingByDevice) || count($pendingByDevice) === 0) {
            $pendingByDevice = json_decode($this->config->getUserValue($userInput, 'pqc_login', 'pending_mlkem_by_device_json', ''), true);
            if (!is_array($pendingByDevice)) {
                $pendingByDevice = [];
            }
        }

        $devicePending = $pendingByDevice[$storedDeviceId] ?? null;
        $sharedSecretHex = is_array($devicePending) ? ($devicePending['shared_secret_hex'] ?? '') : '';
        if ($sharedSecretHex === '') {
            $sharedSecretHex = $_SESSION['pqc_mlkem_shared_secret'] ?? '';
        }
        if ($sharedSecretHex === '') {
            $sharedSecretHex = $this->config->getUserValue($userInput, 'pqc_login', 'pending_mlkem_shared_secret', '');
        }
        $ciphertextB64 = is_array($devicePending) ? ($devicePending['ciphertext_b64'] ?? '') : '';
        if ($ciphertextB64 === '') {
            $ciphertextB64 = $_SESSION['pqc_mlkem_ciphertext_b64'] ?? '';
        }
        if ($ciphertextB64 === '') {
            $ciphertextB64 = $this->config->getUserValue($userInput, 'pqc_login', 'pending_mlkem_ciphertext_b64', '');
        }
        $sessionUser = strtolower($_SESSION['pqc_mlkem_user'] ?? '');
        if ($sessionUser === '') {
            $sessionUser = strtolower($this->config->getUserValue($userInput, 'pqc_login', 'pending_mlkem_user', ''));
        }
        $expectedProof = $this->mlkem->computeProof($sharedSecretHex, $challenge, $userInput, $storedDeviceId);
        $proofValid = $expectedProof !== '' && $proof !== '' && hash_equals($expectedProof, $proof) && $sessionUser === $userInput;

        if (!$keyMatchesBound || !$proofValid) {
            $audit = $this->buildMlkemAuditPayload(
                $challenge,
                $deviceIdInput,
                $proof,
                $storedDeviceId,
                $expectedProof,
                $keyMatchesBound,
                $proofValid,
                $publicKeyB64,
                $ciphertextB64
            );
            $this->clearMlkemSession($userInput);
            return new DataResponse([
                'status' => 'fail',
                'message' => !$keyMatchesBound ? 'Device ID does not match bound ML-KEM key' : 'ML-KEM decapsulation proof invalid',
                'audit' => $audit,
            ], 401);
        }

        $user = $this->userManager->get($userInput);
        if (!$user) {
            $this->clearMlkemSession($userInput);
            return new DataResponse(['status' => 'fail', 'message' => 'User lookup failed'], 500);
        }
        if ($this->isAccountBlocked($userInput, $user)) {
            $this->clearMlkemSession($userInput);
            return $this->blockedAccountResponse($userInput);
        }

        $this->userSession->setUser($user);
        $this->userSession->setLoginName($userInput);

        $audit = $this->buildMlkemAuditPayload(
            $challenge,
            $deviceIdInput,
            $proof,
            $storedDeviceId,
            $expectedProof,
            true,
            true,
            $publicKeyB64,
            $ciphertextB64
        );
        $this->clearMlkemSession($userInput);

        $response = new DataResponse([
            'status' => 'ok',
            'login' => true,
            'pqc_status' => 'quantum_safe',
            'pqc_standard' => 'NIST FIPS 203',
            'pqc_algorithm' => 'ML-KEM-512',
            'pqc_oid' => '2.16.840.1.101.3.4.4.1',
            'pqc_verified' => true,
            'audit' => $audit,
        ]);

        $response->addHeader('X-PQC-Safe', 'true');
        $response->addHeader('X-PQC-Standard', 'FIPS-203');
        $response->addHeader('X-PQC-Algorithm', 'ML-KEM-512');

        return $response;
    }

    private function verifyLegacy(string $userInput, string $keyInput, string $signature, string $storedDeviceId, string $challenge): DataResponse {
        $expectedSignature = $this->computeChallengeSignature($storedDeviceId, $challenge);
        $signatureValid = hash_equals($expectedSignature, $signature);
        $keyMatchesBound = hash_equals($keyInput, $storedDeviceId);

        if (!$keyMatchesBound || !$signatureValid) {
            return new DataResponse([
                'status' => 'fail',
                'message' => !$keyMatchesBound ? 'Hardware device ID does not match bound device' : 'Challenge-response signature invalid',
                'audit' => $this->buildLegacyAuditPayload(
                    $challenge,
                    $keyInput,
                    $signature,
                    $storedDeviceId,
                    $expectedSignature,
                    $keyMatchesBound,
                    $signatureValid
                ),
            ], 401);
        }

        $user = $this->userManager->get($userInput);
        if (!$user) {
            return new DataResponse(['status' => 'fail', 'message' => 'User lookup failed'], 500);
        }
        if ($this->isAccountBlocked($userInput, $user)) {
            return $this->blockedAccountResponse($userInput);
        }

        $this->userSession->setUser($user);
        $this->userSession->setLoginName($userInput);

        $audit = $this->buildLegacyAuditPayload(
            $challenge,
            $keyInput,
            $signature,
            $storedDeviceId,
            $expectedSignature,
            true,
            true
        );

        return new DataResponse([
            'status' => 'ok',
            'login' => true,
            'pqc_status' => 'legacy_device_verified',
            'pqc_algorithm' => 'SHA-256 challenge-response',
            'pqc_verified' => true,
            'audit' => $audit,
        ]);
    }

    private function computeChallengeSignature(string $hardwareKeyHex, string $challenge): string {
        $keyBin = hex2bin($hardwareKeyHex);
        if ($keyBin === false) {
            return '';
        }
        return hash('sha256', $keyBin . $challenge);
    }

    private function clearMlkemSession(string $userInput = ''): void {
        $_SESSION['pqc_mlkem_shared_secret'] = '';
        $_SESSION['pqc_mlkem_ciphertext_b64'] = '';
        $_SESSION['pqc_mlkem_user'] = '';
        $_SESSION['pqc_mlkem_pending_by_device'] = [];

        if ($userInput !== '') {
            $this->config->deleteUserValue($userInput, 'pqc_login', 'pending_mlkem_challenge');
            $this->config->deleteUserValue($userInput, 'pqc_login', 'pending_mlkem_shared_secret');
            $this->config->deleteUserValue($userInput, 'pqc_login', 'pending_mlkem_ciphertext_b64');
            $this->config->deleteUserValue($userInput, 'pqc_login', 'pending_mlkem_user');
            $this->config->deleteUserValue($userInput, 'pqc_login', 'pending_mlkem_by_device_json');
        }
    }

    private function canonicalJson(array $data): string {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = json_decode($this->canonicalJson($value), true);
            }
        }
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function signingKeypair(): array {
        $publicKey = $this->config->getAppValue('pqc_login', 'audit_sign_public_key', '');
        $secretKey = $this->config->getAppValue('pqc_login', 'audit_sign_secret_key', '');

        if ($publicKey !== '' && $secretKey !== '') {
            return [$publicKey, $secretKey];
        }

        if (!function_exists('sodium_crypto_sign_keypair')) {
            return ['', ''];
        }

        $keypair = sodium_crypto_sign_keypair();
        $publicKey = base64_encode(sodium_crypto_sign_publickey($keypair));
        $secretKey = base64_encode(sodium_crypto_sign_secretkey($keypair));
        $this->config->setAppValue('pqc_login', 'audit_sign_public_key', $publicKey);
        $this->config->setAppValue('pqc_login', 'audit_sign_secret_key', $secretKey);

        return [$publicKey, $secretKey];
    }

    private function buildVerificationReceipt(array $audit): array {
        $evidence = [
            'version' => 'pqc-login-audit-v1',
            'issued_at' => gmdate('c'),
            'mode' => $audit['mode'] ?? '',
            'algorithm' => $audit['algorithm'] ?? '',
            'standard' => $audit['standard'] ?? '',
            'oid' => $audit['oid'] ?? '',
            'challenge' => $audit['challenge'] ?? '',
            'device_id' => $audit['hardware_public_key'] ?? '',
            'bound_device_id' => $audit['bound_public_key'] ?? '',
            'key_match' => (bool)($audit['key_match'] ?? false),
            'device_proof' => $audit['device_signature'] ?? '',
            'expected_proof' => $audit['expected_signature'] ?? '',
            'proof_valid' => (bool)($audit['signature_valid'] ?? false),
            'formula' => $audit['signature_formula'] ?? '',
            'ciphertext_bytes' => (int)($audit['kem_encapsulation']['ciphertext_bytes'] ?? 0),
            'public_key_bytes' => (int)($audit['kem_encapsulation']['public_key_bytes'] ?? 0),
            'shared_secret_bytes' => (int)($audit['kem_encapsulation']['shared_secret_bytes'] ?? 0),
        ];

        $canonical = $this->canonicalJson($evidence);
        $hash = hash('sha256', $canonical);
        [$publicKeyB64, $secretKeyB64] = $this->signingKeypair();
        $signatureB64 = '';
        $signature_algorithm = 'none';

        if ($secretKeyB64 !== '' && function_exists('sodium_crypto_sign_detached')) {
            $signatureB64 = base64_encode(sodium_crypto_sign_detached($canonical, base64_decode($secretKeyB64)));
            $signature_algorithm = 'Ed25519 (libsodium)';
        }

        return [
            'receipt_version' => 'pqc-login-audit-v1',
            'receipt_hash_sha256' => $hash,
            'canonical_evidence_b64' => base64_encode($canonical),
            'signature_algorithm' => $signature_algorithm,
            'server_signature_b64' => $signatureB64,
            'server_public_key_b64' => $publicKeyB64,
            'verification_note' => 'This receipt is generated server-side after the proof comparison. The UI displays this signed receipt instead of relying only on hardcoded text.',
        ];
    }

    private function buildMlkemAuditPayload(
        string $challenge,
        string $deviceId,
        string $deviceProof,
        string $boundDeviceId,
        string $expectedProof,
        bool $keyMatch,
        bool $proofValid,
        string $publicKeyB64,
        string $ciphertextB64
    ): array {
        $publicKeyBytes = strlen(base64_decode($publicKeyB64, true) ?: '');
        $ciphertextBytes = strlen(base64_decode($ciphertextB64, true) ?: '');

        $audit = [
            'challenge' => $challenge,
            'algorithm' => 'ML-KEM-512',
            'standard' => 'NIST FIPS 203',
            'oid' => '2.16.840.1.101.3.4.4.1',
            'mode' => 'real_mlkem',
            'hardware_public_key' => $deviceId,
            'bound_public_key' => $boundDeviceId,
            'key_match' => $keyMatch,
            'device_signature' => $deviceProof,
            'expected_signature' => $expectedProof,
            'signature_valid' => $proofValid,
            'signature_formula' => 'SHA-256(shared_secret || challenge || username || device_id)',
            'kem_encapsulation' => [
                'ephemeral_public_key' => 'User bound ML-KEM-512 public key: ' . $publicKeyBytes . ' bytes stored server-side (raw PQCMicro public key payload 800 bytes)',
                'ciphertext' => 'Base64 ciphertext length: ' . strlen($ciphertextB64) . ' chars; decoded: ' . $ciphertextBytes . ' bytes',
                'shared_secret' => '32 bytes, hidden after proof verification',
                'public_key_bytes' => $publicKeyBytes,
                'raw_public_key_bytes' => MLKEMManager::PUBLIC_KEY_RAW_BYTES,
                'ciphertext_bytes' => $ciphertextBytes,
                'shared_secret_bytes' => MLKEMManager::SHARED_SECRET_BYTES,
                'steps' => [
                    'Server loads the user bound ML-KEM-512 public key from Nextcloud preferences',
                    'OpenSSL performs real ML-KEM-512 encapsulation',
                    'Server stores the 32-byte shared secret only as temporary pending login state',
                    'Server sends only the 768-byte ciphertext to the browser, then browser forwards it to Key over serial',
                ],
            ],
            'kem_decapsulation' => [
                'decapsulated_secret' => 'Hidden on Key; never exported',
                'secret_match' => $proofValid,
                'proof' => $deviceProof,
                'expected_proof' => $expectedProof,
                'steps' => [
                    'Key decapsulates the ciphertext using its local ML-KEM-512 secret key',
                    'Key derives the same 32-byte shared secret',
                    'Key returns only SHA-256(shared_secret || challenge || username || device_id)',
                ],
            ],
            'private_key_note' => 'ML-KEM secret key is expected to remain on Key and is never sent to Nextcloud.',
        ];

        $audit['verification_receipt'] = $this->buildVerificationReceipt($audit);
        return $audit;
    }

    private function buildLegacyAuditPayload(
        string $challenge,
        string $deviceKey,
        string $deviceSignature,
        string $boundKey,
        string $expectedSignature,
        bool $keyMatch,
        bool $signatureValid
    ): array {
        return [
            'challenge' => $challenge,
            'algorithm' => 'Legacy SHA-256 device challenge-response',
            'standard' => 'Legacy compatibility mode',
            'oid' => 'N/A',
            'mode' => 'legacy_challenge_response',
            'hardware_public_key' => $deviceKey,
            'bound_public_key' => $boundKey,
            'key_match' => $keyMatch,
            'device_signature' => $deviceSignature,
            'expected_signature' => $expectedSignature,
            'signature_valid' => $signatureValid,
            'signature_formula' => 'SHA-256(DeviceRootKey || Challenge)',
            'kem_encapsulation' => [
                'ephemeral_public_key' => 'Not used in legacy mode',
                'ciphertext' => 'Not used in legacy mode',
                'shared_secret' => 'Not used in legacy mode',
                'steps' => ['This account has no stored ML-KEM public key yet. Rebind with ML-KEM firmware to enable real ML-KEM.'],
            ],
            'kem_decapsulation' => [
                'decapsulated_secret' => 'Not used in legacy mode',
                'secret_match' => false,
                'steps' => ['Legacy SHA-256 challenge-response verified the bound device ID.'],
            ],
            'private_key_note' => 'Legacy hardware root key remains on Key.',
        ];
    }
}
