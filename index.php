<?php

// =============================================================================
// Fichier : index.php
// Rôle : fournit le template de secours du thème WordPress
//
// Ce qu'il fait :
// - affiche les contenus lorsque aucun template plus spécifique n'existe
// - conserve la hiérarchie native des templates WordPress
//
// Fichiers liés :
// - header.php
// - footer.php
// =============================================================================
defined('ABSPATH') || exit;

get_header();

?>
<main id="main-content" class="page-content">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <div <?php post_class(); ?>>
                <h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
                <?php the_excerpt(); ?>
            </div>
        <?php endwhile; ?>

        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p>Aucun contenu trouvé.</p>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
