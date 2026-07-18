<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\AppInfo;

use OCA\Spesenerfassung\Dashboard\SpesenWidget;
use OCA\Spesenerfassung\Service\SettingsService;
use OCA\Spesenerfassung\Service\UserSettingsService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IAppConfig;

class Application extends App implements IBootstrap {
	public const APP_ID = 'spesenerfassung';

	public function __construct() {
		parent::__construct(self::APP_ID);
		$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
		if (file_exists($autoloadPath)) {
			require_once $autoloadPath;
		}
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(SpesenWidget::class);
	}

	public function boot(IBootContext $context): void {
		$container = $context->getAppContainer();
		$appConfig = $container->get(IAppConfig::class);
		SettingsService::setConfig($appConfig);

		$container->get(UserSettingsService::class);
	}
}
