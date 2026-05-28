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
	private const MAX_SIZE = 1048576;
	private const MAX_FILES = 5;

	private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

	private ReceiptMapper $receiptMapper;
	private IAppData $appData;

	public function __construct(ReceiptMapper $receiptMapper, IAppData $appData) {
		$this->receiptMapper = $receiptMapper;
		$this->appData = $appData;
	}

	public function validateFile(string $fileName, string $tempPath, int $size): ?string {
		if ($size > self::MAX_SIZE) {
			return 'File too large. Maximum size is 1MB.';
		}
		if ($size === 0) {
			return 'File is empty.';
		}
		if ($tempPath === '' || !file_exists($tempPath) || !is_readable($tempPath)) {
			return 'File not accessible.';
		}

		$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
			return 'Invalid file type. Allowed: PDF, JPG, PNG.';
		}

		$detected = (new \finfo(FILEINFO_MIME_TYPE))->file($tempPath);
		$allowedMimes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/pjpeg'];
		if (!in_array($detected, $allowedMimes, true)) {
			return 'Invalid file type. Allowed: PDF, JPG, PNG.';
		}

		return null;
	}

	public function findByExpenseId(int $expenseId): array {
		return $this->receiptMapper->findByExpenseId($expenseId);
	}

	public function upload(int $expenseId, string $originalName, string $tempPath, string $mimeType, int $size): ?Receipt {
		$log = '/var/www/nextcloud-data/spes_upload.log';
		file_put_contents($log, "upload: id=$expenseId file=$originalName temp=$tempPath mime=$mimeType size=$size\n", FILE_APPEND);

		$existing = $this->receiptMapper->findByExpenseId($expenseId);
		if (count($existing) >= self::MAX_FILES) {
			file_put_contents($log, "FAIL: too many files\n", FILE_APPEND);
			return null;
		}

		$error = $this->validateFile($originalName, $tempPath, $size);
		if ($error !== null) {
			file_put_contents($log, "FAIL: $error\n", FILE_APPEND);
			return null;
		}
		file_put_contents($log, "validateFile OK\n", FILE_APPEND);

		$safeName = $this->sanitizeFileName($originalName);
		$now = (new DateTime())->format('Y-m-d H:i:s');

		$content = file_get_contents($tempPath);
		if ($content === false) {
			file_put_contents($log, "FAIL: file_get_contents failed\n", FILE_APPEND);
			return null;
		}

		try {
			file_put_contents($log, "entering IAppData try\n", FILE_APPEND);
			try {
				$receiptsFolder = $this->appData->getFolder('receipts');
				file_put_contents($log, "got receiptsFolder\n", FILE_APPEND);
			} catch (FilesNotFoundException) {
				$receiptsFolder = $this->appData->newFolder('receipts');
				file_put_contents($log, "created receiptsFolder\n", FILE_APPEND);
			}

			try {
				$expenseFolder = $receiptsFolder->getFolder((string) $expenseId);
				file_put_contents($log, "got expenseFolder $expenseId\n", FILE_APPEND);
			} catch (FilesNotFoundException) {
				$expenseFolder = $receiptsFolder->newFolder((string) $expenseId);
				file_put_contents($log, "created expenseFolder $expenseId\n", FILE_APPEND);
			}

			$counter = 1;
			$finalName = $safeName;
			$ext = pathinfo($safeName, PATHINFO_EXTENSION);
			$base = pathinfo($safeName, PATHINFO_FILENAME);
			while ($expenseFolder->fileExists($finalName)) {
				$finalName = $base . '_' . $counter . ($ext !== '' && $ext !== $base ? '.' . $ext : '');
				$counter++;
			}

			$expenseFolder->newFile($finalName, $content);
			file_put_contents($log, "file created: $finalName\n", FILE_APPEND);

			$receipt = new Receipt();
			$receipt->setExpenseId($expenseId);
			$receipt->setFileName($finalName);
			$receipt->setFilePath('receipts/' . $expenseId . '/' . $finalName);
			$receipt->setMimeType($mimeType);
			$receipt->setSize($size);
			$receipt->setCreatedAt($now);

			$result = $this->receiptMapper->insert($receipt);
			file_put_contents($log, "SUCCESS: receipt id={$result->getId()}\n", FILE_APPEND);
			return $result;
		} catch (\Throwable $e) {
			file_put_contents($log, "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
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
