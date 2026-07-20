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
	private IUserSession $userSession;
	private IUserManager $userManager;
	private UserSettingsService $userSettingsService;
	private IRootFolder $rootFolder;
	private IGroupManager $groupManager;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		IUserManager $userManager,
		UserSettingsService $userSettingsService,
		IRootFolder $rootFolder,
		IGroupManager $groupManager,
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->userSettingsService = $userSettingsService;
		$this->rootFolder = $rootFolder;
		$this->groupManager = $groupManager;
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
		return new DataResponse(SettingsService::getAll());
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

		$result = SettingsService::updateAll($data);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getCategories(): DataResponse {
		return new DataResponse(SettingsService::getCategories());
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
		return new DataResponse(SettingsService::addCategory($name));
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
		return new DataResponse(SettingsService::updateCategory($id, $name));
	}

	public function deleteCategory(int $id): DataResponse {
		if ($this->requireAdmin() === null) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse(SettingsService::deleteCategory($id));
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
		if (array_key_exists('iban', $data)) {
			$this->userSettingsService->setIban($userId, trim($data['iban']));
		}
		return new DataResponse($this->userSettingsService->getAll($userId));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
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
