<?php

declare(strict_types=1);

use Samtli\Auth\AuthenticationService;
use Samtli\Auth\RegistrationService;
use Samtli\Database\Connection;
use Samtli\Discussions\DiscussionCreationService;
use Samtli\Discussions\ReplyCreationService;
use Samtli\Groups\GroupCreationService;
use Samtli\Http\DiscussionController;
use Samtli\Http\AccountController;
use Samtli\Http\GroupAdminController;
use Samtli\Http\GroupController;
use Samtli\Http\InvitationController;
use Samtli\Http\LoginController;
use Samtli\Http\RedirectResponse;
use Samtli\Http\RegisterController;
use Samtli\Http\Response;
use Samtli\Invitations\InvitationAcceptanceService;
use Samtli\Invitations\InvitationCreationService;
use Samtli\Memberships\MemberRoleService;
use Samtli\Memberships\MembershipRequestService;
use Samtli\Memberships\MembershipApprovalService;
use Samtli\Repositories\GroupRepository;
use Samtli\Repositories\InvitationRepository;
use Samtli\Repositories\MembershipRepository;
use Samtli\Repositories\DiscussionRepository;
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
$invitations = new InvitationRepository($pdo);
$discussions = new DiscussionRepository($pdo);
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
$accountController = new AccountController(
    $users,
    $authenticator,
    $csrf,
    $templates
);
$groupController = new GroupController(
    new GroupCreationService($groups),
    new MembershipRequestService($groups, $memberships),
    $groups,
    $memberships,
    $discussions,
    $authenticator,
    $csrf,
    $templates
);
$discussionController = new DiscussionController(
    new DiscussionCreationService($memberships, $discussions),
    new ReplyCreationService($memberships, $discussions),
    $memberships,
    $discussions,
    $authenticator,
    $csrf,
    $templates
);
$groupAdminController = new GroupAdminController(
    new MembershipApprovalService($memberships),
    new MemberRoleService($memberships),
    new InvitationCreationService($groups, $memberships, $invitations),
    $memberships,
    $authenticator,
    $csrf,
    $templates
);
$invitationController = new InvitationController(
    new InvitationAcceptanceService($memberships, $invitations),
    $invitations,
    $authenticator,
    $csrf,
    $templates
);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$groupId = groupIdFromPath($path);
$adminJoinRequest = adminJoinRequestFromPath($path);
$adminMemberRole = adminMemberRoleFromPath($path);
$adminInvitation = adminInvitationFromPath($path);
$invitation = invitationFromPath($path);
$discussionCreateGroupId = discussionCreateGroupIdFromPath($path);
$discussionDetail = discussionDetailFromPath($path);
$discussionReply = discussionReplyFromPath($path);
$authenticatedUserId = $authenticator->id();
if ($authenticatedUserId !== null) {
    $authenticatedUser = $users->findPublicById($authenticatedUserId);

    if ($authenticatedUser === null) {
        $authenticator->logout();
        $authenticatedUserId = null;
    } else {
        $authenticator->updateUserSnapshot([
            'first_name' => (string) $authenticatedUser['first_name'],
            'last_name' => (string) $authenticatedUser['last_name'],
            'email' => (string) $authenticatedUser['email'],
        ]);
    }
}

$response = match (true) {
    $method === 'GET' && $path === '/' => new Response($templates->render('home', [
        'title' => 'Samtli',
        'authenticatedUserId' => $authenticatedUserId,
        'homeGroups' => $authenticatedUserId === null ? [] : $groups->forUser($authenticatedUserId),
        'homeDiscussions' => $authenticatedUserId === null ? [] : $discussions->latestForUserGroups($authenticatedUserId, 5),
        'homeFeaturedGroups' => $authenticatedUserId === null ? $groups->topByMemberCount(3) : [],
    ])),
    $method === 'GET' && $path === '/register' => $registerController->show(),
    $method === 'POST' && $path === '/register' => $registerController->store($_POST),
    $method === 'GET' && $path === '/login' => $loginController->show(pullFlash('success')),
    $method === 'POST' && $path === '/login' => $loginController->store($_POST),
    $method === 'GET' && $path === '/account' => $accountController->show(pullFlash('success'), pullFlash('error')),
    $method === 'POST' && $path === '/account' => $accountController->update($_POST),
    $method === 'POST' && $path === '/logout' => $accountController->logout($_POST),
    $method === 'GET' && $path === '/groups' => $groupController->index(pullFlash('success'), pullFlash('error')),
    $method === 'GET' && $path === '/groups/create' => $groupController->create(),
    $method === 'POST' && $path === '/groups' => $groupController->store($_POST),
    $method === 'GET' && $adminInvitation !== null => $groupAdminController->invitations($adminInvitation, pullFlash('invite_url'), pullFlash('success'), pullFlash('error')),
    $method === 'POST' && $adminInvitation !== null => $groupAdminController->createInvitation($adminInvitation, $_POST),
    $method === 'GET' && $adminMemberRole !== null && $adminMemberRole['memberUserId'] === null => $groupAdminController->members($adminMemberRole['groupId'], pullFlash('success'), pullFlash('error')),
    $method === 'POST' && $adminMemberRole !== null && $adminMemberRole['memberUserId'] !== null => $groupAdminController->updateMemberRole($adminMemberRole['groupId'], $adminMemberRole['memberUserId'], $_POST),
    $method === 'GET' && $adminJoinRequest !== null && $adminJoinRequest['requestId'] === null => $groupAdminController->joinRequests($adminJoinRequest['groupId'], pullFlash('success'), pullFlash('error')),
    $method === 'POST' && $adminJoinRequest !== null && $adminJoinRequest['requestId'] !== null => $groupAdminController->approveJoinRequest($adminJoinRequest['groupId'], $adminJoinRequest['requestId'], $_POST),
    $method === 'GET' && $discussionCreateGroupId !== null => $discussionController->create($discussionCreateGroupId),
    $method === 'POST' && $discussionCreateGroupId !== null => $discussionController->store($discussionCreateGroupId, $_POST),
    $method === 'POST' && $discussionReply !== null => $discussionController->storeReply($discussionReply['groupId'], $discussionReply['discussionId'], $_POST),
    $method === 'GET' && $discussionDetail !== null => $discussionController->show($discussionDetail['groupId'], $discussionDetail['discussionId']),
    $method === 'GET' && $invitation !== null && !$invitation['accept'] => $invitationController->show($invitation['token'], pullFlash('error')),
    $method === 'POST' && $invitation !== null && $invitation['accept'] => $invitationController->accept($invitation['token'], $_POST),
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

/**
 * @return array{groupId: int, requestId: int|null}|null
 */
function adminJoinRequestFromPath(string $path): ?array
{
    if (preg_match('#^/groups/([1-9][0-9]*)/admin/join-requests(?:/([1-9][0-9]*)/approve)?$#', $path, $matches) !== 1) {
        return null;
    }

    return [
        'groupId' => (int) $matches[1],
        'requestId' => isset($matches[2]) ? (int) $matches[2] : null,
    ];
}

/**
 * @return array{groupId: int, memberUserId: int|null}|null
 */
function adminMemberRoleFromPath(string $path): ?array
{
    if (preg_match('#^/groups/([1-9][0-9]*)/admin/members(?:/([1-9][0-9]*)/role)?$#', $path, $matches) !== 1) {
        return null;
    }

    return [
        'groupId' => (int) $matches[1],
        'memberUserId' => isset($matches[2]) ? (int) $matches[2] : null,
    ];
}

function adminInvitationFromPath(string $path): ?int
{
    if (preg_match('#^/groups/([1-9][0-9]*)/admin/invitations$#', $path, $matches) !== 1) {
        return null;
    }

    return (int) $matches[1];
}

/**
 * @return array{token: string, accept: bool}|null
 */
function invitationFromPath(string $path): ?array
{
    if (preg_match('#^/invitations/([a-f0-9]{64})(/accept)?$#', $path, $matches) !== 1) {
        return null;
    }

    return [
        'token' => $matches[1],
        'accept' => isset($matches[2]),
    ];
}

function discussionCreateGroupIdFromPath(string $path): ?int
{
    if (preg_match('#^/groups/([1-9][0-9]*)/discussions/create$#', $path, $matches) !== 1) {
        return null;
    }

    return (int) $matches[1];
}

/**
 * @return array{groupId: int, discussionId: int}|null
 */
function discussionDetailFromPath(string $path): ?array
{
    if (preg_match('#^/groups/([1-9][0-9]*)/discussions/([1-9][0-9]*)$#', $path, $matches) !== 1) {
        return null;
    }

    return [
        'groupId' => (int) $matches[1],
        'discussionId' => (int) $matches[2],
    ];
}

/**
 * @return array{groupId: int, discussionId: int}|null
 */
function discussionReplyFromPath(string $path): ?array
{
    if (preg_match('#^/groups/([1-9][0-9]*)/discussions/([1-9][0-9]*)/replies$#', $path, $matches) !== 1) {
        return null;
    }

    return [
        'groupId' => (int) $matches[1],
        'discussionId' => (int) $matches[2],
    ];
}
