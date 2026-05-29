<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Service;

use OCP\IConfig;

class UserSettingsService {
	private const KEY_IBAN = 'iban';

	public function __construct(
		private IConfig $config,
	) {
	}

	public function getIban(string $userId): string {
		return $this->config->getUserValue($userId, 'spesenerfassung', self::KEY_IBAN, '');
	}

	public function setIban(string $userId, string $iban): void {
		$this->config->setUserValue($userId, 'spesenerfassung', self::KEY_IBAN, $iban);
	}

	public function getAll(string $userId): array {
		return [
			'iban' => $this->getIban($userId),
		];
	}
}
