<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Controller;

use OCA\Spesenerfassung\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IUserSession;

class PageController extends Controller {
	private IUserSession $userSession;

	public function __construct(string $appName, IRequest $request, IUserSession $userSession) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$user = $this->userSession->getUser();
		$isAdmin = $user !== null && \OC::$server->getGroupManager()->isAdmin($user->getUID());

		$data = [
			'initialData' => json_encode([
				'currentUser' => $user !== null ? $user->getUID() : '',
				'isAdmin' => $isAdmin,
				'settings' => SettingsService::getAll(),
			]),
		];
		return new TemplateResponse('spesenerfassung', 'index', $data);
	}
}
