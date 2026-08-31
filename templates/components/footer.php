<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var bool $isAuthenticatedLayout */

$footerLinks = $isAuthenticatedLayout
    ? [
        ['href' => '/', 'label' => 'Home'],
        ['href' => '/groups', 'label' => 'Explore'],
        ['href' => '/account', 'label' => 'Account'],
    ]
    : [
        ['href' => '/', 'label' => 'Home'],
        ['href' => '/groups', 'label' => 'Explore'],
        ['href' => '/login', 'label' => 'Log in'],
        ['href' => '/register', 'label' => 'Create account'],
    ];

?>
<footer class="site-footer">
    <div class="site-footer__inner">
        <div>
            <a class="site-footer__brand" href="/">Samtli</a>
            <p>A place for communities and conversations.</p>
        </div>
        <nav class="site-footer__nav" aria-label="Footer">
            <?php foreach ($footerLinks as $link): ?>
                <a href="<?php echo Html::escape($link['href']); ?>"><?php echo Html::escape($link['label']); ?></a>
            <?php endforeach; ?>
        </nav>
        <p>&copy; <?php echo date('Y'); ?> Samtli</p>
    </div>
</footer>
