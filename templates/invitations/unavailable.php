<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var string|null $message */

ob_start();
?>
<section class="page-shell page-shell--narrow" aria-labelledby="invitation-unavailable-title">
    <div class="empty-state">
        <h1 id="invitation-unavailable-title">Invitation unavailable</h1>
        <p><?php echo Html::escape($message ?? 'This invitation has already been used, expired, or does not exist.'); ?></p>
        <a class="button button--secondary button--inline" href="/">Return home</a>
    </div>
</section>
<?php
$content = (string) ob_get_clean();
$mainClass = 'page-main';
require dirname(__DIR__) . '/layouts/public.php';
