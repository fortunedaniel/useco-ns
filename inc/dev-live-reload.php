<?php
/**
 * ComKani Starter : connexion au Live Reload uniquement en environnement Local.
 *
 * Injecte un écouteur d'événements fourni par `npm run dev` dans les pages et
 * l'administration. Aucune dépendance Node/Vite n'est chargée en production.
 *
 * @package ComKaniStarter
 * @file inc/dev-live-reload.php
 */
defined('ABSPATH') || exit;

/**
 * Injecte le client de rechargement dans le pied de page courant.
 *
 * Le client SSE ne transmet aucun contenu WordPress ; il écoute seulement
 * les changements signalés par le serveur lié à 127.0.0.1.
 *
 * @return void
 */
function comkani_dev_live_reload_client(): void
{
    if (wp_get_environment_type() !== 'local') {
        return;
    }
    ?>
    <script id="comkani-dev-live-reload">
    (() => {
        if (typeof EventSource === 'undefined') return;
        const stream = new EventSource('http://127.0.0.1:35731/events');
        let reloading = false;
        stream.addEventListener('reload', () => {
            if (reloading) return;
            reloading = true;
            stream.close();
            window.location.reload();
        });
    })();
    </script>
    <?php
}
add_action('wp_footer', 'comkani_dev_live_reload_client', 100);
add_action('admin_footer', 'comkani_dev_live_reload_client', 100);
