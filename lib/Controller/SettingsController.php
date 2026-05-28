<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Controller;

use OCA\Spesenerfassung\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

class SettingsController extends Controller {
	private IUserSession $userSession;
	private IUserManager $userManager;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		IUserManager $userManager,
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
		$this->userManager = $userManager;
	}

	private function requireAdmin(): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}
		return \OC::$server->getGroupManager()->isAdmin($user->getUID());
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function get(): DataResponse {
		return new DataResponse(SettingsService::getAll());
	}

	/**
	 * @NoCSRFRequired
	 */
	public function update(): DataResponse {
		if (!$this->requireAdmin()) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}
		$data = $this->request->getParams();
		$result = SettingsService::updateAll($data);
		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getCategories(): DataResponse {
		return new DataResponse(SettingsService::getCategories());
	}

	/**
	 * @NoCSRFRequired
	 */
	public function createCategory(): DataResponse {
		if (!$this->requireAdmin()) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}
		$data = $this->request->getParams();
		$name = trim($data['name'] ?? '');
		if ($name === '') {
			return new DataResponse(['error' => 'Name required'], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse(SettingsService::addCategory($name));
	}

	/**
	 * @NoCSRFRequired
	 */
	public function updateCategory(int $id): DataResponse {
		if (!$this->requireAdmin()) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}
		$data = $this->request->getParams();
		$name = trim($data['name'] ?? '');
		if ($name === '') {
			return new DataResponse(['error' => 'Name required'], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse(SettingsService::updateCategory($id, $name));
	}

	/**
	 * @NoCSRFRequired
	 */
	public function deleteCategory(int $id): DataResponse {
		if (!$this->requireAdmin()) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse(SettingsService::deleteCategory($id));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getUsers(): DataResponse {
		$users = $this->userManager->search('');
		$result = array_map(fn($u) => [
			'uid' => $u->getUID(),
			'displayName' => $u->getDisplayName(),
		], $users);
		usort($result, fn($a, $b) => strcmp($a['displayName'], $b['displayName']));
		return new DataResponse($result);
	}
}
