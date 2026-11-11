<?php
namespace OCA\PQCLogin\Controller;

use OCA\PQCLogin\Util\MLKEMManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserManager;

class ChallengeController extends Controller {

    private IConfig $config;
    private MLKEMManager $mlkem;
    private IUserManager $userManager;

    public function __construct(string $appName, IRequest $request, IConfig $config, IUserManager $userManager) {
        parent::__construct($appName, $request);
        $this->config = $config;
        $this->userManager = $userManager;
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
                    'bound_at' => time(),
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

    #[NoAdminRequired]
    #[PublicPage]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/challenge')]
    public function getChallenge(): DataResponse {
        $challenge = 'COMPETITION_CHALLENGE_' . bin2hex(random_bytes(16));
        $user = strtolower(trim((string)$this->request->getParam('user', '')));

        $_SESSION['pqc_challenge'] = $challenge;
        $_SESSION['pqc_mlkem_shared_secret'] = '';
        $_SESSION['pqc_mlkem_ciphertext_b64'] = '';
        $_SESSION['pqc_mlkem_user'] = '';

        $response = [
            'challenge' => $challenge,
            'mode' => 'legacy-challenge-response',
            'pqc_mode' => 'legacy-challenge-response',
            'pqc_algorithm' => 'SHA-256 challenge-response',
            'status' => 'ready',
        ];

        if ($user !== '') {
            $account = $this->userManager->get($user);
            if ($this->config->getUserValue($user, 'pqc_login', 'account_frozen_at', '') !== '' || ($account && method_exists($account, 'isEnabled') && !$account->isEnabled())) {
                return new DataResponse(array_merge($response, [
                    'status' => 'account_frozen',
                    'message' => 'This account is frozen. Contact the administrator to unlock or recover it.',
                ]), 403);
            }

            $bindings = $this->getDeviceBindings($user);
            $mlkemDevices = [];
            $pendingSecrets = [];
            $firstEncapsulation = null;

            foreach ($bindings as $binding) {
                $publicKeyB64 = $binding['mlkem_public_key_b64'] ?? '';
                $deviceId = strtolower($binding['device_id'] ?? '');
                if ($publicKeyB64 === '' || $deviceId === '') {
                    continue;
                }

                try {
                    $encapsulation = $this->mlkem->encapsulate($publicKeyB64);
                    if ($firstEncapsulation === null) {
                        $firstEncapsulation = $encapsulation;
                    }
                    $pendingSecrets[$deviceId] = [
                        'shared_secret_hex' => $encapsulation['shared_secret_hex'],
                        'ciphertext_b64' => $encapsulation['ciphertext_b64'],
                        'public_key_b64' => $publicKeyB64,
                    ];
                    $mlkemDevices[] = [
                        'device_id' => $deviceId,
                        'label' => $binding['label'] ?? 'Key Device',
                        'ciphertext_b64' => $encapsulation['ciphertext_b64'],
                        'public_key_der_bytes' => $encapsulation['public_key_der_bytes'],
                        'raw_public_key_bytes' => $encapsulation['public_key_raw_bytes'],
                        'ciphertext_bytes' => $encapsulation['ciphertext_bytes'],
                        'shared_secret_bytes' => $encapsulation['shared_secret_bytes'],
                    ];
                } catch (\Throwable $e) {
                    $response['status'] = 'mlkem_unavailable';
                    $response['message'] = $e->getMessage();
                    break;
                }
            }

            if (count($mlkemDevices) > 0 && $firstEncapsulation !== null) {
                $primary = $mlkemDevices[0];
                $_SESSION['pqc_mlkem_shared_secret'] = $pendingSecrets[$primary['device_id']]['shared_secret_hex'];
                $_SESSION['pqc_mlkem_ciphertext_b64'] = $primary['ciphertext_b64'];
                $_SESSION['pqc_mlkem_user'] = $user;
                $_SESSION['pqc_mlkem_pending_by_device'] = $pendingSecrets;

                $this->config->setUserValue($user, 'pqc_login', 'pending_mlkem_challenge', $challenge);
                $this->config->setUserValue($user, 'pqc_login', 'pending_mlkem_shared_secret', $pendingSecrets[$primary['device_id']]['shared_secret_hex']);
                $this->config->setUserValue($user, 'pqc_login', 'pending_mlkem_ciphertext_b64', $primary['ciphertext_b64']);
                $this->config->setUserValue($user, 'pqc_login', 'pending_mlkem_user', $user);
                $this->config->setUserValue($user, 'pqc_login', 'pending_mlkem_by_device_json', json_encode($pendingSecrets));

                $response = array_merge($response, [
                    'mode' => 'mlkem512',
                    'pqc_mode' => 'mlkem512',
                    'pqc_algorithm' => 'ML-KEM-512',
                    'pqc_oid' => '2.16.840.1.101.3.4.4.1',
                    'device_id' => $primary['device_id'],
                    'ciphertext_b64' => $primary['ciphertext_b64'],
                    'mlkem_devices' => $mlkemDevices,
                    'device_count' => count($mlkemDevices),
                    'public_key_der_bytes' => $primary['public_key_der_bytes'],
                    'raw_public_key_bytes' => $primary['raw_public_key_bytes'],
                    'ciphertext_bytes' => $primary['ciphertext_bytes'],
                    'shared_secret_bytes' => $primary['shared_secret_bytes'],
                ]);
            }
        }

        return new DataResponse($response);
    }
}
