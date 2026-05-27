<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Service;

use OCA\Spesenerfassung\Db\Receipt;
use OCA\Spesenerfassung\Db\ReceiptMapper;
use DateTime;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException;

class ReceiptService {
	private const ALLOWED_MIMES = [
		'application/pdf',
		'image/jpeg',
		'image/jpg',
		'image/png',
	];
	private const MAX_SIZE = 1048576;
	private const MAX_FILES = 5;

	private ReceiptMapper $receiptMapper;
	private IAppData $appData;

	public function __construct(ReceiptMapper $receiptMapper, IAppData $appData) {
		$this->receiptMapper = $receiptMapper;
		$this->appData = $appData;
	}

	public function validateFile(string $mimeType, int $size): ?string {
		if (!in_array($mimeType, self::ALLOWED_MIMES, true)) {
			return 'Invalid file type. Allowed: PDF, JPG, PNG.';
		}
		if ($size > self::MAX_SIZE) {
			return 'File too large. Maximum size is 1MB.';
		}
		if ($size === 0) {
			return 'File is empty.';
		}
		return null;
	}

	public function findByExpenseId(int $expenseId): array {
		return $this->receiptMapper->findByExpenseId($expenseId);
	}

	public function upload(int $expenseId, string $originalName, string $tempPath, string $mimeType, int $size): ?Receipt {
		$existing = $this->receiptMapper->findByExpenseId($expenseId);
		if (count($existing) >= self::MAX_FILES) {
			return null;
		}

		$error = $this->validateFile($mimeType, $size);
		if ($error !== null) {
			return null;
		}

		$safeName = $this->sanitizeFileName($originalName);
		$now = (new DateTime())->format('Y-m-d H:i:s');

		if ($tempPath === '' || ($tempPath !== '' && !file_exists($tempPath) && !is_readable($tempPath))) {
			return null;
		}

		$content = file_get_contents($tempPath);
		if ($content === false) {
			return null;
		}

		try {
			try {
				$receiptsFolder = $this->appData->getFolder('receipts');
			} catch (FilesNotFoundException) {
				$receiptsFolder = $this->appData->newFolder('receipts');
			}

			try {
				$expenseFolder = $receiptsFolder->getFolder((string) $expenseId);
			} catch (FilesNotFoundException) {
				$expenseFolder = $receiptsFolder->newFolder((string) $expenseId);
			}

			$counter = 1;
			$finalName = $safeName;
			$ext = pathinfo($safeName, PATHINFO_EXTENSION);
			$base = pathinfo($safeName, PATHINFO_FILENAME);
			while ($expenseFolder->nodeExists($finalName)) {
				$finalName = $base . '_' . $counter . ($ext !== '' && $ext !== $base ? '.' . $ext : '');
				$counter++;
			}

			$expenseFolder->newFile($finalName, $content);

			$receipt = new Receipt();
			$receipt->setExpenseId($expenseId);
			$receipt->setFileName($finalName);
			$receipt->setFilePath('receipts/' . $expenseId . '/' . $finalName);
			$receipt->setMimeType($mimeType);
			$receipt->setSize($size);
			$receipt->setCreatedAt($now);

			return $this->receiptMapper->insert($receipt);
		} catch (NotPermittedException | \Throwable $e) {
			return null;
		}
	}

	public function delete(int $receiptId): bool {
		try {
			$receipt = $this->receiptMapper->findById($receiptId);
		} catch (DoesNotExistException) {
			return false;
		}

		try {
			$receiptsFolder = $this->appData->getFolder('receipts');
			$expenseFolder = $receiptsFolder->getFolder((string) $receipt->getExpenseId());
			$file = $expenseFolder->getFile($receipt->getFileName());
			$file->delete();
		} catch (FilesNotFoundException) {
		}

		$this->receiptMapper->delete($receipt);
		return true;
	}

	public function findById(int $receiptId): ?Receipt {
		try {
			return $this->receiptMapper->findById($receiptId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function getContent(Receipt $receipt): ?string {
		try {
			$receiptsFolder = $this->appData->getFolder('receipts');
			$expenseFolder = $receiptsFolder->getFolder((string) $receipt->getExpenseId());
			$file = $expenseFolder->getFile($receipt->getFileName());
			return $file->getContent();
		} catch (\Throwable) {
			return null;
		}
	}

	private function sanitizeFileName(string $name): string {
		$name = basename($name);
		$name = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $name);
		return $name ?: 'receipt';
	}
}
