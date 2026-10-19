<?php

// =============================================================================
// Fichier : page.php
// Rôle : affiche les pages sans template ComKani spécifique
//
// Ce qu'il fait :
// - sert de template de secours pour les Pages WordPress
// - affiche le contenu de la page
// - n'affiche pas automatiquement le titre natif WordPress
//
// Fichiers liés :
// - inc/theme.php
// - pages/*.php
// - packages/comkani-core/includes/editor.php
// =============================================================================
defined('ABSPATH') || exit;

get_header();
?>
<main id="main-content" class="page-content">
    <?php
    while (have_posts()) :
        the_post();
        the_content();
    endwhile;
    ?>
</main>
<?php get_footer(); ?>
