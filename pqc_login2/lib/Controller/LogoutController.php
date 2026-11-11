<?php

namespace OCA\PQCLogin\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class LogoutController extends Controller {

    private $userSession;

    public function __construct(
        $appName,
        IRequest $request,
        IUserSession $userSession
    ) {
        parent::__construct($appName, $request);

        $this->userSession = $userSession;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function destroy() {

        // 🔥 REAL NEXTCLOUD LOGOUT
        $this->userSession->logout();

        return new DataResponse([
            "status" => "logged_out"
        ]);
    }
}
