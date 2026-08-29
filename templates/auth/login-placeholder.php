<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var string $title */
/** @var string|null $flash */

ob_start();
?>
<section class="auth-panel" aria-labelledby="login-title">
    <?php if ($flash !== null): ?>
        <div class="form-alert form-alert--success" role="status">
            <?php echo Html::escape($flash); ?>
        </div>
    <?php endif; ?>
    <div class="auth-heading">
        <h1 id="login-title">Log in</h1>
        <p>Login will be implemented in the next authentication branch.</p>
    </div>
    <p class="auth-link"><a href="/register">Create an account</a></p>
</section>
<?php
$content = (string) ob_get_clean();
require dirname(__DIR__) . '/layouts/public.php';
