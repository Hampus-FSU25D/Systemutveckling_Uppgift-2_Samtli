<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var int $groupId */

ob_start();
?>
<section class="bootstrap-panel" aria-labelledby="page-title">
    <p class="eyebrow">Group created</p>
    <h1 id="page-title">Your group is ready.</h1>
    <p>You are this group's administrator. Group discussions and member flows will be added in upcoming branches.</p>
    <p class="note">Group ID: <?php echo Html::escape((string) $groupId); ?></p>
</section>
<?php
$content = (string) ob_get_clean();
require dirname(__DIR__) . '/layouts/public.php';
