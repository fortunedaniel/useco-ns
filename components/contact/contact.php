<?php

// =============================================================================
// Fichier : components/contact/contact.php
// Rôle : affiche le composant de demande de contact
//
// Ce qu'il fait :
// - affiche un formulaire de contact sécurisé
// - gère les champs métier complémentaires du formulaire Contact
// - génère la liste des sujets depuis config/project.json
// - permet un rendu inline ou dans une modale
// - envoie les données au moteur commun ComKani Requests
// - expose un shortcode [comkani_contact_form]
//
// Fichiers liés :
// - config/project.json
// - packages/comkani-core/includes/requests.php
// - resources/js/core/modal.js
// - resources/scss/components/_forms.scss
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Render contact form : fonction du module contact.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_render_contact_form(array $args = []): void
{
    $args = wp_parse_args($args, [
        'title' => '',
        'context' => 'inline',
    ]);

    $id = wp_unique_id('comkani-contact-');
    $subjects = function_exists('comkani_contact_subject_options')
        ? comkani_contact_subject_options()
        : [];
    $organization_name = trim(
        (string) comkani_project_get(
            'organization.name',
            get_bloginfo('name')
        )
    );
    $submit_label = trim(
        (string) comkani_project_get(
            'contact.submitLabel',
            'Envoyer'
        )
    );
    $message_placeholder = sprintf(
        'Votre message pour l’équipe %s',
        $organization_name
    );

    ?>
    <div
        class="component"
        data-request-component="contact"
        data-form-context="<?php echo esc_attr((string) $args['context']); ?>"
    >
        <?php if ($args['title'] !== '') : ?>
            <h2><?php echo esc_html((string) $args['title']); ?></h2>
        <?php endif; ?>

        <?php comkani_request_status_message('contact'); ?>

        <form
            method="post"
            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            class="form"
        >
            <input type="hidden" name="action" value="comkani_submit_request">
            <input type="hidden" name="request_type" value="contact">
            <input
                type="hidden"
                name="form_started_at"
                value="<?php echo esc_attr((string) time()); ?>"
            >

            <?php wp_nonce_field('comkani_submit_contact', 'nonce'); ?>

            <?php
            // Protège le formulaire contre les soumissions automatisées
            ?>

            <div class="honeypot" aria-hidden="true">
                <label for="<?php echo esc_attr($id); ?>-website">Site web</label>
                <input
                    id="<?php echo esc_attr($id); ?>-website"
                    type="text"
                    name="website"
                    tabindex="-1"
                    autocomplete="off"
                >
            </div>

            <?php
            // Regroupe les informations d’identité
            ?>

            <div class="row row-two">
                <label for="<?php echo esc_attr($id); ?>-first-name">
                    <span>Prénom *</span>
                    <input
                        id="<?php echo esc_attr($id); ?>-first-name"
                        type="text"
                        name="client_first_name"
                        maxlength="80"
                        autocomplete="given-name"
                        placeholder="Votre prénom"
                        required
                    >
                </label>

                <label for="<?php echo esc_attr($id); ?>-last-name">
                    <span>Nom *</span>
                    <input
                        id="<?php echo esc_attr($id); ?>-last-name"
                        type="text"
                        name="client_last_name"
                        maxlength="80"
                        autocomplete="family-name"
                        placeholder="Votre nom"
                        required
                    >
                </label>
            </div>

            <div class="row row-two">
                <label for="<?php echo esc_attr($id); ?>-company">
                    <span>Entreprise</span>
                    <input
                        id="<?php echo esc_attr($id); ?>-company"
                        type="text"
                        name="client_company"
                        maxlength="160"
                        autocomplete="organization"
                        placeholder="Votre entreprise"
                    >
                </label>

                <label for="<?php echo esc_attr($id); ?>-position">
                    <span>Poste</span>
                    <input
                        id="<?php echo esc_attr($id); ?>-position"
                        type="text"
                        name="client_position"
                        maxlength="160"
                        autocomplete="organization-title"
                        placeholder="Votre poste"
                    >
                </label>
            </div>

            <?php
            // Regroupe les coordonnées de contact
            ?>

            <div class="row row-two">
                <label for="<?php echo esc_attr($id); ?>-phone">
                    <span>Téléphone</span>
                    <input
                        id="<?php echo esc_attr($id); ?>-phone"
                        type="tel"
                        name="client_phone"
                        maxlength="40"
                        autocomplete="tel"
                        placeholder="Votre numéro de téléphone"
                    >
                </label>

                <label for="<?php echo esc_attr($id); ?>-email">
                    <span>Adresse e-mail *</span>
                    <input
                        id="<?php echo esc_attr($id); ?>-email"
                        type="email"
                        name="client_email"
                        maxlength="254"
                        autocomplete="email"
                        placeholder="Votre adresse e-mail"
                        required
                    >
                </label>
            </div>

            <?php
            // Regroupe les informations liées à la demande
            ?>

            <label for="<?php echo esc_attr($id); ?>-subject">
                <span>Sujet *</span>
                <select
                    id="<?php echo esc_attr($id); ?>-subject"
                    name="subject"
                    required
                >
                    <option value="">Sélectionnez le sujet de votre demande</option>

                    <?php foreach ($subjects as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>">
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label for="<?php echo esc_attr($id); ?>-message">
                <span>Message *</span>
                <textarea
                    id="<?php echo esc_attr($id); ?>-message"
                    name="message"
                    rows="6"
                    maxlength="4000"
                    placeholder="<?php echo esc_attr($message_placeholder); ?>"
                    required
                ></textarea>
            </label>

            <?php
            // Regroupe le consentement et l’envoi du formulaire
            ?>

            <div class="submit">
                <button type="submit" class="button-primary">
                    <span><?php echo esc_html($submit_label); ?></span>
                </button>

                <label class="consent">
                    <input
                        type="checkbox"
                        name="privacy_consent"
                        value="1"
                        required
                    >
                    <span>
                        <?php
                        echo esc_html(
                            sprintf(
                                'J’accepte que mes données personnelles soient traitées par %s afin de répondre à ma demande de contact. *',
                                $organization_name
                            )
                        );
                        ?>
                    </span>
                </label>
            </div>
        </form>
    </div>
    <?php
}

/**
 * Contact shortcode : fonction du module contact.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_contact_shortcode(): string
{
    ob_start();
    comkani_render_contact_form();

    return (string) ob_get_clean();
}

add_shortcode(
    'comkani_contact_form',
    'comkani_contact_shortcode'
);

/**
 * Contact modal : fonction du module contact.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_contact_modal(): void
{
    comkani_render_component_modal(
        'contact',
        'Contact',
        static function (): void {
            comkani_render_contact_form([
                'title' => '',
                'context' => 'modal',
            ]);
        }
    );
}

if ((bool) comkani_project_get('contact.modal', true)) {
    add_action(
        'comkani_render_modals',
        'comkani_contact_modal'
    );
}
