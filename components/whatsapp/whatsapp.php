<?php

// =============================================================================
// Fichier : components/whatsapp/whatsapp.php
// Rôle : affiche le bouton flottant WhatsApp et son formulaire de contact rapide
//
// Ce qu'il fait :
// - affiche un bouton flottant aux couleurs le responsable du site
// - ouvre une modale native via le moteur ComKani
// - permet au visiteur de demander une réponse par WhatsApp ou par e-mail
// - enregistre la demande dans le flux Contact existant
//
// Ce qu'il ne fait pas :
// - n'ouvre pas directement une conversation WhatsApp externe
// - ne remplace pas le formulaire Contact principal
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Affiche le formulaire de contact rapide WhatsApp.
 *
 * @return void
 */
function comkani_starter_render_whatsapp_form(): void
{
    $id = wp_unique_id('comkani-whatsapp-');
    ?>
    <div class="whatsapp-form">
        <div class="intro">
            <p class="eyebrow">Un échange rapide ?</p>
            <h2>Écrivez-nous</h2>
            <p>le responsable du site vous répond au plus vite, selon le canal que vous préférez.</p>
        </div>

        <?php comkani_request_status_message('contact'); ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="form">
            <input type="hidden" name="action" value="comkani_submit_request">
            <input type="hidden" name="request_type" value="contact">
            <input type="hidden" name="contact_variant" value="whatsapp">
            <input type="hidden" name="subject" value="autre-demande">
            <input type="hidden" name="form_started_at" value="<?php echo esc_attr((string) time()); ?>">
            <?php wp_nonce_field('comkani_submit_contact', 'nonce'); ?>

            <div class="honeypot" aria-hidden="true">
                <label for="<?php echo esc_attr($id); ?>-website">Site web</label>
                <input id="<?php echo esc_attr($id); ?>-website" type="text" name="website" tabindex="-1" autocomplete="off">
            </div>

            <label for="<?php echo esc_attr($id); ?>-first-name">
                <span>Prénom *</span>
                <input id="<?php echo esc_attr($id); ?>-first-name" type="text" name="client_first_name" maxlength="80" autocomplete="given-name" required>
            </label>

            <div class="row row-two">
                <label for="<?php echo esc_attr($id); ?>-email">
                    <span>E-mail</span>
                    <input id="<?php echo esc_attr($id); ?>-email" type="email" name="client_email" maxlength="254" autocomplete="email">
                </label>

                <label for="<?php echo esc_attr($id); ?>-phone">
                    <span>Téléphone</span>
                    <input id="<?php echo esc_attr($id); ?>-phone" type="tel" name="client_phone" maxlength="40" autocomplete="tel">
                </label>
            </div>

            <p class="helper">Indiquez au moins un e-mail ou un téléphone pour qu’le responsable du site puisse vous répondre.</p>

            <fieldset class="preference">
                <legend>Comment préférez-vous être recontacté(e) ?</legend>
                <div class="choices">
                    <label><input type="radio" name="contact_preference" value="whatsapp" checked> <span>WhatsApp</span></label>
                    <label><input type="radio" name="contact_preference" value="email"> <span>E-mail</span></label>
                </div>
            </fieldset>

            <label for="<?php echo esc_attr($id); ?>-message">
                <span>Votre message *</span>
                <textarea id="<?php echo esc_attr($id); ?>-message" name="message" rows="5" maxlength="4000" required></textarea>
            </label>

            <label class="consent">
                <input type="checkbox" name="privacy_consent" value="1" required>
                <span>J’accepte que mes informations soient utilisées par le responsable du site pour me recontacter au sujet de ma demande. *</span>
            </label>

            <button type="submit" class="button-primary">Envoyer</button>
        </form>
    </div>
    <?php
}

/**
 * Affiche la modale WhatsApp dans la zone globale des modales.
 *
 * @return void
 */
function comkani_starter_render_whatsapp_modal(): void
{
    comkani_render_component_modal(
        'whatsapp',
        'Écrire à le responsable du site',
        'comkani_starter_render_whatsapp_form'
    );
}
add_action('comkani_render_modals', 'comkani_starter_render_whatsapp_modal');

/**
 * Affiche le bouton flottant permettant d'ouvrir la modale WhatsApp.
 *
 * @return void
 */
function comkani_starter_render_whatsapp_button(): void
{
    $icon_url = get_theme_file_uri('/assets/icons/whatsapp.svg');

    ?>
    <button
        type="button"
        class="whatsapp-button"
        data-comkani-modal-open="whatsapp"
        aria-label="Écrire à le responsable du site via WhatsApp"
    >
        <img
            src="<?php echo esc_url($icon_url); ?>"
            alt=""
            aria-hidden="true"
        >
    </button>
    <?php
}

add_action('wp_footer', 'comkani_starter_render_whatsapp_button', 2);
