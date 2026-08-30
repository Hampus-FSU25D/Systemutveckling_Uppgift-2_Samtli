<?php

declare(strict_types=1);

use Samtli\View\TemplateRenderer;

require __DIR__ . '/../../vendor/autoload.php';

$templates = new TemplateRenderer(dirname(__DIR__, 2) . '/templates');

$guestHome = $templates->render('home', [
    'title' => 'Samtli',
    'authenticatedUserId' => null,
]);
assertContains($guestHome, 'home-hero', 'guest home uses hero layout');
assertContains($guestHome, 'Find your community.', 'guest home uses Stitch welcome headline');
assertContains($guestHome, 'Photography', 'guest home includes visual group collage content');
assertContains($guestHome, 'Create account', 'guest home includes account CTA');
assertNotContains($guestHome, 'PHP environment is running', 'guest home does not render bootstrap placeholder copy');
assertNotContains($guestHome, 'bootstrap-panel', 'guest home does not use placeholder panel');

$authenticatedHome = $templates->render('home', [
    'title' => 'Samtli',
    'authenticatedUserId' => 42,
]);
assertContains($authenticatedHome, 'home-feed', 'authenticated home uses feed layout');
assertContains($authenticatedHome, 'Welcome back', 'authenticated home uses personalized welcome surface');
assertContains($authenticatedHome, 'Your Groups', 'authenticated home includes groups overview');
assertContains($authenticatedHome, 'Latest Threads', 'authenticated home includes thread overview');
assertNotContains($authenticatedHome, 'You are logged in.', 'authenticated home does not render login status placeholder');

echo "Home design verification passed.\n";

function assertContains(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\nMissing: {$needle}\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}

function assertNotContains(string $haystack, string $needle, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\nUnexpected: {$needle}\n");
        exit(1);
    }

    echo "PASS: {$message}\n";
}
