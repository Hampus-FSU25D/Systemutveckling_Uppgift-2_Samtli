<?php

declare(strict_types=1);

use Samtli\Auth\RegistrationService;
use Samtli\Database\Connection;
use Samtli\Http\RedirectResponse;
use Samtli\Http\RegisterController;
use Samtli\Http\Response;
use Samtli\Repositories\UserRepository;
use Samtli\Security\CsrfTokenManager;
use Samtli\View\TemplateRenderer;

require dirname(__DIR__) . '/vendor/autoload.php';

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

$templates = new TemplateRenderer(dirname(__DIR__) . '/templates');
$csrf = new CsrfTokenManager($_SESSION);
$controller = new RegisterController(
    new RegistrationService(new UserRepository(Connection::fromEnvironment())),
    $csrf,
    $templates
);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$response = match ([$method, $path]) {
    ['GET', '/'] => new Response($templates->render('home', ['title' => 'Samtli'])),
    ['GET', '/register'] => $controller->show(),
    ['POST', '/register'] => $controller->store($_POST),
    ['GET', '/login'] => new Response($templates->render('auth/login-placeholder', [
        'title' => 'Log in',
        'flash' => pullFlash('success'),
    ])),
    default => new Response($templates->render('home', ['title' => 'Page not found']), 404),
};

if ($response instanceof Response || $response instanceof RedirectResponse) {
    $response->send();
}

function pullFlash(string $key): ?string
{
    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return is_string($message) ? $message : null;
}
