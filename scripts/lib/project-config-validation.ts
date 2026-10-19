// =============================================================================
// Fichier : scripts/lib/project-config-validation.ts
// Rôle : valider la configuration TypeScript avant sa génération pour WordPress
//
// Ce qu'il fait :
// - détecte les slugs de pages dupliqués
// - contrôle qu'une seule page est déclarée comme accueil
// - détecte les clés de sections one-page dupliquées
// - contrôle les clés de groupes et champs de l'éditeur
// - bloque la génération lorsqu'une incohérence structurelle est détectée
//
// Ce qu'il ne fait pas :
// - ne valide pas le contenu éditorial client
// - ne modifie pas WordPress
// - ne remplace pas la recette fonctionnelle du site
// =============================================================================

interface ValidationIssue {
    readonly path: string;
    readonly message: string;
}

/**
 * Ajoute les doublons d'une propriété à la liste des erreurs.
 *
 * @param items Collection d'objets à contrôler
 * @param property Propriété contenant la clé stable
 * @param path Chemin lisible utilisé dans le rapport
 * @param issues Liste d'erreurs alimentée par le contrôle
 * @returns Rien
 */
function validateUniqueProperty(
    items: readonly Record<string, unknown>[],
    property: string,
    path: string,
    issues: ValidationIssue[],
): void {
    const seen = new Set<string>();

    items.forEach((item, index) => {
        const value = String(item[property] ?? '').trim();
        if (value === '') {
            issues.push({path: `${path}[${index}].${property}`, message: 'Valeur obligatoire.'});
            return;
        }

        if (seen.has(value)) {
            issues.push({path: `${path}[${index}].${property}`, message: `Valeur dupliquée : ${value}`});
            return;
        }

        seen.add(value);
    });
}

/**
 * Valide récursivement les champs d'un groupe éditorial.
 *
 * @param fields Champs à contrôler
 * @param path Chemin du groupe dans la configuration
 * @param issues Liste d'erreurs alimentée par le contrôle
 * @returns Rien
 */
function validateFields(
    fields: readonly Record<string, unknown>[],
    path: string,
    issues: ValidationIssue[],
): void {
    validateUniqueProperty(fields, 'key', path, issues);

    fields.forEach((field, index) => {
        if (field.type !== 'repeater') {
            return;
        }

        const nested = Array.isArray(field.fields)
            ? field.fields as readonly Record<string, unknown>[]
            : [];
        validateFields(nested, `${path}[${index}].fields`, issues);
    });
}

/**
 * Valide la configuration complète du projet avant génération.
 *
 * @param config Configuration assemblée depuis config/src/index.ts
 * @throws Error si la configuration contient une incohérence structurelle
 * @returns Rien
 */
/**
 * Gère validateProjectConfig dans le module ComKani.
 *
 * Les paramètres, effets secondaires et droits doivent être vérifiés
 * avec les appels du module lors de sa maintenance.
 */
export function validateProjectConfig(config: Record<string, unknown>): void {
    const issues: ValidationIssue[] = [];
    const pages = Array.isArray(config.pages)
        ? config.pages as readonly Record<string, unknown>[]
        : [];
    const sections = Array.isArray(config.onepageSections)
        ? config.onepageSections as readonly Record<string, unknown>[]
        : [];

    validateUniqueProperty(pages, 'slug', 'pages', issues);
    validateUniqueProperty(sections, 'key', 'onepageSections', issues);

    const homeCount = pages.filter((page) => page.home === true).length;
    if (homeCount > 1) {
        issues.push({path: 'pages', message: 'Une seule page peut être déclarée comme accueil.'});
    }

    const postTypes = Array.isArray(config.postTypes)
        ? config.postTypes as readonly Record<string, unknown>[] : [];
    validateUniqueProperty(postTypes, 'key', 'postTypes', issues);
    postTypes.forEach((postType, index) => {
        const key = String(postType.key ?? '');
        if (!/^[a-z0-9_]{1,20}$/.test(key)) {
            issues.push({path: `postTypes[${index}].key`, message: 'Clé WordPress valide : 1 à 20 caractères minuscules, chiffres ou underscore.'});
        }
        if (!String(postType.label ?? '').trim()) {
            issues.push({path: `postTypes[${index}].label`, message: 'Libellé obligatoire.'});
        }
    });
    const allPageSlugs = new Set(pages.map(page => String(page.slug ?? '')));
    pages.forEach((page, index) => {
        const parent = String(page.parent ?? '');
        if (parent && (!allPageSlugs.has(parent) || parent === page.slug)) {
            issues.push({path: `pages[${index}].parent`, message: 'La page parente doit exister et être différente de la page enfant.'});
        }
    });
    const menus = config.menus as Record<string, unknown> | undefined;
    const checkMenu = (items: unknown, path: string): void => {
        if (!Array.isArray(items)) return;
        items.forEach((item, index) => {
            const entry = typeof item === 'string' ? {page: item} : item as Record<string, unknown>;
            if (!entry || typeof entry !== 'object') return;
            if (entry.page && !allPageSlugs.has(String(entry.page))) {
                issues.push({path: `${path}[${index}].page`, message: `Page de menu introuvable : ${String(entry.page)}`});
            }
            if (entry.children) checkMenu(entry.children, `${path}[${index}].children`);
        });
    };
    checkMenu(menus?.primary, 'menus.primary');
    checkMenu(menus?.legal, 'menus.legal');
    const footerMenus = menus?.footer as Record<string, Record<string, unknown>> | undefined;
    Object.entries(footerMenus ?? {}).forEach(([key, group]) => checkMenu(group.items, `menus.footer.${key}.items`));

    const pageEditor = config.pageEditor as Record<string, unknown> | undefined;
    const editorPages = pageEditor?.pages as Record<string, unknown> | undefined;

    Object.entries(editorPages ?? {}).forEach(([pageKey, definition]) => {
        if (!definition || typeof definition !== 'object') {
            return;
        }

        const groups = Array.isArray((definition as Record<string, unknown>).groups)
            ? (definition as Record<string, unknown>).groups as readonly Record<string, unknown>[]
            : [];

        validateUniqueProperty(groups, 'key', `pageEditor.pages.${pageKey}.groups`, issues);
        groups.forEach((group, groupIndex) => {
            const fields = Array.isArray(group.fields)
                ? group.fields as readonly Record<string, unknown>[]
                : [];
            validateFields(fields, `pageEditor.pages.${pageKey}.groups[${groupIndex}].fields`, issues);
        });
    });

    if (issues.length === 0) {
        return;
    }

    const details = issues
        .map((issue) => `- ${issue.path} : ${issue.message}`)
        .join('\n');

    throw new Error(`Configuration ComKani invalide :\n${details}`);
}
