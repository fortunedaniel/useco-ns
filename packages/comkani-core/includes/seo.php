<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/seo.php
// Rôle : centraliser les règles SEO techniques complémentaires du Starter
//
// Objectif :
// - exposer uniquement des réglages SEO réellement utilisés par le thème
// - éviter les doublons avec Yoast SEO lorsqu’il prend déjà en charge un sujet
// - fournir des helpers simples aux composants qui génèrent des données Schema
//
// Ce qu’il fait :
// - indique si le socle SEO est activé
// - indique si un type de Schema est autorisé dans config/seo.json
// - détecte Yoast SEO
// - génère le Schema Organization minimal lorsque Yoast ne le fait pas
//
// Ce qu’il ne fait pas :
// - ne remplace pas Yoast SEO pour les titres, descriptions, canonicals ou sitemap
// - ne génère pas de contenu éditorial SEO automatiquement
// - ne déclare pas de fonctionnalités AEO/GEO fictives sans implémentation
// - ne contrôle pas directement l’indexation des environnements, gérée par security.php
//
// Fichiers liés :
// - config/seo.json
// - packages/comkani-core/includes/security.php
// - components/faq/faq.php
// - pages/services.php
// - pages/secteurs.php
// =============================================================================

defined('ABSPATH') || exit;

/**
 * Indique si le socle SEO ComKani est actif.
 *
 * @return bool
 */
function comkani_seo_enabled(): bool
{
    return (bool) comkani_core_get('seo.enabled', true);
}

/**
 * Indique si Yoast SEO est actuellement chargé.
 *
 * @return bool
 */
function comkani_seo_yoast_active(): bool
{
    return defined('WPSEO_VERSION');
}

/**
 * Indique si un type de Schema est autorisé pour le projet.
 *
 * @param string $type Clé définie dans seo.schema.
 * @return bool
 */
function comkani_seo_schema_enabled(string $type): bool
{
    if (!comkani_seo_enabled()) {
        return false;
    }

    if (!(bool) comkani_core_get('seo.schema.enabled', true)) {
        return false;
    }

    return (bool) comkani_core_get(
        'seo.schema.' . sanitize_key($type),
        false
    );
}

/**
 * Génère le Schema Organization minimal lorsque Yoast n’est pas actif.
 *
 * @return void
 */
function comkani_seo_output_organization_schema(): void
{
    if (
        !is_front_page()
        || !comkani_seo_schema_enabled('organization')
        || comkani_seo_yoast_active()
    ) {
        return;
    }

    $name = trim(
        (string) comkani_core_get(
            'organization.name',
            get_bloginfo('name')
        )
    );

    if ($name === '') {
        return;
    }

    $organization_type = trim(
        (string) comkani_core_get(
            'seo.organizationType',
            'Organization'
        )
    );

    $allowed_types = [
        'Organization',
        'Corporation',
        'LocalBusiness',
        'ProfessionalService',
    ];

    if (!in_array($organization_type, $allowed_types, true)) {
        $organization_type = 'Organization';
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => $organization_type,
        '@id' => home_url('/#organization'),
        'name' => $name,
        'url' => home_url('/'),
    ];

    $email = sanitize_email(
        (string) comkani_core_get('organization.email', '')
    );

    $phone = sanitize_text_field(
        (string) comkani_core_get('organization.phone', '')
    );

    $same_as = array_values(
        array_filter(
            array_map(
                'esc_url_raw',
                (array) comkani_core_get('organization.sameAs', [])
            )
        )
    );

    if ($email !== '') {
        $schema['email'] = $email;
    }

    if ($phone !== '') {
        $schema['telephone'] = $phone;
    }

    if ($same_as !== []) {
        $schema['sameAs'] = $same_as;
    }

    echo '<script type="application/ld+json">'
        . wp_json_encode(
            $schema,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        )
        . '</script>';
}
add_action('wp_head', 'comkani_seo_output_organization_schema', 30);
