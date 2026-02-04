<?php

// Front Controller Básico

// 1. CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Max-Age: 86400"); // Cache preflight por 24 horas

// Manejo de preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Autoloader simple (PSR-4 estricto no requerido para este ejemplo, pero haremos un map simple)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use App\Config\Env;
// Cargar variables de entorno
Env::load(__DIR__ . '/../.env');

use App\Controllers\NotasParcialesController;
use App\Controllers\AuthController;
use App\Controllers\GestionUsuariosController;
use App\Controllers\CronogramaController;
use App\Controllers\TutoriasController;
use App\Controllers\ReportesTutoriaController;
use App\Controllers\TutoriaDetalleController;
use App\Controllers\DocumentoController;

use App\Middleware\AuthMiddleware;
use App\Helpers\Response;

// 3. Router muy básico
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Prefix /api
if (strpos($uri, '/api') !== 0) {
    Response::json(['message' => 'API Endpoint no encontrado'], 404);
}

// Rutas
$controller = new NotasParcialesController();
$authController = new AuthController();
$gestionUsuariosController = new GestionUsuariosController();
$cronogramaController = new CronogramaController();
$tutoriasController = new TutoriasController();
$reportesTutoriaController = new ReportesTutoriaController();
$tutoriaDetalleController = new TutoriaDetalleController();
$documentoController = new DocumentoController();

$authMiddleware = new AuthMiddleware();

// Rutas Públicas

if ($uri === '/api/login' && $method === 'POST') {
    $authController->login();
    exit;
}

// Autenticación Middleware (Para todo lo demás)
// Si falla, el middleware corta la ejecución con error 401
$currentUser = $authMiddleware->authenticate();

// Ruta de validación de token (después del middleware)
if ($uri === '/api/auth/validate' && $method === 'GET') {
    $authController->validateSession($currentUser);
    exit;
}

// Rutas Protegidas

switch (true) {
    // GET /api/periodos
    case $uri === '/api/periodos' && $method === 'GET':
        $controller->getPeriodos($currentUser);
        break;

    // GET /api/niveles
    case $uri === '/api/niveles' && $method === 'GET':
        $controller->getNiveles($currentUser);
        break;

    // GET /api/asignaturas
    case $uri === '/api/asignaturas' && $method === 'GET':
        $controller->getAsignaturas($currentUser);
        break;

    // GET /api/docentes
    case $uri === '/api/docentes' && $method === 'GET':
        $controller->getDocentes($currentUser);
        break;

    // GET /api/alumnos
    case $uri === '/api/alumnos' && $method === 'GET':
        $controller->getAlumnos($currentUser);
        break;

    // GET /api/secciones
    case $uri === '/api/secciones' && $method === 'GET':
        $controller->getSecciones($currentUser);
        break;

    // GET /api/notas-parciales o /api/nota-parcial
    case ($uri === '/api/notas-parciales' || $uri === '/api/nota-parcial') && $method === 'GET':
        $controller->getNotas($currentUser);
        break;

    // POST /api/notas-parciales o /api/nota-parcial
    case ($uri === '/api/notas-parciales' || $uri === '/api/nota-parcial') && $method === 'POST':
        $controller->createNota($currentUser);
        break;

    // PUT/DELETE /api/notas-parciales/{id} o /api/nota-parcial/{id}
    case preg_match('#^/api/(notas-parciales|nota-parcial)/(\d+)$#', $uri, $matches):
        $id = $matches[2];
        if ($method === 'PUT') {
            $controller->updateNota($id);
        } elseif ($method === 'DELETE') {
            $controller->deleteNota($id);
        } else {
            Response::error('Método no permitido', 405);
        }
        break;

    // GET /api/gestion-usuarios/roles
    case $uri === '/api/gestion-usuarios/roles' && $method === 'GET':
        $gestionUsuariosController->getRoles($currentUser);
        break;

    // GET /api/gestion-usuarios/docentes
    case $uri === '/api/gestion-usuarios/docentes' && $method === 'GET':
        $gestionUsuariosController->getDocentes($currentUser);
        break;

    // GET /api/gestion-usuarios/usuarios
    case $uri === '/api/gestion-usuarios/usuarios' && $method === 'GET':
        $gestionUsuariosController->getUsuarios($currentUser);
        break;

    // POST /api/gestion-usuarios/usuarios
    case $uri === '/api/gestion-usuarios/usuarios' && $method === 'POST':
        $gestionUsuariosController->createUsuario($currentUser);
        break;

    // PUT/DELETE /api/gestion-usuarios/usuarios/{id}
    case preg_match('#^/api/gestion-usuarios/usuarios/(\d+)$#', $uri, $matches):
        $id = $matches[1];
        if ($method === 'PUT') {
            $gestionUsuariosController->updateUsuario($id);
        } elseif ($method === 'DELETE') {
            $gestionUsuariosController->deleteUsuario($id);
        } else {
            Response::error('Método no permitido', 405);
        }
        break;

    // --- CRONOGRAMA ---

    // GET /api/cronograma/periodos
    case $uri === '/api/cronograma/periodos' && $method === 'GET':
        $cronogramaController->getPeriodos($currentUser);
        break;

    // Tipo Documento CRUD
    case $uri === '/api/cronograma/tipo-documento' && $method === 'GET':
        $cronogramaController->getTiposDocumento($currentUser);
        break;
    case $uri === '/api/cronograma/tipo-documento' && $method === 'POST':
        $cronogramaController->createTipoDocumento($currentUser);
        break;
    case preg_match('#^/api/cronograma/tipo-documento/(\d+)$#', $uri, $matches):
        $id = $matches[1];
        if ($method === 'PUT') {
            $cronogramaController->updateTipoDocumento($id);
        } elseif ($method === 'DELETE') {
            $cronogramaController->deleteTipoDocumento($id);
        } else {
            Response::error('Método no permitido', 405);
        }
        break;

    // Cronograma CRUD
    case $uri === '/api/cronograma' && $method === 'GET':
        $cronogramaController->getCronogramas($currentUser);
        break;
    case $uri === '/api/cronograma' && $method === 'POST':
        $cronogramaController->createCronograma($currentUser);
        break;
    case preg_match('#^/api/cronograma/(\d+)$#', $uri, $matches):
        $id = $matches[1];
        if ($method === 'PUT') {
            $cronogramaController->updateCronograma($id);
        } elseif ($method === 'DELETE') {
            $cronogramaController->deleteCronograma($id);
        } else {
            Response::error('Método no permitido', 405);
        }
        break;

    // --- TUTORIAS ---

    case $uri === '/api/tutorias/candidatos' && $method === 'GET':
        $tutoriasController->getCandidatos($currentUser);
        break;

    case $uri === '/api/tutorias/historial' && $method === 'GET':
        $tutoriasController->getHistorial($currentUser);
        break;

    case $uri === '/api/tutorias' && $method === 'POST':
        $tutoriasController->assignTutoria($currentUser);
        break;

    case preg_match('#^/api/tutorias/(\d+)$#', $uri, $matches):
        $id = $matches[1];
        if ($method === 'PUT') {
            $tutoriasController->updateTutoria($id);
        } elseif ($method === 'DELETE') {
            $tutoriasController->deleteTutoria($id);
        } else {
            Response::error('Método no permitido', 405);
        }
        break;

    // --- REPORTES TUTORIA ---

    // GET /api/reportes-tutoria/asignaturas (Asignaturas del docente logeado por periodo/nivel)
    case $uri === '/api/reportes-tutoria/asignaturas' && $method === 'GET':
        $reportesTutoriaController->getAsignaturas($currentUser);
        break;

    // GET /api/reportes-tutoria/formatos
    case $uri === '/api/reportes-tutoria/formatos' && $method === 'GET':
        $reportesTutoriaController->getFormatos();
        break;

    // GET /api/reportes-tutoria/tipos-documento
    case $uri === '/api/reportes-tutoria/tipos-documento' && $method === 'GET':
        $reportesTutoriaController->getTiposDocumento();
        break;

    // GET /api/reportes-tutoria/estudiantes-riesgo
    case $uri === '/api/reportes-tutoria/estudiantes-riesgo' && $method === 'GET':
        $reportesTutoriaController->getEstudiantesEnRiesgo($currentUser);
        break;

    // POST /api/reportes-tutoria/registrar
    case $uri === '/api/reportes-tutoria/registrar' && $method === 'POST':
        $reportesTutoriaController->registrarTutoria($currentUser);
        break;

    // --- RUTAS TUTORIA DETALLE ---

    // GET /api/tutoria-detalle/estados
    case $uri === '/api/tutoria-detalle/estados' && $method === 'GET':
        $tutoriaDetalleController->getEstados();
        break;

    // GET /api/tutoria-detalle?tutoriaid=X
    case $uri === '/api/tutoria-detalle' && $method === 'GET':
        $tutoriaDetalleController->getByTutoriaId($currentUser);
        break;

    // POST /api/tutoria-detalle
    case $uri === '/api/tutoria-detalle' && $method === 'POST':
        $tutoriaDetalleController->create($currentUser);
        break;

    // PUT /api/tutoria-detalle/{id}
    case preg_match('/^\/api\/tutoria-detalle\/(\d+)$/', $uri, $matches) && $method === 'PUT':
        $tutoriaDetalleController->update($currentUser, $matches[1]);
        break;

    // DELETE /api/tutoria-detalle/{id}
    case preg_match('/^\/api\/tutoria-detalle\/(\d+)$/', $uri, $matches) && $method === 'DELETE':
        $tutoriaDetalleController->delete($currentUser, $matches[1]);
        break;

    // --- DOCUMENTOS ---

    // POST /api/documentos
    case $uri === '/api/documentos' && $method === 'POST':
        $documentoController->upload($currentUser);
        break;

    // GET /api/documentos
    case $uri === '/api/documentos' && $method === 'GET':
        $documentoController->getDocuments($currentUser);
        break;

    default:
        Response::error('Ruta no encontrada', 404);
        break;
}

