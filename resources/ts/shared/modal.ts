// =============================================================================
// Fichier : resources/ts/shared/modal.ts
// Rôle : gérer les modales natives dialog utilisées par les composants ComKani
//
// Ce qu'il fait :
// - ouvre une modale depuis un élément data-comkani-modal-open
// - ferme la modale depuis son bouton, son backdrop ou le comportement Escape natif
// - centralise une interaction partagée par Booking et d'autres composants
//
// Ce qu'il ne fait pas :
// - ne définit pas le contenu des modales
// - ne gère pas l'envoi des formulaires
// - ne remplace pas les styles du composant dialog
//
// Fichiers liés :
// - inc/component-helpers.php
// - resources/scss/components/_modal.scss
// - resources/ts/main.ts
// =============================================================================

/**
 * Ouvre la modale ciblée par un déclencheur.
 *
 * @param opener Élément portant data-comkani-modal-open
 * @returns Rien
 */
function initModalOpener(opener: Element): void {
    opener.addEventListener('click', () => {
        const id = opener.getAttribute('data-comkani-modal-open');
        const dialog = id ? document.querySelector(`[data-comkani-modal="${id}"]`) : null;

        if (dialog instanceof HTMLDialogElement) {
            dialog.showModal();
        }
    });
}

/**
 * Initialise les comportements de fermeture d'une modale native.
 *
 * @param dialog Élément dialog à initialiser
 * @returns Rien
 */
function initDialog(dialog: HTMLDialogElement): void {
    const close = dialog.querySelector('[data-comkani-modal-close]');

    close?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
}

document.querySelectorAll('[data-comkani-modal-open]').forEach(initModalOpener);
document.querySelectorAll<HTMLDialogElement>('[data-comkani-modal]').forEach(initDialog);

export {};
