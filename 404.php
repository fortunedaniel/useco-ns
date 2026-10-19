<?php

// =============================================================================
// Fichier : 404.php
// Rôle : affiche la page d'erreur 404 du site
//
// Ce qu'il fait :
// - informe que la ressource demandée est introuvable
// - propose un retour vers l'accueil
// - conserve une structure accessible et légère
//
// Fichiers liés :
// - header.php
// - footer.php
// - resources/scss/pages/_404.scss
// =============================================================================
defined('ABSPATH') || exit;

get_header();

?>
<main id="main-content" class="error-page">
    <p class="error-code" aria-hidden="true">404</p>
    <h1>Cette page est introuvable.</h1>
    <p>Le contenu demandé a peut-être été déplacé ou supprimé.</p>
    <a class="button-primary" href="<?php echo esc_url(home_url('/')); ?>">
        Retour à l'accueil
    </a>
</main>
<?php get_footer(); ?>
