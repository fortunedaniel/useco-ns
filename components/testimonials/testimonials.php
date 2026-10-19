<?php

// =============================================================================
// Fichier : components/testimonials/testimonials.php
// Rôle : affiche un slider de témoignages
//
// Ce qu'il fait :
// - affiche un témoignage à la fois
// - utilise la sémantique native des citations
// - génère la pagination lorsque plusieurs témoignages existent
//
// Fichiers liés :
// - components/testimonials/testimonials.js
// - resources/scss/components/_testimonials.scss
// =============================================================================
defined('ABSPATH') || exit;

$testimonials = is_array($args['testimonials'] ?? null)
    ? $args['testimonials']
    : [];

if ($testimonials === []) {
    return;
}

?>
<div class="testimonials" data-testimonials>
    <div class="slides">
        <?php foreach ($testimonials as $index => $testimonial) : ?>
            <figure
                class="testimonial<?php echo $index === 0 ? ' is-active' : ''; ?>"
                data-testimonial
                <?php echo $index > 0 ? 'hidden' : ''; ?>
            >
                <?php if (($testimonial['text'] ?? '') !== '') : ?>
                    <blockquote class="text">
                        <?php echo wpautop(
                            esc_html((string) $testimonial['text'])
                        ); ?>
                    </blockquote>
                <?php endif; ?>

                <?php if (
                    ($testimonial['name'] ?? '') !== ''
                    || ($testimonial['role'] ?? '') !== ''
                ) : ?>
                    <figcaption class="author">
                        <?php if (($testimonial['name'] ?? '') !== '') : ?>
                            <strong class="name">
                                <?php echo esc_html(
                                    (string) $testimonial['name']
                                ); ?>
                            </strong>
                        <?php endif; ?>

                        <?php if (($testimonial['role'] ?? '') !== '') : ?>
                            <span class="role">
                                <?php echo esc_html(
                                    (string) $testimonial['role']
                                ); ?>
                            </span>
                        <?php endif; ?>
                    </figcaption>
                <?php endif; ?>
            </figure>
        <?php endforeach; ?>
    </div>

    <?php if (count($testimonials) > 1) : ?>
        <div
            class="pagination"
            aria-label="Témoignages"
        >
            <?php foreach ($testimonials as $index => $testimonial) : ?>
                <button
                    type="button"
                    class="dot<?php echo $index === 0 ? ' is-active' : ''; ?>"
                    aria-label="Afficher le témoignage <?php echo esc_attr((string) ($index + 1)); ?>"
                    aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                    data-testimonial-dot="<?php echo esc_attr((string) $index); ?>"
                ></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
