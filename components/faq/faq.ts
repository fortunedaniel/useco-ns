// =============================================================================
// Fichier : components/faq/faq.ts
// Rôle : gérer l'expérience interactive du composant FAQ
//
// Ce qu'il fait :
// - autorise un seul élément ouvert par liste de FAQ
// - affiche d'abord trois questions puis révèle les questions supplémentaires
// - synchronise le bouton Voir plus / Voir moins et son aria-expanded
// - conserve le comportement natif details/summary sans dépendance externe
//
// Ce qu'il ne fait pas :
// - ne modifie pas les questions ou réponses enregistrées dans WordPress
// - ne dépend pas de la navigation horizontale ancien projet client
// - ne gère pas le Schema.org FAQ généré côté PHP
//
// Fichiers liés :
// - components/faq/faq.php
// - resources/scss/components/_faq.scss
// - resources/ts/main.ts
// =============================================================================

/**
 * Initialise une liste de FAQ.
 *
 * @param list Conteneur des questions
 * @returns Rien
 */
function initFaqList(list: Element): void {
    list.querySelectorAll<HTMLDetailsElement>('details').forEach((item) => {
        item.addEventListener('toggle', () => {
            if (!item.open) {
                return;
            }

            list.querySelectorAll<HTMLDetailsElement>('details[open]').forEach((opened) => {
                if (opened !== item) {
                    opened.open = false;
                }
            });
        });
    });

    const faq = list.closest<HTMLElement>('.faq');
    const button = faq?.querySelector<HTMLButtonElement>('[data-faq-more]');
    const extras = [...list.querySelectorAll<HTMLElement>('[data-faq-extra]')];

    if (!button || extras.length === 0) {
        return;
    }

    button.addEventListener('click', () => {
        const expanded = button.getAttribute('aria-expanded') === 'true';
        const nextExpanded = !expanded;

        extras.forEach((item) => {
            item.hidden = !nextExpanded;

            if (!nextExpanded && item instanceof HTMLDetailsElement) {
                item.open = false;
            }
        });

        list.classList.toggle('is-expanded', nextExpanded);
        button.setAttribute('aria-expanded', String(nextExpanded));
        button.textContent = nextExpanded ? 'Voir moins de questions' : 'Voir plus de questions';
    });
}

document.querySelectorAll('[data-faq-list]').forEach(initFaqList);

export {};
