<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000002Date20260529 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('sp_expenses')) {
			$table = $schema->getTable('sp_expenses');
			if (!$table->hasColumn('payout_method')) {
				$table->addColumn('payout_method', Types::STRING, [
					'notnull' => false,
					'length' => 32,
				]);
			}
		}

		return $schema;
	}
}
