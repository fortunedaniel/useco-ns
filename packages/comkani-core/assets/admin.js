// =============================================================================
// Fichier : packages/comkani-core/assets/admin.js
// Rôle : applique les interactions spécifiques du back-office ComKani
//
// Ce qu'il fait :
// - simplifie le profil de l'utilisateur connecté
// - simplifie aussi l'édition d'un autre compte par l'Agence ComKani
// - affiche uniquement les réglages autorisés selon le niveau d'accès
// - retire les panneaux Aide et Options de l'écran s'ils sont injectés tardivement
//
// Fichiers liés :
// - packages/comkani-core/includes/profile.php
// - packages/comkani-core/includes/admin.php
// - packages/comkani-core/assets/admin.css
// =============================================================================
/**
 * Gère comkaniRevealProfileRow dans le module ComKani.
 *
 * Les paramètres, effets secondaires et droits doivent être vérifiés
 * avec les appels du module lors de sa maintenance.
 */
function comkaniRevealProfileRow(form, selector) {
    const row = form.querySelector(selector);

    if (!row) {
        return;
    }

    row.hidden = false;

    const table = row.closest('.form-table');

    if (!table) {
        return;
    }

    table.hidden = false;

    const heading = table.previousElementSibling;

    if (heading && /^H[23]$/.test(heading.tagName)) {
        heading.hidden = false;
    }
}

/**
 * Gère comkaniRemoveScreenMeta dans le module ComKani.
 *
 * Les paramètres, effets secondaires et droits doivent être vérifiés
 * avec les appels du module lors de sa maintenance.
 */
function comkaniRemoveScreenMeta() {
    [
        'screen-meta',
        'screen-meta-links',
        'screen-options-link-wrap',
        'contextual-help-link-wrap',
    ].forEach((id) => {
        document.getElementById(id)?.remove();
    });
}

/**
 * Gère comkaniSimplifyProfile dans le module ComKani.
 *
 * Les paramètres, effets secondaires et droits doivent être vérifiés
 * avec les appels du module lors de sa maintenance.
 */
function comkaniSimplifyProfile() {
    const form = document.querySelector('#your-profile');

    if (!form || !document.body.classList.contains('comkani-profile-screen')) {
        return;
    }

    const isAgency = document.body.classList.contains('comkani-profile-agency');
    const isOtherUser = document.body.classList.contains('comkani-profile-edit-other');

    if (!isAgency) {
        Array.from(form.children).forEach((child) => {
            if (child.matches('input[type=hidden], .submit')) {
                return;
            }

            child.hidden = true;
        });
    }

    form.querySelectorAll('.form-table').forEach((table) => {
        table.hidden = true;

        table.querySelectorAll('tr').forEach((row) => {
            row.hidden = true;
        });

        const heading = table.previousElementSibling;

        if (heading && /^H[23]$/.test(heading.tagName)) {
            heading.hidden = true;
        }
    });

    form.querySelectorAll(':scope > h2, :scope > h3').forEach((heading) => {
        heading.hidden = true;
    });

    comkaniRevealProfileRow(form, '.user-email-wrap');
    comkaniRevealProfileRow(form, '.user-pass1-wrap');
    comkaniRevealProfileRow(form, '.user-pass2-wrap');
    comkaniRevealProfileRow(form, '.pw-weak');

    if (isAgency) {
        comkaniRevealProfileRow(form, '.user-admin-bar-front-wrap');
        if (isOtherUser) {
            comkaniRevealProfileRow(form, '.user-role-wrap');
        }
    }

    const applicationPasswords = form.querySelector('#application-passwords-section');

    if (applicationPasswords) {
        applicationPasswords.hidden = !isAgency;

        if (isAgency) {
            applicationPasswords.querySelectorAll('h2, h3').forEach((heading) => {
                heading.hidden = false;
            });
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    comkaniRemoveScreenMeta();
    comkaniSimplifyProfile();
});
