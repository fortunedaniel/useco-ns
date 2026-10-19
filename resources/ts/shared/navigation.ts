// =============================================================================
// Fichier : resources/ts/shared/navigation.ts
// Rôle : gérer la navigation mobile globale du thème ComKani
//
// Ce qu'il fait :
// - ferme le menu mobile via son bouton de fermeture
// - ferme le menu après activation d'un lien
// - synchronise la classe du body avec l'état du details natif
//
// Ce qu'il ne fait pas :
// - ne construit pas le menu WordPress
// - ne pilote pas l'animation desktop du header
// - ne modifie pas les destinations des liens
//
// Fichiers liés :
// - header.php
// - resources/scss/components/_header.scss
// - resources/ts/layout/header-scroll.ts
// =============================================================================

/**
 * Initialise le menu mobile lorsqu'il est présent dans le DOM.
 *
 * @returns Rien
 */
function initMobileNavigation(): void {
    const navigation = document.querySelector<HTMLDetailsElement>('[data-mobile-navigation]');

    if (!navigation) {
        return;
    }

    const closeButton = navigation.querySelector('[data-mobile-navigation-close]');
    const links = navigation.querySelectorAll('a');

    /**
     * Synchronise la classe globale utilisée pour bloquer ou adapter la page.
     *
     * @returns Rien
     */
    function syncState(): void {
        document.body.classList.toggle('is-mobile-menu-open', navigation.open);
    }

    closeButton?.addEventListener('click', () => {
        navigation.open = false;
        syncState();
    });

    links.forEach((link) => {
        link.addEventListener('click', () => {
            navigation.open = false;
            syncState();
        });
    });

    navigation.addEventListener('toggle', syncState);
}

initMobileNavigation();

export {};
