<?php

// =============================================================================
// Fichier : templates/legal/mentions.php
// Rôle : affiche les mentions légales du projet à partir de sa configuration
//
// Ce qu'il fait :
// - lit les informations juridiques centralisées dans config/src/project.ts
// - masque automatiquement les informations encore non renseignées
// - affiche les informations de l’hébergeur configuré pour le projet
// - ajoute le contenu WordPress uniquement lorsqu’un complément a été saisi
//
// Ce qu'il ne fait pas :
// - n'invente aucune donnée juridique manquante
// - ne modifie pas les données du projet
// - ne remplace pas la validation juridique finale du client
//
// Fichiers liés :
// - config/src/project.ts
// - templates/page-legal.php
// =============================================================================

defined('ABSPATH') || exit;

$organization = comkani_project_get('organization', []);
$organization = is_array($organization) ? $organization : [];

$professional = $organization['professionalRegistration'] ?? [];
$professional = is_array($professional) ? $professional : [];

$hosting = $organization['hosting'] ?? [];
$hosting = is_array($hosting) ? $hosting : [];

$company_name = trim((string) ($organization['legalName'] ?? $organization['name'] ?? ''));
$legal_form = trim((string) ($organization['legalForm'] ?? ''));
$capital = trim((string) ($organization['capital'] ?? ''));
$email = trim((string) ($organization['email'] ?? ''));
$phone = trim((string) ($organization['phone'] ?? ''));
$siren = trim((string) ($organization['siren'] ?? ''));
$siret = trim((string) ($organization['siret'] ?? ''));
$vat_number = trim((string) ($organization['vatNumber'] ?? ''));
$registration = trim((string) ($organization['registration'] ?? ''));
$publication_director = trim((string) ($organization['publicationDirector'] ?? ''));

/**
 * Assemble une adresse à partir de segments facultatifs.
 *
 * @param array<int, string> $parts Segments de l'adresse à assembler.
 * @return string Adresse finale sans segment vide.
 */
$format_address = static function (array $parts): string {
    $parts = array_values(array_filter(array_map(
        static fn(string $value): string => trim($value),
        $parts
    )));

    return implode(', ', $parts);
};

$company_address = $format_address([
    (string) ($organization['address'] ?? ''),
    trim((string) ($organization['postalCode'] ?? '') . ' ' . (string) ($organization['city'] ?? '')),
    (string) ($organization['country'] ?? ''),
]);

$hosting_name = trim((string) ($hosting['legalName'] ?? $hosting['name'] ?? ''));
$hosting_address = $format_address([
    (string) ($hosting['address'] ?? ''),
    trim((string) ($hosting['postalCode'] ?? '') . ' ' . (string) ($hosting['city'] ?? '')),
    (string) ($hosting['country'] ?? ''),
]);
$hosting_phone = trim((string) ($hosting['phone'] ?? ''));
$hosting_url = trim((string) ($hosting['url'] ?? ''));

$professional_organization = trim((string) ($professional['organization'] ?? ''));
$professional_region = trim((string) ($professional['region'] ?? ''));
$professional_number = trim((string) ($professional['number'] ?? ''));
?>

<h2>Éditeur du site</h2>

<?php if ($company_name !== '') : ?>
    <p><strong><?php echo esc_html($company_name); ?></strong></p>
<?php endif; ?>

<?php if ($legal_form !== '' || $capital !== '') : ?>
    <p>
        <?php if ($legal_form !== '') : ?><?php echo esc_html($legal_form); ?><?php endif; ?>
        <?php if ($capital !== '') : ?><?php echo $legal_form !== '' ? ' au capital de ' : ''; ?><?php echo esc_html($capital); ?><?php endif; ?>
    </p>
<?php endif; ?>

<?php if ($company_address !== '') : ?>
    <p><strong>Siège social :</strong> <?php echo esc_html($company_address); ?></p>
<?php endif; ?>

<?php if ($siren !== '') : ?>
    <p><strong>SIREN :</strong> <?php echo esc_html($siren); ?></p>
<?php endif; ?>

<?php if ($siret !== '') : ?>
    <p><strong>SIRET :</strong> <?php echo esc_html($siret); ?></p>
<?php endif; ?>

<?php if ($registration !== '') : ?>
    <p><strong>Immatriculation :</strong> <?php echo esc_html($registration); ?></p>
<?php endif; ?>

<?php if ($vat_number !== '') : ?>
    <p><strong>TVA intracommunautaire :</strong> <?php echo esc_html($vat_number); ?></p>
<?php endif; ?>

<?php if ($email !== '') : ?>
    <p>
        <strong>E-mail :</strong>
        <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
    </p>
<?php endif; ?>

<?php if ($phone !== '') : ?>
    <p><strong>Téléphone :</strong> <?php echo esc_html($phone); ?></p>
<?php endif; ?>

<?php if ($publication_director !== '') : ?>
    <h2>Direction de la publication</h2>
    <p>La direction de la publication est assurée par <?php echo esc_html($publication_director); ?>.</p>
<?php endif; ?>

<h2>Activité réglementée</h2>
<p>
    L’activité d’expertise comptable est notamment encadrée par l’ordonnance n° 45-2138 du 19 septembre 1945 et par les règles professionnelles applicables.
</p>

<?php if ($professional_organization !== '') : ?>
    <p><strong>Organisme professionnel :</strong> <?php echo esc_html($professional_organization); ?></p>
<?php endif; ?>

<?php if ($professional_region !== '') : ?>
    <p><strong>Région :</strong> <?php echo esc_html($professional_region); ?></p>
<?php endif; ?>

<?php if ($professional_number !== '') : ?>
    <p><strong>Numéro d’inscription :</strong> <?php echo esc_html($professional_number); ?></p>
<?php endif; ?>

<h2>Conception et réalisation</h2>
<p>
    Le site a été conçu et développé par
    <a href="https://www.comkani.fr/" target="_blank" rel="noopener noreferrer">Agence ComKani</a>.
</p>

<?php if ($hosting_name !== '' || $hosting_address !== '') : ?>
    <h2>Hébergement</h2>

    <?php if ($hosting_name !== '') : ?>
        <p><strong><?php echo esc_html($hosting_name); ?></strong></p>
    <?php endif; ?>

    <?php if ($hosting_address !== '') : ?>
        <p><?php echo esc_html($hosting_address); ?></p>
    <?php endif; ?>

    <?php if ($hosting_phone !== '') : ?>
        <p><strong>Téléphone :</strong> <?php echo esc_html($hosting_phone); ?></p>
    <?php endif; ?>

    <?php if ($hosting_url !== '') : ?>
        <p>
            <a href="<?php echo esc_url($hosting_url); ?>" target="_blank" rel="noopener noreferrer">Site de l’hébergeur</a>
        </p>
    <?php endif; ?>
<?php endif; ?>

<h2>Propriété intellectuelle</h2>
<p>
    L’ensemble des éléments présents sur ce site, notamment les textes, photographies, illustrations, éléments graphiques,
    logos, icônes, vidéos, animations, structure et code, est protégé par les dispositions applicables en matière de propriété intellectuelle.
</p>
<p>
    Sauf mention contraire, ces éléments sont la propriété de <?php echo esc_html($company_name !== '' ? $company_name : 'l’organisation'); ?>
    ou sont utilisés avec l’autorisation de leurs titulaires respectifs. Toute reproduction, représentation, adaptation ou exploitation,
    totale ou partielle, sans autorisation préalable est interdite, sous réserve des exceptions prévues par la législation applicable.
</p>

<h2>Responsabilité</h2>
<p>
    <?php echo esc_html($company_name !== '' ? $company_name : 'l’organisation'); ?> s’efforce de fournir des informations exactes et à jour.
    Les informations présentées sur le site sont toutefois fournies à titre général et informatif.
</p>
<p>
    Elles ne constituent pas, à elles seules, un conseil comptable, fiscal, juridique, social ou financier personnalisé et ne sauraient
    se substituer à une analyse tenant compte de la situation particulière de chaque utilisateur.
</p>

<h2>Données personnelles et cookies</h2>
<p>
    Les modalités relatives au traitement des données personnelles et à l’utilisation des cookies sont détaillées dans la page
    « Cookies &amp; confidentialité » du site.
</p>

<?php if (trim((string) get_the_content()) !== '') : ?>
    <div class="additional-content">
        <?php the_content(); ?>
    </div>
<?php endif; ?>
