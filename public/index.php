<?php

declare(strict_types=1);

$environment = getenv('APP_ENV') ?: 'local';

?><!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Samtli</title>
    <link rel="stylesheet" href="/assets/css/base.css">
</head>
<body>
    <main class="bootstrap-page">
        <section class="bootstrap-panel" aria-labelledby="page-title">
            <p class="eyebrow">Samtli</p>
            <h1 id="page-title">PHP-miljon ar igang</h1>
            <p>Projektgrunden kor PHP <?php echo htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8'); ?> i miljo <?php echo htmlspecialchars($environment, ENT_QUOTES, 'UTF-8'); ?>.</p>
            <p class="note">Forumfunktioner byggs i kommande feature branches.</p>
        </section>
    </main>
</body>
</html>
