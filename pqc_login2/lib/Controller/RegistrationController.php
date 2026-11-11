<?php
namespace OCA\PQCLogin\Controller;

use OCA\PQCLogin\Util\MRSManager;
use OCA\PQCLogin\Util\MLKEMManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Util;

class RegistrationController extends Controller {

    private IUserManager $userManager;
    private IUserSession $userSession;
    private IConfig $config;
    private MRSManager $mrsManager;
    private ISession $session;
    private MLKEMManager $mlkem;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserManager $userManager,
        IUserSession $userSession,
        IConfig $config,
        MRSManager $mrsManager,
        ISession $session
    ) {
        parent::__construct($appName, $request);
        $this->userManager = $userManager;
        $this->userSession = $userSession;
        $this->config = $config;
        $this->mrsManager = $mrsManager;
        $this->session = $session;
        $this->mlkem = new MLKEMManager();
    }


    private function normalizeEmail(string $email): string {
        return strtolower(trim($email));
    }

    private function resolveUserIdByEmail(string $email): ?string {
        $email = $this->normalizeEmail($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $matches = $this->userManager->getByEmail($email);
        if (count($matches) !== 1) {
            return null;
        }

        $account = $matches[0];
        return method_exists($account, 'getUID') ? $account->getUID() : null;
    }

    private function countUsersByEmail(string $email): int {
        $email = $this->normalizeEmail($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 0;
        }
        return count($this->userManager->getByEmail($email));
    }

    private function resolveAccountInput(string $input): string {
        $input = strtolower(trim($input));
        if ($input === '') {
            return '';
        }
        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            return $this->resolveUserIdByEmail($input) ?: $input;
        }
        return $input;
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
                    'bound_at' => (int)$this->config->getUserValue($user, 'pqc_login', 'mlkem_key_updated_at', (string)time()),
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
                'bound_at' => (int)($binding['bound_at'] ?? time()),
            ];
        }

        return array_values($clean);
    }

    private function saveDeviceBindings(string $user, array $bindings): void {
        $bindings = array_slice(array_values($bindings), 0, 2);
        $this->config->setUserValue($user, 'pqc_login', 'device_bindings_json', json_encode($bindings));

        $primary = $bindings[0] ?? null;
        if ($primary === null) {
            $this->config->deleteUserValue($user, 'pqc_login', 'pqc_key');
            $this->config->deleteUserValue($user, 'pqc_login', 'device_id');
            $this->config->deleteUserValue($user, 'pqc_login', 'mlkem_public_key_b64');
            return;
        }

        $this->config->setUserValue($user, 'pqc_login', 'pqc_key', $primary['device_id']);
        $this->config->setUserValue($user, 'pqc_login', 'device_id', $primary['device_id']);
        if (($primary['mlkem_public_key_b64'] ?? '') !== '') {
            $this->config->setUserValue($user, 'pqc_login', 'mlkem_public_key_b64', $primary['mlkem_public_key_b64']);
        }
    }

    private function normalizeDevicePayloads(array $data): array {
        $items = [];
        if (isset($data['devices']) && is_array($data['devices'])) {
            $items = $data['devices'];
        } else {
            $items[] = [
                'pqcKey' => $data['pqcKey'] ?? '',
                'mlkemPublicKeyB64' => $data['mlkemPublicKeyB64'] ?? '',
            ];
        }

        $devices = [];
        foreach ($items as $item) {
            $pqcKey = strtolower(trim($item['pqcKey'] ?? $item['deviceId'] ?? ''));
            $mlkemPublicKeyB64 = trim($item['mlkemPublicKeyB64'] ?? '');
            $normalizedMlkemPublicKey = null;
            if ($mlkemPublicKeyB64 !== '') {
                $normalizedMlkemPublicKey = $this->mlkem->normalizePublicKeyB64($mlkemPublicKeyB64);
                if ($normalizedMlkemPublicKey === null) {
                    throw new \InvalidArgumentException('Invalid ML-KEM-512 public key format (expected 800-byte raw PQCMicro public key as Base64)');
                }
                $pqcKey = $this->mlkem->deviceIdFromPublicKeyB64($normalizedMlkemPublicKey) ?? '';
            }
            if ($pqcKey === '') {
                continue;
            }
            if (!preg_match('/^[a-f0-9]{64}$/', $pqcKey)) {
                throw new \InvalidArgumentException('Invalid device ID format (must be 64 hex characters)');
            }
            $devices[$pqcKey] = [
                'device_id' => $pqcKey,
                'mlkem_public_key_b64' => $normalizedMlkemPublicKey,
            ];
        }

        return array_slice(array_values($devices), 0, 2);
    }

    #[NoAdminRequired]
    #[PublicPage]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/register')]
    public function index(): TemplateResponse {
        Util::addScript('pqc_login', 'pqc-branding');

        if ($this->request->getParam('mode') === 'recovery') {
            Util::addScript('pqc_login', 'pqc-serial');
            Util::addScript('pqc_login', 'pqc-recovery');
            return new TemplateResponse('pqc_login', 'recovery', [], 'guest');
        }

        Util::addScript('pqc_login', 'pqc-serial');
        Util::addScript('pqc_login', 'pqc-register');
        return new TemplateResponse('pqc_login', 'register', [], 'guest');
    }

    #[NoAdminRequired]
    #[PublicPage]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'POST', url: '/register')]
    public function register(): DataResponse {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $action = $data['action'] ?? '';

            if ($action === 'check-credentials') {
                return $this->checkCredentials($data);
            }
            if ($action === 'kba-question') {
                return $this->getKbaQuestion($data);
            }
            if ($action === 'setup-kba') {
                return $this->setupKba($data);
            }
            if ($action === 'recovery-verify') {
                return $this->recoveryVerify($data);
            }
            if ($action === 'recovery-rebind') {
                return $this->recoveryRebind($data);
            }
            if ($action === 'recovery-freeze') {
                return $this->recoveryFreeze();
            }

            $user = strtolower(trim($data['user'] ?? ''));
            $email = $this->normalizeEmail($data['email'] ?? '');
            $password = $data['password'] ?? '';
            $displayName = trim($data['displayName'] ?? '');

            if ($user === '' || $email === '' || $password === '') {
                return new DataResponse([
                    'status' => 'error',
                    'message' => 'Username, email, and password are required',
                ], 400);
            }
            if (!preg_match('/^[a-z0-9_.@-]{3,64}$/', $user)) {
                return new DataResponse([
                    'status' => 'error',
                    'message' => 'Username must be 3-64 characters using letters, numbers, dot, dash, underscore, or @',
                ], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return new DataResponse([
                    'status' => 'error',
                    'message' => 'Please enter a valid email address',
                ], 400);
            }

            try {
                $devices = $this->normalizeDevicePayloads($data);
            } catch (\InvalidArgumentException $e) {
                return new DataResponse([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 400);
            }
            if (count($devices) < 1) {
                return new DataResponse([
                    'status' => 'error',
                    'message' => 'Please bind at least the primary Key device',
                ], 400);
            }

            if ($this->userManager->userExists($user)) {
                $existingUser = $this->userManager->checkPassword($user, $password);
                if ($existingUser === false) {
                    return new DataResponse([
                        'status' => 'error',
                        'message' => 'User already exists but password is incorrect',
                    ], 403);
                }

                foreach ($devices as $device) {
                    $this->storeDeviceBinding($user, $device['device_id'], $device['mlkem_public_key_b64']);
                }
                $sssData = $this->mrsManager->initializeForUser($user);
                $this->session->set('pqc_pending_kba_user', $user);

                return new DataResponse([
                    'status' => 'success',
                    'message' => 'PQC key bound to existing account',
                    'user' => $user,
                    'sss' => $sssData,
                    'device_count' => count($this->getDeviceBindings($user))
                ]);
            }

            if ($this->countUsersByEmail($email) > 0) {
                return new DataResponse([
                    'status' => 'error',
                    'message' => 'This email is already registered. Please login or use recovery.',
                ], 409);
            }

            $newUser = $this->userManager->createUser($user, $password);
            if ($newUser === false) {
                throw new \Exception('Failed to create user');
            }

            if ($displayName !== '') {
                $newUser->setDisplayName($displayName);
            }
            if ($email !== '') {
                $newUser->setEMailAddress($email);
            }

            foreach ($devices as $device) {
                $this->storeDeviceBinding($user, $device['device_id'], $device['mlkem_public_key_b64']);
            }
            $sssData = $this->mrsManager->initializeForUser($user);
            $this->session->set('pqc_pending_kba_user', $user);

            return new DataResponse([
                'status' => 'success',
                'message' => 'User created and PQC key bound successfully',
                'user' => $user,
                'sss' => $sssData,
                'device_count' => count($this->getDeviceBindings($user))
            ]);
        } catch (\Exception $e) {
            return new DataResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    private function normalizeKbaAnswer(string $answer): string {
        $answer = strtolower(trim($answer));
        $answer = preg_replace('/\s+/', ' ', $answer);
        return $answer === null ? '' : $answer;
    }

    private function storeKbaSet(string $user, array $items): void {
        $clean = [];
        foreach ($items as $item) {
            $question = trim($item['question'] ?? '');
            $answer = $this->normalizeKbaAnswer($item['answer'] ?? '');
            if ($question === '' || $answer === '') {
                continue;
            }
            $clean[] = [
                'question' => $question,
                'answer_hash' => password_hash($answer, PASSWORD_DEFAULT),
            ];
        }

        if (count($clean) < 3) {
            throw new \Exception('Please set all 3 recovery questions and answers');
        }
        $uniqueQuestions = [];
        foreach ($clean as $item) {
            $uniqueQuestions[strtolower($item['question'])] = true;
        }
        if (count($uniqueQuestions) < 3) {
            throw new \Exception('Please choose 3 different recovery questions');
        }

        $this->config->setUserValue($user, 'pqc_login', 'kba_questions_json', json_encode(array_slice($clean, 0, 3)));
        $this->config->setUserValue($user, 'pqc_login', 'kba_required_correct', '2');
        $this->config->setUserValue($user, 'pqc_login', 'kba_updated_at', (string)time());

        $this->config->setUserValue($user, 'pqc_login', 'kba_question', $clean[0]['question']);
        $this->config->setUserValue($user, 'pqc_login', 'kba_answer_hash', $clean[0]['answer_hash']);
    }

    private function getKbaSet(string $user): array {
        $json = $this->config->getUserValue($user, 'pqc_login', 'kba_questions_json', '');
        $items = json_decode($json, true);
        if (is_array($items) && count($items) > 0) {
            return $items;
        }

        $legacyQuestion = $this->config->getUserValue($user, 'pqc_login', 'kba_question', '');
        $legacyHash = $this->config->getUserValue($user, 'pqc_login', 'kba_answer_hash', '');
        if ($legacyQuestion !== '' && $legacyHash !== '') {
            return [[
                'question' => $legacyQuestion,
                'answer_hash' => $legacyHash,
            ]];
        }

        return [];
    }

    private function countCorrectKbaAnswers(string $user, array $answers): int {
        $items = $this->getKbaSet($user);
        $correct = 0;
        foreach ($items as $index => $item) {
            $answer = $this->normalizeKbaAnswer($answers[$index] ?? '');
            $hash = $item['answer_hash'] ?? '';
            if ($answer !== '' && $hash !== '' && password_verify($answer, $hash)) {
                $correct++;
            }
        }
        return $correct;
    }

    private function getKbaQuestion(array $data): DataResponse {
        $user = $this->resolveAccountInput($data['user'] ?? '');
        if ($user === '' || !$this->userManager->userExists($user)) {
            return new DataResponse(['status' => 'error', 'message' => 'User not found'], 404);
        }

        $items = $this->getKbaSet($user);
        if (count($items) === 0) {
            return new DataResponse(['status' => 'error', 'message' => 'No security question is registered for this user'], 404);
        }

        $questions = array_map(static fn($item) => $item['question'] ?? '', $items);
        return new DataResponse([
            'status' => 'success',
            'question' => $questions[0] ?? '',
            'questions' => $questions,
            'requiredCorrect' => count($questions) >= 3 ? 2 : 1,
        ]);
    }


    private function setupKba(array $data): DataResponse {
        $user = strtolower(trim($data['user'] ?? ''));
        $pendingUser = $this->session->get('pqc_pending_kba_user');

        $items = [];
        if (isset($data['kbaItems']) && is_array($data['kbaItems'])) {
            $items = $data['kbaItems'];
        } else {
            $items[] = [
                'question' => trim($data['kbaQuestion'] ?? ''),
                'answer' => $data['kbaAnswer'] ?? '',
            ];
        }

        if ($user === '') {
            return new DataResponse(['status' => 'error', 'message' => 'User is required'], 400);
        }
        if ($pendingUser !== $user) {
            return new DataResponse(['status' => 'error', 'message' => 'KBA setup session expired. Please register again.'], 403);
        }
        if (!$this->userManager->userExists($user)) {
            return new DataResponse(['status' => 'error', 'message' => 'User not found'], 404);
        }

        try {
            $this->storeKbaSet($user, $items);
        } catch (\Exception $e) {
            return new DataResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
        $this->session->remove('pqc_pending_kba_user');

        return new DataResponse(['status' => 'success', 'message' => 'Knowledge-based recovery saved. Recovery will require 2 correct answers out of 3.']);
    }

    private function storeDeviceBinding(string $user, string $deviceId, ?string $mlkemPublicKeyB64, bool $replaceAll = false): void {
        $bindings = $replaceAll ? [] : $this->getDeviceBindings($user);
        $deviceId = strtolower(trim($deviceId));
        $updated = false;

        foreach ($bindings as &$binding) {
            if (($binding['device_id'] ?? '') === $deviceId) {
                $binding['mlkem_public_key_b64'] = $mlkemPublicKeyB64 ?? ($binding['mlkem_public_key_b64'] ?? '');
                $binding['bound_at'] = time();
                $updated = true;
                break;
            }
        }
        unset($binding);

        if (!$updated) {
            if (count($bindings) >= 2) {
                throw new \Exception('Maximum 2 Key devices are already bound to this account. Use recovery rebind to replace them or contact admin.');
            }
            $bindings[] = [
                'device_id' => $deviceId,
                'mlkem_public_key_b64' => $mlkemPublicKeyB64 ?? '',
                'label' => count($bindings) === 0 ? 'Primary Key' : 'secondary Key',
                'bound_at' => time(),
            ];
        }

        $this->saveDeviceBindings($user, $bindings);
        $this->config->setUserValue($user, 'pqc_login', 'mlkem_algorithm', MLKEMManager::ALGORITHM);
        $this->config->setUserValue($user, 'pqc_login', 'mlkem_public_key_der_bytes', (string)MLKEMManager::PUBLIC_KEY_DER_BYTES);
        $this->config->setUserValue($user, 'pqc_login', 'mlkem_raw_public_key_bytes', (string)MLKEMManager::PUBLIC_KEY_RAW_BYTES);
        $this->config->setUserValue($user, 'pqc_login', 'mlkem_key_updated_at', (string)time());
    }

    private function checkCredentials(array $data): DataResponse {
        $email = $this->normalizeEmail($data['email'] ?? ($data['user'] ?? ''));
        $password = $data['password'] ?? '';

        if ($email === '' || $password === '') {
            return new DataResponse([
                'status' => 'error',
                'message' => 'Please enter email and password',
            ], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new DataResponse([
                'status' => 'error',
                'message' => 'Please enter a valid email address',
            ], 400);
        }

        $matches = $this->userManager->getByEmail($email);
        if (count($matches) === 0) {
            return new DataResponse([
                'status' => 'error',
                'message' => 'No account is registered with this email',
            ], 403);
        }
        if (count($matches) > 1) {
            return new DataResponse([
                'status' => 'error',
                'message' => 'This email is used by more than one account. Contact the administrator.',
            ], 409);
        }

        $user = $matches[0]->getUID();
        $account = $this->userManager->checkPassword($user, $password);
        if ($account === false) {
            return new DataResponse([
                'status' => 'error',
                'message' => 'Invalid email or password',
            ], 403);
        }
        if ($this->config->getUserValue($user, 'pqc_login', 'account_frozen_at', '') !== '' || (method_exists($account, 'isEnabled') && !$account->isEnabled())) {
            return new DataResponse([
                'status' => 'error',
                'message' => 'This account is frozen. Contact the administrator to unlock or recover it.',
            ], 403);
        }

        return new DataResponse([
            'status' => 'success',
            'message' => 'Credentials verified',
            'user' => $user,
            'email' => $email,
        ]);
    }

    private function recoveryVerify(array $data): DataResponse {
        $user = $this->resolveAccountInput($data['user'] ?? '');
        $share = trim($data['share'] ?? '');
        $kbaAnswers = [];
        if (isset($data['kbaAnswers']) && is_array($data['kbaAnswers'])) {
            $kbaAnswers = $data['kbaAnswers'];
        } else {
            $kbaAnswers = [$data['kbaAnswer'] ?? ''];
        }

        if (!$user || !$share) {
            return new DataResponse(['status' => 'error', 'message' => 'User and recovery share required'], 400);
        }

        $kbaItems = $this->getKbaSet($user);
        $requiredCorrect = count($kbaItems) >= 3 ? 2 : 1;
        if ($this->countCorrectKbaAnswers($user, $kbaAnswers) < $requiredCorrect) {
            return new DataResponse(['status' => 'error', 'message' => 'Security answers are incorrect. Need ' . $requiredCorrect . ' correct answer' . ($requiredCorrect > 1 ? 's' : '') . '. Contact admin if you forgot them.'], 403);
        }

        $mrs = $this->mrsManager->verifyAndBurn($user, $share);
        if (!$mrs) {
            return new DataResponse(['status' => 'error', 'message' => 'Invalid recovery share or version mismatch'], 403);
        }

        $this->session->set('pqc_recovery_mrs', $mrs);
        $this->session->set('pqc_recovery_user', $user);

        return new DataResponse([
            'status' => 'success',
            'message' => 'Recovery share and security answer verified. You may now continue.',
            'token' => bin2hex(random_bytes(16)),
        ]);
    }


    private function recoveryFreeze(): DataResponse {
        $user = $this->session->get('pqc_recovery_user');
        $mrs = $this->session->get('pqc_recovery_mrs');

        if (!$user || !$mrs) {
            return new DataResponse(['status' => 'error', 'message' => 'Unauthorized recovery session'], 403);
        }

        $account = $this->userManager->get($user);
        if ($account === null) {
            return new DataResponse(['status' => 'error', 'message' => 'User not found'], 404);
        }

        if (method_exists($account, 'setEnabled')) {
            $account->setEnabled(false);
        } else {
            return new DataResponse(['status' => 'error', 'message' => 'Account freeze is not supported on this Nextcloud version'], 500);
        }

        $this->config->setUserValue($user, 'pqc_login', 'account_frozen_at', (string)time());
        $this->config->setUserValue($user, 'pqc_login', 'account_frozen_reason', 'recovery_freeze');

        $this->session->remove('pqc_recovery_user');
        $this->session->remove('pqc_recovery_mrs');

        return new DataResponse([
            'status' => 'success',
            'message' => 'Account frozen successfully.',
        ]);
    }

    private function recoveryRebind(array $data): DataResponse {
        $pqcKey = strtolower(trim($data['pqcKey'] ?? ''));
        $mlkemPublicKeyB64 = trim($data['mlkemPublicKeyB64'] ?? '');
        $slot = strtolower(trim($data['slot'] ?? 'primary'));
        $user = $this->session->get('pqc_recovery_user');
        $mrs = $this->session->get('pqc_recovery_mrs');

        if (!$user || !$mrs || ($pqcKey === '' && $mlkemPublicKeyB64 === '')) {
            return new DataResponse(['status' => 'error', 'message' => 'Unauthorized or missing key'], 403);
        }
        if (!in_array($slot, ['primary', 'backup'], true)) {
            return new DataResponse(['status' => 'error', 'message' => 'Invalid binding slot'], 400);
        }

        $normalizedMlkemPublicKey = null;
        if ($mlkemPublicKeyB64 !== '') {
            $normalizedMlkemPublicKey = $this->mlkem->normalizePublicKeyB64($mlkemPublicKeyB64);
            if ($normalizedMlkemPublicKey === null) {
                return new DataResponse(['status' => 'error', 'message' => 'Invalid ML-KEM public key'], 400);
            }
            $pqcKey = $this->mlkem->deviceIdFromPublicKeyB64($normalizedMlkemPublicKey) ?? '';
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $pqcKey)) {
            return new DataResponse(['status' => 'error', 'message' => 'Invalid device ID'], 400);
        }

        $bindings = $this->getDeviceBindings($user);
        $otherBindings = [];
        foreach ($bindings as $binding) {
            if (($binding['device_id'] ?? '') !== $pqcKey) {
                $otherBindings[] = $binding;
            }
        }

        $newBinding = [
            'device_id' => $pqcKey,
            'mlkem_public_key_b64' => $normalizedMlkemPublicKey ?? '',
            'label' => $slot === 'backup' ? 'secondary Key' : 'Primary Key',
            'bound_at' => time(),
        ];

        if ($slot === 'primary') {
            $bindingsToSave = array_merge([$newBinding], array_slice($otherBindings, 0, 1));
        } else {
            $bindingsToSave = array_merge(array_slice($otherBindings, 0, 1), [$newBinding]);
        }

        $this->saveDeviceBindings($user, array_slice($bindingsToSave, 0, 2));
        $sssData = $this->mrsManager->initializeForUser($user);

        $this->session->remove('pqc_recovery_user');
        $this->session->remove('pqc_recovery_mrs');

        return new DataResponse([
            'status' => 'success',
            'message' => 'Device rebound as ' . $slot . ' and recovery secrets rotated.',
            'sss' => $sssData,
        ]);
    }
}
