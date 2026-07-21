<?php
declare(strict_types=1);

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		['name' => 'expense#index', 'url' => '/api/expenses', 'verb' => 'GET'],
		['name' => 'expense#show', 'url' => '/api/expenses/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'expense#create', 'url' => '/api/expenses', 'verb' => 'POST'],
		['name' => 'expense#ping', 'url' => '/api/ping', 'verb' => 'POST'],
		['name' => 'expense#update', 'url' => '/api/expenses/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'expense#updateCategory', 'url' => '/api/expenses/{id}/category', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'expense#destroy', 'url' => '/api/expenses/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],
		['name' => 'expense#uploadReceipt', 'url' => '/api/expenses/{id}/receipts', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'expense#deleteReceipt', 'url' => '/api/expenses/{id}/receipts/{receiptId}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+', 'receiptId' => '\d+']],
		['name' => 'expense#downloadReceipt', 'url' => '/api/expenses/{id}/receipts/{receiptId}/download', 'verb' => 'GET', 'requirements' => ['id' => '\d+', 'receiptId' => '\d+']],
		['name' => 'expense#previewReceipt', 'url' => '/api/expenses/{id}/receipts/{receiptId}/preview', 'verb' => 'GET', 'requirements' => ['id' => '\d+', 'receiptId' => '\d+']],

		['name' => 'approval#submit', 'url' => '/api/expenses/{id}/submit', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'approval#approve', 'url' => '/api/expenses/{id}/approve', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'approval#reject', 'url' => '/api/expenses/{id}/reject', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'approval#pay', 'url' => '/api/expenses/{id}/pay', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'approval#done', 'url' => '/api/expenses/{id}/done', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'approval#pending', 'url' => '/api/approvals/pending', 'verb' => 'GET'],
		['name' => 'approval#paystack', 'url' => '/api/expenses/{id}/paystack', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'approval#paystackList', 'url' => '/api/approvals/paystack', 'verb' => 'GET'],
		['name' => 'approval#paystackExport', 'url' => '/api/approvals/paystack/export', 'verb' => 'GET'],
		['name' => 'approval#paystackExportSingle', 'url' => '/api/approvals/paystack/export/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'approval#paystackPayAll', 'url' => '/api/approvals/paystack/pay-all', 'verb' => 'POST'],
		['name' => 'approval#bookkeeping', 'url' => '/api/expenses/{id}/bookkeeping', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'approval#bookkeepingList', 'url' => '/api/approvals/bookkeeping', 'verb' => 'GET'],
		['name' => 'approval#bookkeepingExport', 'url' => '/api/approvals/bookkeeping/export', 'verb' => 'GET'],
		['name' => 'approval#bookkeepingExportSingle', 'url' => '/api/approvals/bookkeeping/export/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'approval#evaluation', 'url' => '/api/evaluation', 'verb' => 'GET'],
		['name' => 'approval#evaluationExport', 'url' => '/api/evaluation/export', 'verb' => 'GET'],

		['name' => 'settings#get', 'url' => '/api/settings', 'verb' => 'GET'],
		['name' => 'settings#update', 'url' => '/api/settings', 'verb' => 'PUT'],
		['name' => 'settings#getCategories', 'url' => '/api/categories', 'verb' => 'GET'],
		['name' => 'settings#createCategory', 'url' => '/api/categories', 'verb' => 'POST'],
		['name' => 'settings#updateCategory', 'url' => '/api/categories/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'settings#deleteCategory', 'url' => '/api/categories/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],
		['name' => 'settings#getUsers', 'url' => '/api/users', 'verb' => 'GET'],
		['name' => 'settings#getRoles', 'url' => '/api/user/roles', 'verb' => 'GET'],
		['name' => 'settings#getUserSettings', 'url' => '/api/settings/user', 'verb' => 'GET'],
		['name' => 'settings#updateUserSettings', 'url' => '/api/settings/user', 'verb' => 'PUT'],
	],
];
