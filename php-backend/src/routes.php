<?php

declare(strict_types=1);

/**
 * Таблица маршрутов — аналог подключения роутеров в server/src/index.js.
 * Контракт маршрутов: docs/backend-api.md, §4.
 *
 * @var Router $router
 */

use App\Controller\AdminController;
use App\Controller\ApplicationController;
use App\Controller\HealthController;
use App\Routing\Router;

// Публичный JSON-API
$router->get('/api/health', [HealthController::class, 'health']);
$router->post('/api/applications', [ApplicationController::class, 'create']);

// Админка: HTML + HTTP Basic (realm="admin")
$router->get('/admin', [AdminController::class, 'index']);
$router->post('/admin/{id:\d+}/status', [AdminController::class, 'changeStatus']);
