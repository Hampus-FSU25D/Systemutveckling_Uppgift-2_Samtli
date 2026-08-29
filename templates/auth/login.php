<?php

declare(strict_types=1);

use Samtli\View\Html;

/** @var string $title */
/** @var string $csrfToken */
/** @var string|null $error */
/** @var string|null $flash */
/** @var array<string, string> $old */

ob_start();
?>
<section class="auth-panel auth-card" aria-labelledby="login-title">
    <?php if ($flash !== null): ?>
        <div class="form-alert form-alert--success" role="status">
            <?php echo Html::escape($flash); ?>
        </div>
    <?php endif; ?>

    <div class="auth-card__body">
        <div class="auth-icon" aria-hidden="true">-></div>

        <div class="auth-heading">
            <h1 id="login-title">Welcome back</h1>
            <p>Enter your details to access your Samtli account.</p>
        </div>

        <?php if ($error !== null): ?>
            <div class="form-alert" role="alert">
                <?php echo Html::escape($error); ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" action="/login" method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?php echo Html::escape($csrfToken); ?>">

            <div class="form-field">
                <label for="email">Email</label>
                <input class="<?php echo $error !== null ? 'form-input is-invalid' : 'form-input'; ?>" id="email" name="email" type="email" value="<?php echo Html::escape($old['email'] ?? ''); ?>" placeholder="name@example.com" autocomplete="email" required maxlength="254" <?php echo $error !== null ? 'aria-invalid="true" aria-describedby="login_error"' : ''; ?>>
            </div>

            <div class="form-field">
                <label for="password">Password</label>
                <input class="<?php echo $error !== null ? 'form-input is-invalid' : 'form-input'; ?>" id="password" name="password" type="password" placeholder="........" autocomplete="current-password" required <?php echo $error !== null ? 'aria-invalid="true" aria-describedby="login_error"' : ''; ?>>
            </div>

            <?php if ($error !== null): ?>
                <p class="field-error" id="login_error"><?php echo Html::escape($error); ?></p>
            <?php endif; ?>

            <button class="button button--primary" type="submit">Log in</button>
        </form>
    </div>

    <p class="auth-link">Don't have an account? <a href="/register">Create an account</a></p>
</section>
<?php
$content = (string) ob_get_clean();
require dirname(__DIR__) . '/layouts/public.php';
