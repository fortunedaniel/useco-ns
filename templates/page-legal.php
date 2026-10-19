<?php
/**
 * Template Name: Page légale ComKani
 */

// =============================================================================
// Fichier : templates/page-legal.php
// Rôle : fournit le cadre commun des pages légales du projet
//
// Ce qu'il fait :
// - identifie la page légale affichée
// - charge le contenu légal standard correspondant
// - utilise le contenu WordPress comme solution de repli
// - conserve une structure unique pour toutes les pages légales ComKani
//
// Ce qu'il ne fait pas :
// - ne contient pas directement les textes juridiques
// - ne modifie pas les données du projet
// - ne crée ni ne supprime de page WordPress
//
// Fichiers liés :
// - templates/legal/mentions.php
// - templates/legal/privacy-cookies.php
// - config/src/project.ts
// - config/src/wordpress.ts
// - resources/scss/pages/_page-legal.scss
// =============================================================================

defined('ABSPATH') || exit;

get_header();

$page_id = (int) get_queried_object_id();
$legal_key = sanitize_key((string) get_post_meta($page_id, '_comkani_required_legal_page', true));
$current_slug = sanitize_title((string) get_post_field('post_name', $page_id));

$legal_templates = [
    'mentions' => 'mentions',
    'privacyCookies' => 'privacy-cookies',
    'cgv' => 'cgv',
    'cgu' => 'cgu',
    'shippingReturns' => 'shipping-returns',
    'refund' => 'refund',
    'bookingTerms' => 'booking-terms',
];

$slug_fallbacks = [
    'mentions-legales' => 'mentions',
    'confidentialite-cookies' => 'privacyCookies',
    'politique-de-confidentialite' => 'privacyCookies',
    'conditions-generales-de-vente' => 'cgv',
    'conditions-generales-utilisation' => 'cgu',
    'livraison-retours' => 'shippingReturns',
    'politique-remboursement' => 'refund',
    'conditions-reservation' => 'bookingTerms',
];

if ($legal_key === '' && isset($slug_fallbacks[$current_slug])) {
    $legal_key = $slug_fallbacks[$current_slug];
}

$legal_template = $legal_templates[$legal_key] ?? '';
$legal_template_path = $legal_template !== ''
    ? get_theme_file_path('/templates/legal/' . $legal_template . '.php')
    : '';
?>

<main id="main-content" class="main legal-page">
    <?php while (have_posts()) : the_post(); ?>
        <div class="content">
            <h1><?php the_title(); ?></h1>

            <?php if ($legal_template_path !== '' && is_readable($legal_template_path)) : ?>
                <?php get_template_part('templates/legal/' . $legal_template); ?>
            <?php else : ?>
                <?php the_content(); ?>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>
