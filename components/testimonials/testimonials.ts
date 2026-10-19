// =============================================================================
// Fichier : components/testimonials/testimonials.ts
// Rôle : gérer la navigation du composant Témoignages
//
// Ce qu'il fait :
// - change le témoignage actif au clic sur un point de pagination
// - synchronise l'état visuel et aria-pressed de la pagination
// - masque les témoignages inactifs avec l'attribut hidden
//
// Ce qu'il ne fait pas :
// - ne récupère pas les témoignages depuis WordPress
// - ne lance pas de défilement automatique
// - ne dépend pas d'une bibliothèque de slider
//
// Fichiers liés :
// - components/testimonials/testimonials.php
// - resources/scss/components/_testimonials.scss
// - resources/ts/main.ts
// =============================================================================

/**
 * Initialise un composant Témoignages.
 *
 * @param slider Conteneur principal du composant
 * @returns Rien
 */
function initTestimonials(slider: Element): void {
    const testimonials = [...slider.querySelectorAll<HTMLElement>('[data-testimonial]')];
    const dots = [...slider.querySelectorAll<HTMLButtonElement>('[data-testimonial-dot]')];

    /**
     * Affiche un témoignage et synchronise les points de pagination.
     *
     * @param index Index du témoignage à afficher
     * @returns Rien
     */
    function show(index: number): void {
        testimonials.forEach((testimonial, current) => {
            const active = current === index;
            testimonial.classList.toggle('is-active', active);
            testimonial.hidden = !active;
        });

        dots.forEach((dot, current) => {
            const active = current === index;
            dot.classList.toggle('is-active', active);
            dot.setAttribute('aria-pressed', String(active));
        });
    }

    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            show(Number.parseInt(dot.dataset.testimonialDot || '0', 10));
        });
    });
}

document.querySelectorAll('[data-testimonials]').forEach(initTestimonials);

export {};
