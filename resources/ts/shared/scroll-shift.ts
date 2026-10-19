// =============================================================================
// Fichier : resources/ts/shared/scroll-shift.ts
// Rôle : appliquer un décalage vertical léger piloté par le scroll
//
// Ce qu'il fait :
// - cible uniquement les éléments portant data-scroll-shift
// - calcule une progression normalisée dans le viewport
// - expose le déplacement via la variable CSS --scroll-shift-y
// - limite les calculs à une frame avec requestAnimationFrame
//
// Ce qu'il ne fait pas :
// - ne crée pas de timeline complexe
// - ne bloque jamais le scroll utilisateur
// - n'est pas chargé par défaut si aucun module ne l'importe
// =============================================================================

/**
 * Contraint une valeur numérique entre deux bornes.
 *
 * @param value Valeur à contraindre
 * @param min Borne minimale
 * @param max Borne maximale
 * @returns Valeur comprise entre min et max
 */
function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

/**
 * Initialise le décalage de scroll partagé.
 *
 * @returns Rien
 */
function initScrollShift(): void {
    const elements = document.querySelectorAll<HTMLElement>('[data-scroll-shift]');

    if (elements.length === 0) {
        return;
    }

    let ticking = false;

    /**
     * Recalcule la variable CSS de chaque élément observé.
     *
     * @returns Rien
     */
    function update(): void {
        const viewportHeight = window.innerHeight;

        elements.forEach((element) => {
            const rect = element.getBoundingClientRect();
            const distance = Number(element.dataset.scrollShift) || 90;
            const progress = clamp(
                (viewportHeight - rect.top) / (viewportHeight + rect.height),
                0,
                1,
            );

            element.style.setProperty('--scroll-shift-y', `${progress * distance}px`);
        });

        ticking = false;
    }

    /**
     * Programme un recalcul au prochain rafraîchissement navigateur.
     *
     * @returns Rien
     */
    function requestUpdate(): void {
        if (ticking) {
            return;
        }

        ticking = true;
        window.requestAnimationFrame(update);
    }

    update();
    window.addEventListener('scroll', requestUpdate, {passive: true});
    window.addEventListener('resize', requestUpdate);
}

initScrollShift();

export {};
