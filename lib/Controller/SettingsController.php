<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Controller;

use OCA\Spesenerfassung\Service\SettingsService;
use OCA\Spesenerfassung\Service\UserSettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

class SettingsController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IUserSession $userSession,
		private IUserManager $userManager,
		private UserSettingsService $userSettingsService,
		private IRootFolder $rootFolder,
		private IGroupManager $groupManager,
		private SettingsService $settingsService,
	) {
		parent::__construct($appName, $request);
	}

	private function requireAdmin(): ?string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return null;
		}
		if (!$this->groupManager->isAdmin($user->getUID())) {
			return null;
		}
		return $user->getUID();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function get(): DataResponse {
		$user = $this->userSession->getUser();
		$uid = $user !== null ? $user->getUID() : '';
		$isAdmin = $user !== null && $this->groupManager->isAdmin($uid);
		$isPresident = $uid === $this->settingsService->getPresidentUid() && $uid !== '';
		$isTreasurer = $uid === $this->settingsService->getTreasurerUid() && $uid !== '';

		if ($isAdmin || $isPresident || $isTreasurer) {
			return new DataResponse($this->settingsService->getAll());
		}

		$all = $this->settingsService->getAll();
		return new DataResponse([
			'categories' => $all['categories'],
			'threshold' => $all['threshold'],
			'defaultPayoutMethod' => $all['defaultPayoutMethod'],
		]);
	}

	public function update(): DataResponse {
		$adminUid = $this->requireAdmin();
		if ($adminUid === null) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}
		$data = $this->request->getParams();

		if (isset($data['bookingFolder'])) {
			$folder = str_replace('\\', '/', trim($data['bookingFolder']));
			$data['bookingFolder'] = $folder;
			if ($folder === '') {
				return new DataResponse(['error' => 'Der Ordnerpfad darf nicht leer sein.'], Http::STATUS_BAD_REQUEST);
			}
			if (str_contains($folder, '..')) {
				return new DataResponse(['error' => 'Der Ordnerpfad enthält ungültige Zeichen.'], Http::STATUS_BAD_REQUEST);
			}
			try {
				$userFolder = $this->rootFolder->getUserFolder($adminUid);
				$parts = explode('/', trim($folder, '/'));
				$current = $userFolder;
				foreach ($parts as $part) {
					if ($part === '') {
						continue;
					}
					if (!$current->nodeExists($part)) {
						return new DataResponse(['error' => 'Der Ordner "' . $folder . '" existiert nicht.'], Http::STATUS_BAD_REQUEST);
					}
					$current = $current->get($part);
				}
			} catch (\Throwable $e) {
				return new DataResponse(['error' => 'Der Ordner "' . $folder . '" konnte nicht gefunden werden.'], Http::STATUS_BAD_REQUEST);
			}
		}

		$result = $this->settingsService->updateAll($data);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getCategories(): DataResponse {
		return new DataResponse($this->settingsService->getCategories());
	}

	public function createCategory(): DataResponse {
		if ($this->requireAdmin() === null) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}
		$data = $this->request->getParams();
		$name = trim($data['name'] ?? '');
		if ($name === '') {
			return new DataResponse(['error' => 'Name required'], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($this->settingsService->addCategory($name));
	}

	public function updateCategory(int $id): DataResponse {
		if ($this->requireAdmin() === null) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}
		$data = $this->request->getParams();
		$name = trim($data['name'] ?? '');
		if ($name === '') {
			return new DataResponse(['error' => 'Name required'], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($this->settingsService->updateCategory($id, $name));
	}

	public function deleteCategory(int $id): DataResponse {
		if ($this->requireAdmin() === null) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($this->settingsService->deleteCategory($id));
	}

	private function getUserId(): string {
		$user = $this->userSession->getUser();
		return $user !== null ? $user->getUID() : '';
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getUserSettings(): DataResponse {
		$userId = $this->getUserId();
		if ($userId === '') {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		return new DataResponse($this->userSettingsService->getAll($userId));
	}

	#[NoAdminRequired]
	public function updateUserSettings(): DataResponse {
		$userId = $this->getUserId();
		if ($userId === '') {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		$data = $this->request->getParams();
		try {
			$this->userSettingsService->updateSettings($userId, $data);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($this->userSettingsService->getAll($userId));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getUsers(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null || !$this->groupManager->isAdmin($user->getUID())) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}
		$users = $this->userManager->search('');
		$result = array_map(fn($u) => [
			'uid' => $u->getUID(),
			'displayName' => $u->getDisplayName(),
		], $users);
		usort($result, fn($a, $b) => strcmp($a['displayName'], $b['displayName']));
		return new DataResponse($result);
	}
}
