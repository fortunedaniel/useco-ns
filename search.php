<?php

// =============================================================================
// Fichier : search.php
// Rôle : affiche les résultats de recherche WordPress
//
// Ce qu'il fait :
// - affiche le terme recherché
// - liste les contenus correspondant à la recherche
// - affiche la pagination native WordPress
//
// Fichiers liés :
// - header.php
// - footer.php
// =============================================================================
defined('ABSPATH') || exit;

get_header();

?>
<main id="main-content" class="search-page">
    <h1>
        Résultats pour « <?php echo esc_html(get_search_query()); ?> »
    </h1>

    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <div <?php post_class('search-result'); ?>>
                <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                <?php the_excerpt(); ?>
            </div>
        <?php endwhile; ?>

        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p>Aucun résultat trouvé.</p>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
