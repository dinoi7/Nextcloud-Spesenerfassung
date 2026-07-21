<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Service;

use OCP\IConfig;

class UserSettingsService {
	private const KEY_IBAN = 'iban';
	private const KEY_PLZ = 'plz';
	private const KEY_CITY = 'city';

	public function __construct(
		private IConfig $config,
	) {
	}

	public function getIban(string $userId): string {
		return $this->config->getUserValue($userId, 'spesenerfassung', self::KEY_IBAN, '');
	}

	public function setIban(string $userId, string $iban): void {
		$iban = strtoupper(preg_replace('/\s+/', '', $iban));
		if ($iban !== '' && !self::isValidIban($iban)) {
			throw new \InvalidArgumentException('Invalid IBAN format');
		}
		$this->config->setUserValue($userId, 'spesenerfassung', self::KEY_IBAN, $iban);
	}

	private static function isValidIban(string $iban): bool {
		if (strlen($iban) < 15 || strlen($iban) > 34) {
			return false;
		}
		if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
			return false;
		}
		$rearranged = substr($iban, 4) . substr($iban, 0, 4);
		$numeric = '';
		for ($i = 0; $i < strlen($rearranged); $i++) {
			$char = $rearranged[$i];
			if (ctype_alpha($char)) {
				$numeric .= (string)(ord($char) - ord('A') + 10);
			} else {
				$numeric .= $char;
			}
		}
		return bcmod($numeric, '97') === '1';
	}

	public function getPlz(string $userId): string {
		return $this->config->getUserValue($userId, 'spesenerfassung', self::KEY_PLZ, '');
	}

	public function setPlz(string $userId, string $plz): void {
		$plz = trim($plz);
		if ($plz !== '' && !preg_match('/^\d{4}$/', $plz)) {
			throw new \InvalidArgumentException('Invalid PLZ format');
		}
		$this->config->setUserValue($userId, 'spesenerfassung', self::KEY_PLZ, $plz);
	}

	public function getCity(string $userId): string {
		return $this->config->getUserValue($userId, 'spesenerfassung', self::KEY_CITY, '');
	}

	public function setCity(string $userId, string $city): void {
		$city = trim($city);
		$this->config->setUserValue($userId, 'spesenerfassung', self::KEY_CITY, $city);
	}

	public function getAll(string $userId): array {
		return [
			'iban' => $this->getIban($userId),
			'plz' => $this->getPlz($userId),
			'city' => $this->getCity($userId),
		];
	}

	public function updateSettings(string $userId, array $data): void {
		if (array_key_exists('iban', $data)) {
			$this->setIban($userId, trim($data['iban']));
		}
		if (array_key_exists('plz', $data)) {
			$this->setPlz($userId, trim($data['plz']));
		}
		if (array_key_exists('city', $data)) {
			$this->setCity($userId, trim($data['city']));
		}
	}
}
