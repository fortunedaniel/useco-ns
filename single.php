<?php

// =============================================================================
// Fichier : single.php
// Rôle : affiche un contenu individuel WordPress
//
// Ce qu'il fait :
// - affiche le titre, le contenu et les métadonnées essentielles
// - conserve une structure sémantique simple et réutilisable
//
// Fichiers liés :
// - header.php
// - footer.php
// =============================================================================
defined('ABSPATH') || exit;

get_header();

?>
<main id="main-content" class="single-page">
    <?php while (have_posts()) : the_post(); ?>
        <div <?php post_class(); ?>>
            <h1><?php the_title(); ?></h1>
            <?php the_content(); ?>
        </div>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
