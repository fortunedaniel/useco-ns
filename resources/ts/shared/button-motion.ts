// =============================================================================
// Fichier : resources/ts/shared/button-motion.ts
// Rôle : applique l'animation de libellé aux boutons du thème
//
// Ce qu'il fait :
// - prépare les boutons primary, secondary et tertiary pour l'animation hover
// - conserve le libellé accessible et le duplique via data-label pour le CSS
// - évite de retraiter un bouton déjà initialisé
//
// Ce qu'il ne fait pas :
// - ne change pas la destination des liens ou le comportement des formulaires
// - ne modifie pas les couleurs des boutons
//
// Fichiers liés :
// - resources/scss/components/_buttons.scss
// - resources/ts/main.ts
// =============================================================================

const selector = '.button-primary, .button-secondary, .button-tertiary';

/**
 * Prépare un bouton pour l'animation verticale de son libellé.
 *
 * @param button Bouton ou lien utilisant une classe de bouton ComKani
 * @returns Rien
 */
function prepareButton(button: Element): void {
    if (!(button instanceof HTMLElement) || button.dataset.buttonMotion === 'ready') {
        return;
    }

    const label = button.textContent?.trim() ?? '';

    if (label === '') {
        return;
    }

    const children = [...button.children];
    const singleTextSpan = children.length === 1
        && children[0] instanceof HTMLSpanElement;

    if (singleTextSpan) {
        children[0].classList.add('button-label');
    } else if (children.length === 0) {
        button.textContent = '';

        const span = document.createElement('span');
        span.className = 'button-label';
        span.textContent = label;
        button.append(span);
    }

    button.dataset.label = label;
    button.dataset.buttonMotion = 'ready';
}

/**
 * Initialise l'animation sur tous les boutons présents dans le document.
 *
 * @returns Rien
 */
function initButtonMotion(): void {
    document.querySelectorAll(selector).forEach(prepareButton);
}

initButtonMotion();

export {};
