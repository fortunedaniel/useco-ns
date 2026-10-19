<?php
// =============================================================================
// Fichier : header.php
// Rôle : afficher l’en-tête et la navigation générique WordPress
//
// Ce qu’il fait :
// - utilise le titre et le logo personnalisables dans WordPress
// - affiche le menu principal attribué dans l’administration
//
// Ce qu’il ne fait pas :
// - n’intègre aucune identité ni ressource d’un ancien client
// =============================================================================
defined('ABSPATH') || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main-content">Aller au contenu</a>
<header class="site-header">
    <div class="inner">
        <a class="site-brand" href="<?php echo esc_url(home_url('/')); ?>" rel="home">
            <?php if (has_custom_logo()) : ?><?php echo wp_get_attachment_image((int) get_theme_mod('custom_logo'), 'full'); ?><?php else : ?><?php bloginfo('name'); ?><?php endif; ?>
        </a>
        <nav class="navigation" aria-label="Navigation principale">
            <?php if (has_nav_menu('primary')) : ?>
                <?php wp_nav_menu(['theme_location' => 'primary', 'container' => false]); ?>
            <?php endif; ?>
        </nav>
    </div>
</header>
