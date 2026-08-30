<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var string $title */
/** @var string $csrfToken */
/** @var array<string, list<string>> $errors */
/** @var array<string, string> $old */

$fieldError = static fn (string $field): ?string => $errors[$field][0] ?? null;
$fieldClass = static fn (string $field): string => isset($errors[$field]) ? 'form-input is-invalid' : 'form-input';

ob_start();
?>
<section class="auth-panel auth-panel--open" aria-labelledby="register-title">
    <div class="auth-heading">
        <h1 id="register-title">Join Samtli</h1>
        <p>Create your account to start participating in the discussion.</p>
    </div>

    <?php if (isset($errors['_form'])): ?>
        <div class="form-alert" role="alert">
            <?php echo Html::escape($errors['_form'][0]); ?>
        </div>
    <?php endif; ?>

    <form class="auth-form" action="/register" method="post" novalidate>
        <input type="hidden" name="_csrf" value="<?php echo Html::escape($csrfToken); ?>">

        <div class="form-row">
            <div class="form-field">
                <label for="first_name">First Name</label>
                <input class="<?php echo $fieldClass('first_name'); ?>" id="first_name" name="first_name" type="text" value="<?php echo Html::escape($old['first_name'] ?? ''); ?>" placeholder="e.g. Jane" autocomplete="given-name" required maxlength="100" <?php echo $fieldError('first_name') ? 'aria-invalid="true" aria-describedby="first_name_error"' : ''; ?>>
                <?php if ($fieldError('first_name')): ?>
                    <p class="field-error" id="first_name_error"><?php echo Html::escape((string) $fieldError('first_name')); ?></p>
                <?php endif; ?>
            </div>

            <div class="form-field">
                <label for="last_name">Last Name</label>
                <input class="<?php echo $fieldClass('last_name'); ?>" id="last_name" name="last_name" type="text" value="<?php echo Html::escape($old['last_name'] ?? ''); ?>" placeholder="e.g. Doe" autocomplete="family-name" required maxlength="100" <?php echo $fieldError('last_name') ? 'aria-invalid="true" aria-describedby="last_name_error"' : ''; ?>>
                <?php if ($fieldError('last_name')): ?>
                    <p class="field-error" id="last_name_error"><?php echo Html::escape((string) $fieldError('last_name')); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-field">
            <label for="email">Email Address</label>
            <input class="<?php echo $fieldClass('email'); ?>" id="email" name="email" type="email" value="<?php echo Html::escape($old['email'] ?? ''); ?>" placeholder="jane@example.com" autocomplete="email" required maxlength="254" <?php echo $fieldError('email') ? 'aria-invalid="true" aria-describedby="email_error"' : ''; ?>>
            <?php if ($fieldError('email')): ?>
                <p class="field-error" id="email_error"><?php echo Html::escape((string) $fieldError('email')); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="password">Password</label>
            <input class="<?php echo $fieldClass('password'); ?>" id="password" name="password" type="password" autocomplete="new-password" required minlength="8" <?php echo $fieldError('password') ? 'aria-invalid="true" aria-describedby="password_help password_error"' : 'aria-describedby="password_help"'; ?>>
            <p class="field-help" id="password_help">Minimum 8 characters.</p>
            <?php if ($fieldError('password')): ?>
                <p class="field-error" id="password_error"><?php echo Html::escape((string) $fieldError('password')); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="password_confirmation">Confirm Password</label>
            <input class="<?php echo $fieldClass('password_confirmation'); ?>" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required <?php echo $fieldError('password_confirmation') ? 'aria-invalid="true" aria-describedby="password_confirmation_error"' : ''; ?>>
            <?php if ($fieldError('password_confirmation')): ?>
                <p class="field-error" id="password_confirmation_error"><?php echo Html::escape((string) $fieldError('password_confirmation')); ?></p>
            <?php endif; ?>
        </div>

        <button class="button button--primary" type="submit">Create account</button>
    </form>

    <p class="auth-link">Already have an account? <a href="/login">Log in</a></p>
</section>
<?php
$content = (string) ob_get_clean();
require dirname(__DIR__) . '/layouts/public.php';
