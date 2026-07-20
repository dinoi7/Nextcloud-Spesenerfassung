<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Controller;

use OCA\Spesenerfassung\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

class PageController extends Controller {
	private IUserSession $userSession;
	private IGroupManager $groupManager;
	private IConfig $config;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		IConfig $config,
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->config = $config;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$user = $this->userSession->getUser();
		$uid = $user !== null ? $user->getUID() : '';
		$isAdmin = $user !== null && $this->groupManager->isAdmin($user->getUID());
		$locale = $uid !== ''
			? $this->config->getUserValue($uid, 'core', 'lang', 'en')
			: 'en';
		if (str_starts_with($locale, 'de')) {
			$locale = 'de';
		} else {
			$locale = 'en';
		}

		$data = [
			'initialData' => json_encode([
				'currentUser' => $uid,
				'isAdmin' => $isAdmin,
				'locale' => $locale,
				'settings' => SettingsService::getAll(),
			]),
		];
		return new TemplateResponse('spesenerfassung', 'index', $data);
	}
}
