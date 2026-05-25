<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Controller;

use OCA\Spesenerfassung\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class SettingsController extends Controller {
	private IUserSession $userSession;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
	}

	private function requireAdmin(): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}
		// Nextcloud uses 'admin' group for administrators
		return \OC::$server->getGroupManager()->isAdmin($user->getUID());
	}

	#[NoAdminRequired]
	public function get(): DataResponse {
		return new DataResponse(SettingsService::getAll());
	}

	public function update(): DataResponse {
		if (!$this->requireAdmin()) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}
		$data = $this->request->getParsedBody();
		$result = SettingsService::updateAll($data);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	public function getCategories(): DataResponse {
		return new DataResponse(SettingsService::getCategories());
	}

	public function createCategory(): DataResponse {
		if (!$this->requireAdmin()) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}
		$data = $this->request->getParsedBody();
		$name = trim($data['name'] ?? '');
		if ($name === '') {
			return new DataResponse(['error' => 'Name required'], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse(SettingsService::addCategory($name));
	}

	public function updateCategory(int $id): DataResponse {
		if (!$this->requireAdmin()) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}
		$data = $this->request->getParsedBody();
		$name = trim($data['name'] ?? '');
		if ($name === '') {
			return new DataResponse(['error' => 'Name required'], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse(SettingsService::updateCategory($id, $name));
	}

	public function deleteCategory(int $id): DataResponse {
		if (!$this->requireAdmin()) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse(SettingsService::deleteCategory($id));
	}
}
