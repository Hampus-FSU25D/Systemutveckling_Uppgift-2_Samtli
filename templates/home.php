<?php

declare(strict_types=1);

ob_start();
?>
<section class="bootstrap-panel" aria-labelledby="page-title">
    <p class="eyebrow">Samtli</p>
    <h1 id="page-title">PHP environment is running</h1>
    <p>The project foundation is ready for server-rendered community features.</p>
    <p class="note"><a href="/register">Create an account</a> to try the registration flow.</p>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layouts/public.php';
