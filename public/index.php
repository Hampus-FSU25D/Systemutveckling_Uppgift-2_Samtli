<?php

declare(strict_types=1);

use Samtli\Auth\AuthenticationService;
use Samtli\Auth\RegistrationService;
use Samtli\Database\Connection;
use Samtli\Http\LoginController;
use Samtli\Http\RedirectResponse;
use Samtli\Http\RegisterController;
use Samtli\Http\Response;
use Samtli\Repositories\UserRepository;
use Samtli\Security\CsrfTokenManager;
use Samtli\Security\SessionAuthenticator;
use Samtli\View\TemplateRenderer;

require dirname(__DIR__) . '/vendor/autoload.php';

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

$templates = new TemplateRenderer(dirname(__DIR__) . '/templates');
$csrf = new CsrfTokenManager($_SESSION);
$users = new UserRepository(Connection::fromEnvironment());
$authenticator = new SessionAuthenticator($_SESSION);

$registerController = new RegisterController(
    new RegistrationService($users),
    $csrf,
    $templates
);
$loginController = new LoginController(
    new AuthenticationService($users),
    $authenticator,
    $csrf,
    $templates
);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$response = match ([$method, $path]) {
    ['GET', '/'] => new Response($templates->render('home', [
        'title' => 'Samtli',
        'authenticatedUserId' => $authenticator->id(),
    ])),
    ['GET', '/register'] => $registerController->show(),
    ['POST', '/register'] => $registerController->store($_POST),
    ['GET', '/login'] => $loginController->show(pullFlash('success')),
    ['POST', '/login'] => $loginController->store($_POST),
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
