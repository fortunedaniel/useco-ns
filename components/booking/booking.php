<?php

// =============================================================================
// Fichier : components/booking/booking.php
// Rôle : affiche le composant de prise de rendez-vous
//
// Ce qu'il fait :
// - affiche une date et les créneaux configurés pour le projet
// - vérifie les disponibilités via l'API REST ComKani
// - envoie les réservations au moteur commun ComKani Requests
// - expose un shortcode [comkani_booking_form]
//
// Ce qu'il ne fait pas :
// - ne gère pas directement le stockage des demandes
// - ne calcule pas les créneaux disponibles côté navigateur
//
// Fichiers liés :
// - packages/comkani-core/includes/requests.php
// - components/booking/booking.ts
// - resources/scss/components/_booking.scss
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Affiche le formulaire de prise de rendez-vous.
 *
 * @param array<string, mixed> $args Paramètres de rendu du composant.
 * @return void
 */
function comkani_render_booking_form(array $args = []): void
{
    $args = wp_parse_args($args, ['title' => 'Prendre rendez-vous']);
    $id = wp_unique_id('comkani-booking-');
    $max_days = max(1, (int) comkani_project_get('booking.maxDaysAhead', 60));
    $min_date = wp_date('Y-m-d');
    $max_date = wp_date('Y-m-d', current_datetime()->modify('+' . $max_days . ' days')->getTimestamp());

    ?>
    <div class="booking component" data-request-component="booking">
        <?php if ($args['title'] !== '') : ?>
            <div class="intro">
                <p class="eyebrow">Rendez-vous</p>
                <h2><?php echo esc_html((string) $args['title']); ?></h2>
                <p class="lead">Choisissez la date qui vous convient puis renseignez vos coordonnées.</p>
            </div>
        <?php endif; ?>

        <?php comkani_request_status_message('booking'); ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="form" data-booking-form>
            <input type="hidden" name="action" value="comkani_submit_request">
            <input type="hidden" name="request_type" value="booking">
            <input type="hidden" name="form_started_at" value="<?php echo esc_attr((string) time()); ?>">
            <?php wp_nonce_field('comkani_submit_booking', 'nonce'); ?>

            <div class="honeypot" aria-hidden="true">
                <label for="<?php echo esc_attr($id); ?>-website">Site web</label>
                <input id="<?php echo esc_attr($id); ?>-website" type="text" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="choices">
                <label>
                    <span>Date *</span>
                    <input type="date" name="booking_date" min="<?php echo esc_attr($min_date); ?>" max="<?php echo esc_attr($max_date); ?>" required data-booking-date>
                </label>

                <label>
                    <span>Créneau *</span>
                    <select name="booking_time" required data-booking-time disabled>
                        <option value="">Choisissez d’abord une date</option>
                    </select>
                </label>
            </div>

            <p class="status" data-booking-status aria-live="polite"></p>

            <div class="row row-two">
                <label>
                    <span>Nom et prénom *</span>
                    <input type="text" name="client_name" maxlength="120" autocomplete="name" required>
                </label>

                <label>
                    <span>E-mail *</span>
                    <input type="email" name="client_email" maxlength="254" autocomplete="email" required>
                </label>
            </div>

            <label>
                <span>Téléphone</span>
                <input type="tel" name="client_phone" maxlength="40" autocomplete="tel">
            </label>

            <label>
                <span>Message</span>
                <textarea name="message" rows="4" maxlength="4000"></textarea>
            </label>

            <div class="submit">
                <button type="submit" class="button-primary"><span>Demander ce rendez-vous</span></button>

                <label class="consent">
                    <input type="checkbox" name="privacy_consent" value="1" required>
                    <span>J’accepte que mes informations soient utilisées pour traiter ma demande de rendez-vous. *</span>
                </label>
            </div>
        </form>
    </div>
    <?php
}

/**
 * Retourne le formulaire Booking pour le shortcode WordPress.
 *
 * @return string HTML du formulaire.
 */
function comkani_booking_shortcode(): string
{
    ob_start();
    comkani_render_booking_form();

    return (string) ob_get_clean();
}

add_shortcode('comkani_booking_form', 'comkani_booking_shortcode');

/**
 * Ajoute la modale Booking au rendu global des modales ComKani.
 *
 * @return void
 */
function comkani_booking_modal(): void
{
    comkani_render_component_modal('booking', 'Prendre rendez-vous', static function (): void {
        comkani_render_booking_form(['title' => '']);
    });
}

add_action('comkani_render_modals', 'comkani_booking_modal');
