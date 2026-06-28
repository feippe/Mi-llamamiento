<?php

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

require_once __DIR__ . '/../app/core/helpers.php';
require_once base_path('app/core/Database.php');
require_once base_path('app/core/Request.php');
require_once base_path('app/core/Response.php');
require_once base_path('app/core/Session.php');
require_once base_path('app/core/Controller.php');
require_once base_path('app/core/Router.php');
require_once base_path('app/core/Migrator.php');
require_once base_path('app/services/AccessService.php');
require_once base_path('app/services/PushService.php');
require_once base_path('app/services/MailService.php');
require_once base_path('app/controllers/DashboardController.php');
require_once base_path('app/controllers/PushController.php');
require_once base_path('app/controllers/AuthController.php');
require_once base_path('app/controllers/ProfileController.php');
require_once base_path('app/controllers/CatalogController.php');
require_once base_path('app/controllers/CallingController.php');
require_once base_path('app/controllers/AreaController.php');
require_once base_path('app/controllers/RequestController.php');
require_once base_path('app/controllers/TaskController.php');
require_once base_path('app/controllers/InterviewController.php');
require_once base_path('app/controllers/MinisteringController.php');
require_once base_path('app/controllers/AdminController.php');

set_exception_handler(function (Throwable $e) {
    // No volver a depender de la config aquí: si el fallo original es una
    // config/local.php inválida, re-leerla relanzaría dentro del handler y
    // daría pantalla en blanco. Intentamos leer env de forma segura.
    $env = 'production';
    try {
        $config = require base_path('app/config/config.php');
        $env = $config['app']['env'] ?? 'production';
    } catch (\Throwable $_) {
        // La config en sí está rota (p.ej. local.php con error de sintaxis):
        // mostramos el detalle para poder diagnosticar.
        $env = 'development';
    }
    $message = $env === 'development' ? $e->getMessage() : 'Error interno.';
    Response::error('SERVER_ERROR', $message, 500);
});

Session::start();
Migrator::run();

if (!str_starts_with(Request::path(), '/api')) {
    require __DIR__ . '/app.html';
    exit;
}

$router = new Router();
$router->add('GET',    '/api/dashboard',          [DashboardController::class, 'index']);
$router->add('POST',   '/api/push-subscription',  [PushController::class, 'subscribe']);
$router->add('DELETE', '/api/push-subscription',  [PushController::class, 'unsubscribe']);
$router->add('GET', '/api/csrf', [AuthController::class, 'csrf']);
$router->add('POST', '/api/register', [AuthController::class, 'register']);
$router->add('POST', '/api/login', [AuthController::class, 'login']);
$router->add('POST', '/api/logout', [AuthController::class, 'logout']);
$router->add('POST', '/api/forgot-password', [AuthController::class, 'forgotPassword']);
$router->add('POST', '/api/reset-password', [AuthController::class, 'resetPassword']);
$router->add('GET', '/api/profile', [ProfileController::class, 'show']);
$router->add('PUT', '/api/profile', [ProfileController::class, 'update']);

$router->add('GET', '/api/units', [CatalogController::class, 'units']);
$router->add('GET', '/api/organizations', [CatalogController::class, 'organizations']);
$router->add('GET', '/api/callings', [CatalogController::class, 'callings']);

$router->add('GET', '/api/user-callings', [CallingController::class, 'index']);
$router->add('POST', '/api/user-callings', [CallingController::class, 'create']);
$router->add('DELETE', '/api/user-callings/{id}', [CallingController::class, 'remove']);

$router->add('GET', '/api/work-areas', [AreaController::class, 'index']);
$router->add('GET', '/api/work-areas/{id}/members', [AreaController::class, 'members']);

$router->add('GET', '/api/access-requests', [RequestController::class, 'index']);
$router->add('POST', '/api/access-requests/{id}/approve', [RequestController::class, 'approve']);
$router->add('POST', '/api/access-requests/{id}/reject', [RequestController::class, 'reject']);

$router->add('GET', '/api/tasks', [TaskController::class, 'index']);
$router->add('POST', '/api/tasks', [TaskController::class, 'create']);
$router->add('PUT', '/api/tasks/{id}', [TaskController::class, 'update']);
$router->add('DELETE', '/api/tasks/{id}', [TaskController::class, 'delete']);

$router->add('GET', '/api/interviews', [InterviewController::class, 'index']);
$router->add('POST', '/api/interviews', [InterviewController::class, 'create']);
$router->add('PUT', '/api/interviews/{id}', [InterviewController::class, 'update']);
$router->add('DELETE', '/api/interviews/{id}', [InterviewController::class, 'delete']);

$router->add('GET', '/api/ministering/pairs', [MinisteringController::class, 'pairs']);
$router->add('POST', '/api/ministering/pairs', [MinisteringController::class, 'createPair']);
$router->add('PUT', '/api/ministering/pairs/{id}/interview', [MinisteringController::class, 'saveInterview']);
$router->add('PUT', '/api/ministering/pairs/{id}', [MinisteringController::class, 'updatePair']);
$router->add('DELETE', '/api/ministering/pairs/{id}', [MinisteringController::class, 'deletePair']);

$router->add('GET', '/api/admin/catalog', [AdminController::class, 'catalog']);
$router->add('POST', '/api/admin/units', [AdminController::class, 'createUnit']);
$router->add('PUT', '/api/admin/units/{id}', [AdminController::class, 'updateUnit']);
$router->add('DELETE', '/api/admin/units/{id}', [AdminController::class, 'deleteUnit']);
$router->add('POST', '/api/admin/organizations', [AdminController::class, 'createOrganization']);
$router->add('PUT', '/api/admin/organizations/{id}', [AdminController::class, 'updateOrganization']);
$router->add('DELETE', '/api/admin/organizations/{id}', [AdminController::class, 'deleteOrganization']);
$router->add('POST', '/api/admin/callings', [AdminController::class, 'createCalling']);
$router->add('PUT', '/api/admin/callings/{id}', [AdminController::class, 'updateCalling']);
$router->add('DELETE', '/api/admin/callings/{id}', [AdminController::class, 'deleteCalling']);
$router->add('POST', '/api/admin/work-area-templates', [AdminController::class, 'createAreaTemplate']);
$router->add('PUT', '/api/admin/work-area-templates/{id}', [AdminController::class, 'updateAreaTemplate']);
$router->add('DELETE', '/api/admin/work-area-templates/{id}', [AdminController::class, 'deleteAreaTemplate']);
$router->add('POST', '/api/admin/rules', [AdminController::class, 'createRule']);
$router->add('PUT', '/api/admin/rules/{id}', [AdminController::class, 'updateRule']);
$router->add('DELETE', '/api/admin/rules/{id}', [AdminController::class, 'deleteRule']);

$router->dispatch();
