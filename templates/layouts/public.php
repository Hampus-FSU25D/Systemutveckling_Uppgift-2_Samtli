<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var string $title */
/** @var string $content */
/** @var string|null $mainClass */
/** @var bool|null $showFooter */

$layoutUserId = $authenticatedUserId ?? ($_SESSION['auth_user_id'] ?? null);
$isAuthenticatedLayout = is_int($layoutUserId) && $layoutUserId > 0;

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo Html::escape($title); ?> | Samtli</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/base.css">
</head>
<body>
    <header class="public-header">
        <div class="public-header__inner">
            <div class="public-header__left">
                <a class="brand-link" href="/" aria-label="Samtli home">
                    <span class="brand-name">Samtli</span>
                </a>
                <nav class="public-nav" aria-label="Primary">
                    <a href="/groups">Explore</a>
                </nav>
            </div>
            <nav class="public-actions" aria-label="Account">
                <?php if ($isAuthenticatedLayout): ?>
                    <a class="public-actions__primary" href="/groups/create">Create group</a>
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
    <?php if (($showFooter ?? false) === true): ?>
        <footer class="public-footer">
            <div class="public-footer__inner">
                <span>Samtli</span>
                <div class="public-footer__links" aria-label="Policy pages planned">
                    <span>Privacy</span>
                    <span>Terms</span>
                    <span>Community Guidelines</span>
                </div>
                <span>&copy; 2024 Samtli. Built for community.</span>
            </div>
        </footer>
    <?php endif; ?>
    <?php if ($isAuthenticatedLayout): ?>
        <nav class="mobile-tabbar" aria-label="Mobile primary">
            <a href="/">Home</a>
            <a href="/groups">Explore</a>
            <a href="/groups/create">Create</a>
        </nav>
    <?php endif; ?>
</body>
</html>
