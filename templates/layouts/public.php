<?php

declare(strict_types=1);

use Samtli\View\Html;
use Samtli\Security\CsrfTokenManager;

if (!isset($_SESSION) || !is_array($_SESSION)) {
    $_SESSION = [];
}

/** @var string $title */
/** @var string $content */
/** @var string|null $mainClass */
/** @var bool|null $showFooter */

$layoutUserId = $authenticatedUserId ?? ($_SESSION['auth_user_id'] ?? null);
$isAuthenticatedLayout = is_int($layoutUserId) && $layoutUserId > 0;
$sessionUser = $_SESSION['auth_user'] ?? null;
$layoutUser = isset($authenticatedUser) && is_array($authenticatedUser)
    ? $authenticatedUser
    : (is_array($sessionUser) ? $sessionUser : null);
$layoutCsrf = new CsrfTokenManager($_SESSION);
$logoutToken = $layoutCsrf->token('logout');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo Html::escape($title); ?> | Samtli</title>
    <meta name="theme-color" content="#faf9f7">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="icon" href="/favicon.ico">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/base.css">
</head>
<body>
    <header class="public-header">
        <div class="public-header__inner">
            <div class="public-header__left">
                <?php require dirname(__DIR__) . '/components/brand.php'; ?>
                <nav class="public-nav" aria-label="Primary">
                    <?php if ($isAuthenticatedLayout): ?>
                        <a href="/" <?php echo $currentPath === '/' ? 'class="is-active" aria-current="page"' : ''; ?>>Home</a>
                    <?php endif; ?>
                    <a href="/groups" <?php echo str_starts_with($currentPath, '/groups') ? 'class="is-active" aria-current="page"' : ''; ?>>Explore</a>
                </nav>
            </div>
            <nav class="public-actions" aria-label="Account">
                <?php if ($isAuthenticatedLayout): ?>
                    <?php require dirname(__DIR__) . '/components/account-menu.php'; ?>
                <?php else: ?>
                    <a href="/login">Log in</a>
                    <a class="public-actions__primary" href="/register">Create account</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="<?php echo Html::escape($mainClass ?? 'auth-page'); ?>">
        <?php echo $content; ?>
    </main>
    <?php require dirname(__DIR__) . '/components/footer.php'; ?>
    <?php if ($isAuthenticatedLayout): ?>
        <nav class="mobile-tabbar" aria-label="Mobile primary">
            <a href="/" <?php echo $currentPath === '/' ? 'aria-current="page"' : ''; ?>>Home</a>
            <a href="/groups" <?php echo str_starts_with($currentPath, '/groups') ? 'aria-current="page"' : ''; ?>>Explore</a>
            <a href="/account" <?php echo $currentPath === '/account' ? 'aria-current="page"' : ''; ?>>Account</a>
        </nav>
    <?php endif; ?>
</body>
</html>
