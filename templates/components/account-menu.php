<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var array{id: int|string, first_name: string, last_name: string, email: string, created_at?: string}|null $layoutUser */
/** @var string $logoutToken */

$fullName = trim((string) ($layoutUser['first_name'] ?? '') . ' ' . (string) ($layoutUser['last_name'] ?? ''));
$email = (string) ($layoutUser['email'] ?? '');
$initials = strtoupper(substr((string) ($layoutUser['first_name'] ?? 'A'), 0, 1) . substr((string) ($layoutUser['last_name'] ?? ''), 0, 1));
$initials = $initials !== '' ? $initials : 'A';

?>
<details class="account-menu">
    <summary class="account-menu__trigger" aria-label="Open account menu">
        <span class="account-avatar account-avatar--icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
                <path d="M12 12.25a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path>
                <path d="M4.75 20.25a7.25 7.25 0 0 1 14.5 0"></path>
            </svg>
        </span>
    </summary>
    <div class="account-menu__panel">
        <div class="account-menu__identity">
            <span class="account-avatar" aria-hidden="true"><?php echo Html::escape($initials); ?></span>
            <div>
                <strong><?php echo Html::escape($fullName !== '' ? $fullName : 'Account'); ?></strong>
                <?php if ($email !== ''): ?>
                    <span><?php echo Html::escape($email); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <a href="/">Home</a>
        <a href="/groups">Explore groups</a>
        <a href="/account">Account settings</a>
        <form action="/logout" method="post">
            <input type="hidden" name="_csrf" value="<?php echo Html::escape($logoutToken); ?>">
            <button type="submit">Log out</button>
        </form>
    </div>
</details>
