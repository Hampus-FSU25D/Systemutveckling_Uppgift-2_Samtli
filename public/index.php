<?php

declare(strict_types=1);

use Samtli\Auth\AuthenticationService;
use Samtli\Auth\RegistrationService;
use Samtli\Database\Connection;
use Samtli\Groups\GroupCreationService;
use Samtli\Http\GroupController;
use Samtli\Http\LoginController;
use Samtli\Http\RedirectResponse;
use Samtli\Http\RegisterController;
use Samtli\Http\Response;
use Samtli\Memberships\MembershipRequestService;
use Samtli\Repositories\GroupRepository;
use Samtli\Repositories\MembershipRepository;
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
$pdo = Connection::fromEnvironment();
$users = new UserRepository($pdo);
$groups = new GroupRepository($pdo);
$memberships = new MembershipRepository($pdo);
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
$groupController = new GroupController(
    new GroupCreationService($groups),
    new MembershipRequestService($groups, $memberships),
    $groups,
    $authenticator,
    $csrf,
    $templates
);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$groupId = groupIdFromPath($path);

$response = match (true) {
    $method === 'GET' && $path === '/' => new Response($templates->render('home', [
        'title' => 'Samtli',
        'authenticatedUserId' => $authenticator->id(),
    ])),
    $method === 'GET' && $path === '/register' => $registerController->show(),
    $method === 'POST' && $path === '/register' => $registerController->store($_POST),
    $method === 'GET' && $path === '/login' => $loginController->show(pullFlash('success')),
    $method === 'POST' && $path === '/login' => $loginController->store($_POST),
    $method === 'GET' && $path === '/groups' => $groupController->index(pullFlash('success'), pullFlash('error')),
    $method === 'GET' && $path === '/groups/create' => $groupController->create(),
    $method === 'POST' && $path === '/groups' => $groupController->store($_POST),
    $method === 'POST' && $groupId !== null && str_ends_with($path, '/join-requests') => $groupController->requestMembership($groupId, $_POST),
    $method === 'GET' && $groupId !== null => $groupController->show($groupId),
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

function groupIdFromPath(string $path): ?int
{
    if (preg_match('#^/groups/([1-9][0-9]*)(?:/join-requests)?$#', $path, $matches) !== 1) {
        return null;
    }

    return (int) $matches[1];
}
