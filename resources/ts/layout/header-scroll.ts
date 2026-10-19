// =============================================================================
// Fichier : resources/ts/layout/header-scroll.ts
// Rôle : anime le header du thème et synchronise son état avec les sections
//
// Ce qu'il fait :
// - passe la navigation verticale en navigation horizontale à partir de Lifecycle
// - anime le déplacement des liens avec une transition FLIP
// - inverse le logo, les liens et le bouton sur les sections déclarées sombres
// - maintient le lien de navigation actif selon la section affichée
//
// Ce qu'il ne fait pas :
// - ne modifie pas les menus WordPress
// - ne pilote pas le menu mobile
// - ne bloque pas le scroll
// =============================================================================

const desktop = window.matchMedia('(min-width: 1200px)');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

type NavigationTarget = {
    link: HTMLAnchorElement;
    target: HTMLElement;
};

/**
 * Retourne l'identifiant d'ancre contenu dans une URL de navigation.
 *
 * @param href URL du lien WordPress
 * @returns Identifiant sans le caractère # ou chaîne vide
 */
function getHash(href: string): string {
    try {
        return new URL(href, window.location.href).hash.replace('#', '');
    } catch {
        return '';
    }
}

/**
 * Construit la liste des liens du header associés à une section réelle.
 *
 * @param header En-tête global
 * @returns Liens et sections correspondantes
 */
function getNavigationTargets(header: HTMLElement): NavigationTarget[] {
    return [...header.querySelectorAll<HTMLAnchorElement>('.navigation .links a')]
        .map((link) => {
            const hash = getHash(link.href);
            const target = hash !== '' ? document.getElementById(hash) : null;
            return target ? {link, target} : null;
        })
        .filter((item): item is NavigationTarget => item !== null);
}

/**
 * Initialise le changement d'état du header desktop.
 *
 * @param header En-tête global du site
 * @returns Rien
 */
function initHeaderScroll(header: Element): void {
    if (!(header instanceof HTMLElement)) {
        return;
    }

    const lifecycle = document.querySelector<HTMLElement>('.lifecycle');
    const items = [...header.querySelectorAll<HTMLElement>('.navigation .links > li')];
    const navigationTargets = getNavigationTargets(header);

    if (!lifecycle) {
        return;
    }

    let ticking = false;
    let compact = header.classList.contains('is-compact');

    /**
     * Bascule l'état du header et anime chaque lien entre ses deux positions.
     *
     * @param nextCompact Nouvel état compact du header
     * @returns Rien
     */
    const setCompact = (nextCompact: boolean): void => {
        if (nextCompact === compact) {
            return;
        }

        const before = items.map((item) => item.getBoundingClientRect());

        header.classList.toggle('is-compact', nextCompact);
        compact = nextCompact;

        if (reducedMotion.matches) {
            return;
        }

        const after = items.map((item) => item.getBoundingClientRect());

        items.forEach((item, index) => {
            const dx = before[index].left - after[index].left;
            const dy = before[index].top - after[index].top;

            item.animate(
                [
                    {transform: `translate3d(${dx}px, ${dy}px, 0)`, opacity: 0.86},
                    {transform: 'translate3d(0, 0, 0)', opacity: 1},
                ],
                {
                    duration: 720 + (index * 45),
                    easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
                    fill: 'both',
                }
            );
        });
    };

    /**
     * Détermine si le header survole une section avec un thème sombre.
     *
     * @returns Rien
     */
    const updateTheme = (): void => {
        if (!desktop.matches) {
            header.classList.remove('is-on-dark');
            return;
        }

        const probe = header.querySelector<HTMLElement>('.inner');

        if (!probe) {
            return;
        }

        const rect = probe.getBoundingClientRect();
        const x = rect.left + Math.min(rect.width * 0.55, 620);
        const y = rect.top + Math.min(rect.height * 0.5, 36);
        const darkPanel = document
            .elementsFromPoint(x, y)
            .map((element) => element.closest('[data-header-theme="dark"]'))
            .find(Boolean);

        header.classList.toggle('is-on-dark', Boolean(darkPanel));
    };

    /**
     * Met à jour le lien actif dans la navigation desktop.
     *
     * @returns Rien
     */
    const updateActiveLink = (): void => {
        if (!desktop.matches || navigationTargets.length === 0) {
            navigationTargets.forEach(({link}) => link.classList.remove('is-active'));
            return;
        }

        const targetX = window.innerWidth * 0.4;
        let activeIndex = 0;

        navigationTargets.forEach(({target}, index) => {
            const rect = target.getBoundingClientRect();

            if (rect.left <= targetX) {
                activeIndex = index;
            }
        });

        navigationTargets.forEach(({link}, index) => {
            const isActive = index === activeIndex;
            link.classList.toggle('is-active', isActive);

            if (isActive) {
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    };

    /**
     * Synchronise le header avec la position horizontale actuelle.
     *
     * @returns Rien
     */
    const update = (): void => {
        ticking = false;

        if (!desktop.matches) {
            setCompact(false);
            updateTheme();
            updateActiveLink();
            return;
        }

        const rect = lifecycle.getBoundingClientRect();
        const threshold = window.innerWidth * 0.72;

        setCompact(rect.left <= threshold);
        updateTheme();
        updateActiveLink();
    };

    /**
     * Limite les calculs du scroll à une frame navigateur.
     *
     * @returns Rien
     */
    const requestUpdate = (): void => {
        if (ticking) {
            return;
        }

        ticking = true;
        window.requestAnimationFrame(update);
    };

    update();
    window.addEventListener('scroll', requestUpdate, {passive: true});
    window.addEventListener('resize', requestUpdate);
    desktop.addEventListener('change', requestUpdate);
    reducedMotion.addEventListener('change', requestUpdate);
}

document.querySelectorAll('.site-header').forEach(initHeaderScroll);

export {};
