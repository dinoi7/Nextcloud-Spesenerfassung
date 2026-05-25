<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Settings;

use OCP\Settings\ISettings;
use OCP\AppFramework\Http\TemplateResponse;

class AdminSettings implements ISettings {
	public function getForm(): TemplateResponse {
		return new TemplateResponse('spesenerfassung', 'admin');
	}

	public function getSection(): string {
		return 'spesenerfassung';
	}

	public function getPriority(): int {
		return 0;
	}
}
