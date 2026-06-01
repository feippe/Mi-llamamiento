<?php

require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Request.php';
require_once __DIR__ . '/../app/core/Response.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/core/Router.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/CatalogController.php';
require_once __DIR__ . '/../app/controllers/AreaController.php';
require_once __DIR__ . '/../app/controllers/TaskController.php';
require_once __DIR__ . '/../app/controllers/InterviewController.php';
require_once __DIR__ . '/../app/controllers/SyncController.php';

set_exception_handler(function (Throwable $e) {
    $config = require __DIR__ . '/../app/config/config.php';
    $message = $config['env'] === 'production' ? 'Error interno del servidor.' : $e->getMessage();
    Response::error('SERVER_ERROR', $message, 500);
});

$router = new Router();

$router->add('POST', '/api/auth/google', [AuthController::class, 'google']);
$router->add('POST', '/api/auth/logout', [AuthController::class, 'logout']);
$router->add('GET', '/api/auth/me', [AuthController::class, 'me']);

$router->add('GET', '/api/profile', [CatalogController::class, 'profile']);
$router->add('PUT', '/api/profile', [CatalogController::class, 'updateProfile']);
$router->add('GET', '/api/stakes', [CatalogController::class, 'stakes']);
$router->add('POST', '/api/stakes', [CatalogController::class, 'createStake']);
$router->add('GET', '/api/stakes/{id}/units', [CatalogController::class, 'units']);
$router->add('POST', '/api/stakes/{id}/units', [CatalogController::class, 'createUnit']);
$router->add('GET', '/api/service-areas', [CatalogController::class, 'serviceAreas']);
$router->add('GET', '/api/service-areas/{id}/callings', [CatalogController::class, 'callings']);
$router->add('POST', '/api/user-callings', [CatalogController::class, 'createUserCalling']);
$router->add('GET', '/api/user-callings', [CatalogController::class, 'userCallings']);
$router->add('DELETE', '/api/user-callings/{id}', [CatalogController::class, 'deleteUserCalling']);

$router->add('GET', '/api/areas', [AreaController::class, 'index']);
$router->add('GET', '/api/areas/{id}', [AreaController::class, 'show']);
$router->add('DELETE', '/api/areas/{id}', [AreaController::class, 'delete']);
$router->add('GET', '/api/areas/{id}/members', [AreaController::class, 'members']);
$router->add('PUT', '/api/areas/{id}/members/{userId}/role', [AreaController::class, 'promote']);
$router->add('DELETE', '/api/areas/{id}/members/{userId}', [AreaController::class, 'removeMember']);
$router->add('GET', '/api/access-requests', [AreaController::class, 'accessRequests']);
$router->add('POST', '/api/access-requests/{id}/approve', [AreaController::class, 'approveRequest']);
$router->add('POST', '/api/access-requests/{id}/reject', [AreaController::class, 'rejectRequest']);

$router->add('GET', '/api/areas/{id}/tasks', [TaskController::class, 'index']);
$router->add('POST', '/api/areas/{id}/tasks', [TaskController::class, 'create']);
$router->add('GET', '/api/tasks/{id}', [TaskController::class, 'show']);
$router->add('PUT', '/api/tasks/{id}', [TaskController::class, 'update']);
$router->add('DELETE', '/api/tasks/{id}', [TaskController::class, 'delete']);
$router->add('POST', '/api/tasks/{id}/subtasks', [TaskController::class, 'createSubtask']);
$router->add('PUT', '/api/subtasks/{id}', [TaskController::class, 'updateSubtask']);
$router->add('DELETE', '/api/subtasks/{id}', [TaskController::class, 'deleteSubtask']);

$router->add('GET', '/api/areas/{id}/pairs', [InterviewController::class, 'pairs']);
$router->add('POST', '/api/areas/{id}/pairs', [InterviewController::class, 'createPair']);
$router->add('PUT', '/api/pairs/{id}', [InterviewController::class, 'updatePair']);
$router->add('DELETE', '/api/pairs/{id}', [InterviewController::class, 'deletePair']);
$router->add('PUT', '/api/pairs/{id}/assign', [InterviewController::class, 'assignPair']);
$router->add('POST', '/api/pairs/{id}/schedule', [InterviewController::class, 'schedulePair']);
$router->add('POST', '/api/pairs/{id}/interview', [InterviewController::class, 'recordPairInterview']);
$router->add('GET', '/api/areas/{id}/compliance', [InterviewController::class, 'compliance']);
$router->add('GET', '/api/areas/{id}/leadership-interviews', [InterviewController::class, 'leadership']);
$router->add('POST', '/api/areas/{id}/leadership-interviews', [InterviewController::class, 'createLeadership']);
$router->add('PUT', '/api/leadership-interviews/{id}', [InterviewController::class, 'updateLeadership']);
$router->add('POST', '/api/leadership-interviews/{id}/complete', [InterviewController::class, 'completeLeadership']);
$router->add('DELETE', '/api/leadership-interviews/{id}', [InterviewController::class, 'deleteLeadership']);

$router->add('GET', '/api/notifications', [SyncController::class, 'notifications']);
$router->add('POST', '/api/notifications/{id}/read', [SyncController::class, 'readNotification']);
$router->add('POST', '/api/sync/push', [SyncController::class, 'push']);
$router->add('GET', '/api/sync/pull', [SyncController::class, 'pull']);

$router->dispatch();
