// =============================================================================
// Fichier : resources/ts/shared/motion.ts
// Rôle : gérer les animations d'apparition génériques du thème
//
// Ce qu'il fait :
// - observe uniquement les éléments portant data-animate
// - ajoute is-visible lorsqu'un élément entre dans le viewport
// - respecte prefers-reduced-motion
// - fonctionne sans librairie d'animation
//
// Ce qu'il ne fait pas :
// - ne pilote pas les timelines complexes de la page Accueil
// - ne remplace pas GSAP lorsqu'une séquence coordonnée le justifie
// - ne modifie pas le contenu WordPress
//
// Fichiers liés :
// - resources/scss/base/_animations.scss
// - resources/ts/main.ts
// =============================================================================

/**
 * Initialise les apparitions génériques du thème.
 *
 * @returns Rien
 */
function initMotion(): void {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const animatedElements = document.querySelectorAll('[data-animate]');

    if (reducedMotion) {
        animatedElements.forEach((element) => element.classList.add('is-visible'));
        return;
    }

    if (!('IntersectionObserver' in window)) {
        animatedElements.forEach((element) => element.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, {threshold: 0.12});

    animatedElements.forEach((element) => observer.observe(element));
}

initMotion();

export {};
