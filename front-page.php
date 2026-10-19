<?php
// =============================================================================
// Fichier : front-page.php
// Rôle : afficher la page d’accueil depuis l’éditeur WordPress
//
// Ce qu’il fait :
// - affiche le titre et le contenu administrable de la page d’accueil
//
// Ce qu’il ne fait pas :
// - ne contient pas de contenu ni de mise en page d’un ancien projet
// =============================================================================
defined('ABSPATH') || exit;
get_header();
?>
<main id="main-content" class="site-main">
    <?php while (have_posts()) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <h1><?php the_title(); ?></h1>
            <?php the_content(); ?>
        </article>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
