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
		['name' => 'expense#destroy', 'url' => '/api/expenses/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],
		['name' => 'expense#uploadReceipt', 'url' => '/api/expenses/{id}/receipts', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'expense#deleteReceipt', 'url' => '/api/expenses/{id}/receipts/{receiptId}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+', 'receiptId' => '\d+']],

		['name' => 'approval#submit', 'url' => '/api/expenses/{id}/submit', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'approval#approve', 'url' => '/api/expenses/{id}/approve', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'approval#reject', 'url' => '/api/expenses/{id}/reject', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'approval#pay', 'url' => '/api/expenses/{id}/pay', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'approval#done', 'url' => '/api/expenses/{id}/done', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'approval#pending', 'url' => '/api/approvals/pending', 'verb' => 'GET'],

		['name' => 'settings#get', 'url' => '/api/settings', 'verb' => 'GET'],
		['name' => 'settings#update', 'url' => '/api/settings', 'verb' => 'PUT'],
		['name' => 'settings#getCategories', 'url' => '/api/categories', 'verb' => 'GET'],
		['name' => 'settings#createCategory', 'url' => '/api/categories', 'verb' => 'POST'],
		['name' => 'settings#updateCategory', 'url' => '/api/categories/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'settings#deleteCategory', 'url' => '/api/categories/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],
	],
];
