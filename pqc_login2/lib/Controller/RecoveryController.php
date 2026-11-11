<?php
namespace OCA\PQCLogin\Controller;

use OCA\PQCLogin\Util\MRSManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\ISession;

class RecoveryController extends Controller {

    private IUserManager $userManager;
    private IUserSession $userSession;
    private IConfig $config;
    private MRSManager $mrsManager;
    private ISession $session;

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
    }

    #[NoAdminRequired]
    #[PublicPage]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/recovery')]
    public function index(): TemplateResponse {
        return new TemplateResponse('pqc_login', 'recovery', [], 'guest');
    }

    #[NoAdminRequired]
    #[PublicPage]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'POST', url: '/recovery/verify')]
    public function verify(): DataResponse {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $user = strtolower(trim($data['user'] ?? ''));
        $share = trim($data['share'] ?? '');

        if (!$user || !$share) {
            return new DataResponse(['status' => 'error', 'message' => 'User and share required'], 400);
        }

        $mrs = $this->mrsManager->verifyAndBurn($user, $share);
        if (!$mrs) {
            return new DataResponse(['status' => 'error', 'message' => 'Invalid recovery share or version mismatch'], 403);
        }

        // Store MRS in session to authorize rebinding
        $this->session->set('pqc_recovery_mrs', $mrs);
        $this->session->set('pqc_recovery_user', $user);

        return new DataResponse([
            'status' => 'success',
            'message' => 'Recovery secret verified. You may now rebind your device.',
            'token' => bin2hex(random_bytes(16)) // Simple token for frontend
        ]);
    }

    #[NoAdminRequired]
    #[PublicPage]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'POST', url: '/recovery/rebind')]
    public function rebind(): DataResponse {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $pqcKey = strtolower(trim($data['pqcKey'] ?? ''));
        $user = $this->session->get('pqc_recovery_user');
        $mrs = $this->session->get('pqc_recovery_mrs');

        if (!$user || !$mrs || !$pqcKey) {
            return new DataResponse(['status' => 'error', 'message' => 'Unauthorized or missing key'], 403);
        }

        // Rebind new key
        $this->config->setUserValue($user, 'pqc_login', 'pqc_key', $pqcKey);
        
        // Rotate MRS immediately
        $sssData = $this->mrsManager->initializeForUser($user);

        // Clear recovery session
        $this->session->remove('pqc_recovery_user');
        $this->session->remove('pqc_recovery_mrs');

        return new DataResponse([
            'status' => 'success',
            'message' => 'Device rebound and recovery secrets rotated.',
            'sss' => $sssData
        ]);
    }
}
