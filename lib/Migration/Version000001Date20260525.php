<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000001Date20260525 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('spesenerfassung_expenses')) {
			$table = $schema->createTable('spesenerfassung_expenses');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('amount', Types::DECIMAL, [
				'notnull' => true,
				'precision' => 10,
				'scale' => 2,
			]);
			$table->addColumn('category', Types::STRING, [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'draft',
			]);
			$table->addColumn('expense_date', Types::DATE, [
				'notnull' => true,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'spesenerfassung_expenses_user_idx');
			$table->addIndex(['status'], 'spesenerfassung_expenses_status_idx');
		}

		if (!$schema->hasTable('spesenerfassung_receipts')) {
			$table = $schema->createTable('spesenerfassung_receipts');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('expense_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('file_name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('file_path', Types::STRING, [
				'notnull' => true,
				'length' => 512,
			]);
			$table->addColumn('mime_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('size', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['expense_id'], 'spesenerfassung_rec_expense_idx');
			$table->addForeignKeyConstraint(
				$schema->getTable('spesenerfassung_expenses'),
				['expense_id'],
				['id'],
				['onDelete' => 'CASCADE']
			);
		}

		if (!$schema->hasTable('spesenerfassung_approvals')) {
			$table = $schema->createTable('spesenerfassung_approvals');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('expense_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('action', Types::STRING, [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('comment', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['expense_id'], 'spesenerfassung_appr_expense_idx');
			$table->addForeignKeyConstraint(
				$schema->getTable('spesenerfassung_expenses'),
				['expense_id'],
				['id'],
				['onDelete' => 'CASCADE']
			);
		}

		return $schema;
	}
}
