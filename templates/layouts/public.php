<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var string $title */
/** @var string $content */

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
        <a class="brand-link" href="/" aria-label="Samtli home">
            <span class="brand-mark" aria-hidden="true">S</span>
            <span class="brand-name">Samtli</span>
        </a>
    </header>
    <main class="auth-page">
        <?php echo $content; ?>
    </main>
</body>
</html>
