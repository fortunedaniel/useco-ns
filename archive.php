<?php

// =============================================================================
// Fichier : archive.php
// Rôle : affiche les archives de contenus WordPress
//
// Ce qu'il fait :
// - affiche le titre de l'archive
// - liste les contenus publiés
// - affiche la pagination native WordPress
//
// Fichiers liés :
// - header.php
// - footer.php
// =============================================================================
defined('ABSPATH') || exit;

get_header();

?>
<main id="main-content" class="archive-page">
    <header class="header">
        <?php the_archive_title('<h1>', '</h1>'); ?>
        <?php the_archive_description('<div class="description">', '</div>'); ?>
    </header>

    <?php if (have_posts()) : ?>
        <div class="grid">
            <?php while (have_posts()) : the_post(); ?>
                <div <?php post_class('card'); ?>>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php the_excerpt(); ?>
                </div>
            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p>Aucun contenu trouvé.</p>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
