<?php

declare(strict_types=1);

/** @var int|null $authenticatedUserId */

ob_start();
?>
<?php if (($authenticatedUserId ?? null) !== null): ?>
    <section class="home-feed" aria-labelledby="page-title">
        <div class="home-feed__intro">
            <p class="eyebrow">Good morning</p>
            <h1 id="page-title">Welcome back.</h1>
            <p>Here is what is unfolding in your communities today.</p>
        </div>

        <section class="home-section" aria-labelledby="your-groups-title">
            <div class="home-section__heading">
                <h2 id="your-groups-title">Your Groups</h2>
                <a href="/groups">View all</a>
            </div>

            <div class="home-group-strip" role="list">
                <a class="home-group-tile" role="listitem" href="/groups">
                    <span class="home-group-tile__image home-group-tile__image--garden" aria-hidden="true"></span>
                    <span>Urban Gardens</span>
                </a>
                <a class="home-group-tile" role="listitem" href="/groups">
                    <span class="home-group-tile__image home-group-tile__image--coffee" aria-hidden="true"></span>
                    <span>Coffee Snobs</span>
                </a>
                <a class="home-group-tile" role="listitem" href="/groups">
                    <span class="home-group-tile__image home-group-tile__image--design" aria-hidden="true"></span>
                    <span>Nordic Design</span>
                </a>
                <a class="home-group-tile" role="listitem" href="/groups/create">
                    <span class="home-group-tile__image home-group-tile__image--add" aria-hidden="true">+</span>
                    <span>Discover</span>
                </a>
            </div>
        </section>

        <section class="home-section" aria-labelledby="latest-threads-title">
            <div class="home-section__heading">
                <h2 id="latest-threads-title">Latest Threads</h2>
                <a href="/groups">Filter</a>
            </div>

            <div class="home-thread-list">
                <article class="home-thread-card">
                    <div class="home-thread-card__meta">
                        <span>Urban Gardens</span>
                        <span>2h ago</span>
                    </div>
                    <h3>Tips for winterizing balcony planters in minus degrees?</h3>
                    <p>First real frost hit last night and I am worried about my perennials. Does anyone have experience with wrapping pots?</p>
                    <div class="home-thread-card__footer">
                        <span>14 replies</span>
                        <span>12 saved</span>
                    </div>
                </article>

                <article class="home-thread-card">
                    <div class="home-thread-card__meta">
                        <span>Nordic Design</span>
                        <span>5h ago</span>
                    </div>
                    <h3>Thoughts on the new Alvar Aalto exhibition?</h3>
                    <p>I visited the museum this weekend and was struck by the early glassware prototypes and the careful curation.</p>
                    <div class="home-thread-card__footer">
                        <span>32 replies</span>
                        <span>45 saved</span>
                    </div>
                </article>
            </div>
        </section>
    </section>
<?php else: ?>
    <section class="home-hero" aria-labelledby="page-title">
        <div class="home-hero__copy">
            <div>
                <p class="eyebrow">01</p>
                <h1 id="page-title">Find your community.</h1>
                <p>Join groups centered around shared interests and thoughtful discussion. A quiet room for public discourse.</p>
            </div>

            <div class="home-hero__actions">
                <a class="button button--primary button--inline" href="/register">Create account</a>
                <a class="button button--secondary button--inline" href="/groups">Explore Samtli</a>
            </div>

            <div class="home-hero__proof" aria-label="Community activity">
                <div class="home-avatar-stack" aria-hidden="true">
                    <span>A</span>
                    <span>K</span>
                    <span>M</span>
                </div>
                <p>Join members already connecting around practical interests.</p>
            </div>
        </div>

        <div class="home-hero__visual" aria-label="Featured communities">
            <article class="home-collage-card home-collage-card--tall">
                <img src="https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=900&q=80" alt="Camera and photos on a table">
                <div>
                    <span>Photography</span>
                    <p>4.2k members</p>
                </div>
            </article>
            <article class="home-collage-card home-collage-card--code">
                <span aria-hidden="true">{}</span>
                <div>
                    <span>Web Dev</span>
                </div>
            </article>
            <article class="home-collage-card">
                <img src="https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=700&q=80" alt="Golf ball on grass">
                <div>
                    <span>Golf</span>
                </div>
            </article>
        </div>
    </section>
<?php endif; ?>
<?php
$content = (string) ob_get_clean();
$mainClass = ($authenticatedUserId ?? null) !== null ? 'home-main home-main--feed' : 'home-main';
require __DIR__ . '/layouts/public.php';
