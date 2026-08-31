<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var array{id: int|string, first_name: string, last_name: string, email: string, created_at: string}|null $user */
/** @var string $memberSince */
/** @var string $csrfToken */
/** @var string $logoutToken */
/** @var array<string, list<string>> $errors */
/** @var string|null $success */
/** @var string|null $error */

$fieldError = static fn (string $field): ?string => $errors[$field][0] ?? null;
$fieldClass = static fn (string $field): string => isset($errors[$field]) ? 'form-input is-invalid' : 'form-input';

ob_start();
?>
<section class="account-page page-shell" aria-labelledby="account-title">
    <div class="account-card">
        <div class="account-card__heading">
            <h1 id="account-title">Account settings</h1>
            <p>Update your personal details here.</p>
        </div>

        <?php if ($success !== null): ?>
            <div class="form-alert form-alert--success" role="status"><?php echo Html::escape($success); ?></div>
        <?php endif; ?>

        <?php if ($error !== null): ?>
            <div class="form-alert" role="alert"><?php echo Html::escape($error); ?></div>
        <?php endif; ?>

        <?php if (isset($errors['_form'])): ?>
            <div class="form-alert" role="alert"><?php echo Html::escape($errors['_form'][0]); ?></div>
        <?php endif; ?>

        <form class="account-form" action="/account" method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?php echo Html::escape($csrfToken); ?>">

            <div class="form-row">
                <div class="form-field">
                    <label for="first_name">First name</label>
                    <input class="<?php echo $fieldClass('first_name'); ?>" id="first_name" name="first_name" type="text" value="<?php echo Html::escape((string) ($user['first_name'] ?? '')); ?>" autocomplete="given-name" required maxlength="100" <?php echo $fieldError('first_name') ? 'aria-invalid="true" aria-describedby="first_name_error"' : ''; ?>>
                    <?php if ($fieldError('first_name')): ?>
                        <p class="field-error" id="first_name_error"><?php echo Html::escape((string) $fieldError('first_name')); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <label for="last_name">Last name</label>
                    <input class="<?php echo $fieldClass('last_name'); ?>" id="last_name" name="last_name" type="text" value="<?php echo Html::escape((string) ($user['last_name'] ?? '')); ?>" autocomplete="family-name" required maxlength="100" <?php echo $fieldError('last_name') ? 'aria-invalid="true" aria-describedby="last_name_error"' : ''; ?>>
                    <?php if ($fieldError('last_name')): ?>
                        <p class="field-error" id="last_name_error"><?php echo Html::escape((string) $fieldError('last_name')); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-field">
                <label for="email">Email</label>
                <input class="<?php echo $fieldClass('email'); ?>" id="email" name="email" type="email" value="<?php echo Html::escape((string) ($user['email'] ?? '')); ?>" autocomplete="email" required maxlength="254" <?php echo $fieldError('email') ? 'aria-invalid="true" aria-describedby="email_error"' : ''; ?>>
                <?php if ($fieldError('email')): ?>
                    <p class="field-error" id="email_error"><?php echo Html::escape((string) $fieldError('email')); ?></p>
                <?php endif; ?>
            </div>

            <div class="account-meta">
                <span>Member since</span>
                <strong><?php echo Html::escape($memberSince); ?></strong>
            </div>

            <div class="account-actions">
                <button class="button button--primary button--inline" type="submit">Save changes</button>
            </div>
        </form>

        <form class="account-logout" action="/logout" method="post">
            <input type="hidden" name="_csrf" value="<?php echo Html::escape($logoutToken); ?>">
            <button class="button button--danger button--inline" type="submit">Log out</button>
        </form>
    </div>
</section>
<?php
$content = (string) ob_get_clean();
$mainClass = 'page-main';
require dirname(__DIR__) . '/layouts/public.php';
