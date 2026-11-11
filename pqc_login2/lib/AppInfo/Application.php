<?php
namespace OCA\PQCLogin\AppInfo;

use OCA\PQCLogin\Util\MRSManager;
use OCA\PQCLogin\Util\SSSManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\Util;
use OCP\IRequest;
use OCP\IUserSession;

class Application extends App implements IBootstrap {

    public function __construct(array $urlParams = []) {
        parent::__construct('pqc_login', $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerService(SSSManager::class, function($c) {
            return new SSSManager();
        });
        $context->registerService(MRSManager::class, function($c) {
            return new MRSManager(
                $c->get(\OCP\IConfig::class),
                $c->get(SSSManager::class)
            );
        });
        $context->registerService(\OCA\PQCLogin\Controller\RecoveryController::class, function($c) {
            return new \OCA\PQCLogin\Controller\RecoveryController(
                $c->get('AppName'),
                $c->get(\OCP\IRequest::class),
                $c->get(\OCP\IUserManager::class),
                $c->get(\OCP\IUserSession::class),
                $c->get(\OCP\IConfig::class),
                $c->get(MRSManager::class),
                $c->get(\OCP\ISession::class)
            );
        });
        $context->registerEventListener(
            \OCP\AppFramework\Http\Events\BeforeResponseSentEvent::class,
            \OCA\PQCLogin\Listener\ResponseListener::class
        );
    }

    public function boot(IBootContext $context): void {
        $server = $context->getServerContainer();
        $request = $server->get(IRequest::class);
        $path = $request->getPathInfo();

        $isLoginPage = $this->isLoginPath($path);

        if ($isLoginPage) {
            Util::addScript('pqc_login', 'pqc-branding');
            Util::addScript('pqc_login', 'pqc-login');
        } elseif ($server->get(IUserSession::class)->isLoggedIn()) {
            Util::addScript('pqc_login', 'pqc-session');
        }
    }

    private function isLoginPath(string $path): bool {
        return $path === '/login'
            || $path === '/login/'
            || (strpos($path, '/login/') === 0);
    }
}
