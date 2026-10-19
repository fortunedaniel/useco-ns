<?php

// =============================================================================
// Fichier : templates/legal/privacy-cookies.php
// Rôle : affiche la politique de confidentialité et de cookies du projet
//
// Ce qu'il fait :
// - décrit les traitements liés aux formulaires, rendez-vous et demandes l’organisation
// - récupère les coordonnées RGPD depuis config/src/project.ts
// - reprend les durées configurées pour les demandes et le consentement
// - précise que les outils de mesure d’audience restent soumis au consentement
// - ajoute le contenu WordPress lorsqu’un complément spécifique a été saisi
//
// Ce qu'il ne fait pas :
// - n'active aucun service de mesure d’audience
// - ne remplace pas la validation juridique finale du client
// - ne modifie aucune donnée personnelle
//
// Fichiers liés :
// - config/src/project.ts
// - config/src/features/forms.ts
// - config/src/features/consent.ts
// - templates/page-legal.php
// =============================================================================

defined('ABSPATH') || exit;

$organization = comkani_project_get('organization', []);
$organization = is_array($organization) ? $organization : [];

$company_name = trim((string) ($organization['legalName'] ?? $organization['name'] ?? 'l’organisation'));
$privacy_email = trim((string) ($organization['privacyEmail'] ?? $organization['email'] ?? ''));
$requests_retention_days = absint(comkani_project_get('requests.retentionDays', 365));
$consent_lifetime_days = absint(comkani_project_get('consent.cookieLifetimeDays', 180));

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
?>

<p>
    <?php echo esc_html($company_name); ?> accorde une importance particulière à la protection des données personnelles et s’engage
    à les traiter conformément à la réglementation applicable, notamment au Règlement général sur la protection des données (RGPD)
    et à la loi Informatique et Libertés.
</p>

<h2>Responsable du traitement</h2>
<p><strong><?php echo esc_html($company_name); ?></strong></p>

<?php if ($company_address !== '') : ?>
    <p><?php echo esc_html($company_address); ?></p>
<?php endif; ?>

<?php if ($privacy_email !== '') : ?>
    <p>
        <strong>Contact relatif aux données personnelles :</strong>
        <a href="mailto:<?php echo esc_attr($privacy_email); ?>"><?php echo esc_html($privacy_email); ?></a>
    </p>
<?php endif; ?>

<h2>Données susceptibles d’être collectées</h2>
<p>Selon votre utilisation du site, les données suivantes peuvent notamment être collectées :</p>
<ul>
    <li>nom et prénom</li>
    <li>adresse e-mail</li>
    <li>numéro de téléphone lorsque vous choisissez de le communiquer</li>
    <li>nom de l’entreprise et informations liées à votre activité</li>
    <li>objet et contenu de votre demande</li>
    <li>informations nécessaires à l’organisation d’un rendez-vous</li>
    <li>source de la demande et paramètres de campagne lorsqu’ils sont disponibles</li>
    <li>données techniques strictement nécessaires au fonctionnement et à la sécurité du site</li>
</ul>

<h2>Finalités des traitements</h2>
<ul>
    <li>répondre aux demandes adressées à l’organisation</li>
    <li>traiter les demandes de contact et de rappel</li>
    <li>organiser et gérer les demandes de rendez-vous</li>
    <li>assurer le suivi des échanges avec les prospects et clients</li>
    <li>sécuriser et maintenir le bon fonctionnement du site</li>
    <li>respecter les obligations légales et réglementaires applicables</li>
    <li>mesurer l’audience du site uniquement lorsqu’un outil concerné est activé et que le consentement requis a été obtenu</li>
</ul>

<h2>Bases légales</h2>
<p>
    Selon le traitement concerné, les données sont traitées sur le fondement du consentement, de mesures précontractuelles prises à
    votre demande, de l’exécution d’un contrat, du respect d’une obligation légale ou de l’intérêt légitime d’l’organisation lorsqu’il est applicable.
</p>

<h2>Caractère obligatoire des informations</h2>
<p>
    Les champs identifiés comme obligatoires dans les formulaires sont nécessaires au traitement de votre demande. Les autres informations
    sont facultatives, sauf indication contraire dans l’interface.
</p>

<h2>Destinataires des données</h2>
<p>
    Les données sont accessibles uniquement aux personnes habilitées chez l’organisation et aux prestataires techniques intervenant pour son compte
    lorsque cet accès est nécessaire à l’hébergement, à la maintenance, à la sécurité, au traitement des formulaires ou à la gestion des rendez-vous.
</p>
<p>Les données personnelles ne sont pas vendues à des tiers.</p>

<h2>Durée de conservation</h2>
<p>
    Les demandes transmises via le site sont conservées pendant une durée maximale configurée de
    <strong><?php echo esc_html((string) $requests_retention_days); ?> jours</strong>, sauf lorsqu’une durée différente est nécessaire pour
    l’exécution d’une relation contractuelle, le respect d’une obligation légale ou la défense des droits d’l’organisation.
</p>
<p>
    Le choix exprimé dans le module de consentement est conservé pendant
    <strong><?php echo esc_html((string) $consent_lifetime_days); ?> jours</strong> avant qu’un nouveau choix puisse être demandé.
</p>

<h2>Sécurité</h2>
<p>
    l’organisation met en œuvre des mesures techniques et organisationnelles destinées à protéger les données personnelles contre la perte,
    l’altération, la divulgation ou l’accès non autorisé. Le site utilise notamment une connexion HTTPS et des mesures de sécurité adaptées à son fonctionnement.
</p>

<h2>Vos droits</h2>
<p>Conformément à la réglementation applicable, vous pouvez notamment exercer les droits suivants :</p>
<ul>
    <li>droit d’accès</li>
    <li>droit de rectification</li>
    <li>droit à l’effacement lorsque les conditions sont réunies</li>
    <li>droit à la limitation du traitement</li>
    <li>droit d’opposition lorsque celui-ci est applicable</li>
    <li>droit à la portabilité lorsque celui-ci est applicable</li>
    <li>droit de retirer votre consentement à tout moment pour les traitements qui reposent sur celui-ci</li>
</ul>

<?php if ($privacy_email !== '') : ?>
    <p>
        Pour exercer vos droits, vous pouvez écrire à
        <a href="mailto:<?php echo esc_attr($privacy_email); ?>"><?php echo esc_html($privacy_email); ?></a>.
    </p>
<?php endif; ?>

<p>
    Vous disposez également du droit d’introduire une réclamation auprès de la Commission nationale de l’informatique et des libertés (CNIL).
</p>

<h2>Cookies et traceurs</h2>
<p>
    Le site utilise des cookies ou mécanismes équivalents strictement nécessaires à son fonctionnement, à sa sécurité et à la mémorisation
    de vos choix de confidentialité. Ces éléments nécessaires ne peuvent pas être désactivés depuis le module de consentement.
</p>
<p>
    Les outils de mesure d’audience non nécessaires restent désactivés tant qu’ils ne sont pas configurés et, lorsqu’un consentement est requis,
    tant que vous ne les avez pas autorisés.
</p>

<h2>Gestion de vos préférences</h2>
<p>
    Vous pouvez accepter, refuser ou personnaliser les catégories optionnelles depuis le bandeau affiché lors de votre première visite.
    Vous pouvez ensuite modifier votre choix à tout moment grâce au bouton « Gérer mes cookies » disponible dans le pied de page.
</p>

<h2>Services tiers</h2>
<p>
    Le site est hébergé par OVH SAS. Certaines fonctionnalités peuvent également nécessiter des services techniques tiers pour assurer
    le fonctionnement, la sécurité ou la transmission des demandes. Lorsqu’un service optionnel nécessite votre consentement, il n’est chargé
    qu’après l’obtention de celui-ci.
</p>

<h2>Transferts hors de l’Espace économique européen</h2>
<p>
    Lorsque l’utilisation d’un prestataire entraîne un transfert de données hors de l’Espace économique européen, l’organisation veille à ce que ce
    transfert repose sur un mécanisme prévu par la réglementation applicable et sur des garanties appropriées lorsque celles-ci sont nécessaires.
</p>

<h2>Évolution de la politique</h2>
<p>
    La présente politique peut être modifiée afin de tenir compte des évolutions réglementaires, techniques ou fonctionnelles du site.
</p>

<?php if (trim((string) get_the_content()) !== '') : ?>
    <div class="additional-content">
        <?php the_content(); ?>
    </div>
<?php endif; ?>
