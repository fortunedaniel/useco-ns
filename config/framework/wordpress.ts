// =============================================================================
// Fichier : config/framework/wordpress.ts
// Rôle : typer la structure WordPress déclarée par les projets ComKani
//
// Ce qu'il fait :
// - décrit les pages, sections one-page, menus et réglages des articles
// - conserve des slugs stables utilisables par les scripts de synchronisation
// - fournit defineWordPressConfig() pour valider la structure avec TypeScript
//
// Ce qu'il ne fait pas :
// - ne crée ni ne supprime directement de page WordPress
// - ne remplace pas scripts/wp-setup.php
// - ne contient aucun contenu client stocké en base de données
//
// Fichiers liés :
// - config/src/wordpress.ts
// - scripts/wp-setup.php
// - scripts/lib/project-config-validation.ts
// =============================================================================

export interface WordPressPageDefinition {
    readonly title: string;
    readonly slug: string;
    readonly home?: boolean;
    readonly parent?: string;
    readonly template?: string;
    readonly content?: string;
}

export interface OnepageSectionDefinition {
    readonly key: string;
    readonly label: string;
}

export interface WordPressMenuEntry {
    readonly page?: string;
    readonly legal?: string;
    readonly url?: string;
    readonly label?: string;
    /** Sous-entrées de navigation. Un sous-menu WordPress n'est pas une sous-page. */
    readonly children?: readonly WordPressMenuEntry[];
}

export interface FooterMenuGroup {
    readonly label?: string;
    readonly items: readonly WordPressMenuEntry[];
}

/** Type de contenu réellement pris en charge par packages/comkani-core/includes/post-types.php. */
export interface WordPressPostTypeDefinition {
    readonly key: string;
    readonly label: string;
    readonly singular?: string;
    readonly slug?: string;
    readonly archive?: boolean;
    readonly icon?: string;
}

export interface WordPressStructureConfig {
    readonly pages: readonly WordPressPageDefinition[];
    readonly onepageSections?: readonly OnepageSectionDefinition[];
    readonly wordpress: {
        readonly posts: {
            readonly enabled: boolean;
            readonly indexPage: string;
            readonly permalinkStructure: string;
            readonly labels: {
                readonly singular: string;
                readonly plural: string;
                readonly gender?: string;
            };
        };
    };
    readonly legalPages: Readonly<Record<string, boolean>>;
    readonly menus: {
        readonly primary?: readonly WordPressMenuEntry[];
        readonly footer?: Readonly<Record<string, FooterMenuGroup>>;
        readonly legal?: readonly WordPressMenuEntry[];
    };
    readonly postTypes: readonly WordPressPostTypeDefinition[];
}

/**
 * Déclare la structure WordPress d'un projet ComKani.
 *
 * @param config Pages, menus et réglages WordPress du projet
 * @returns Configuration inchangée avec ses types littéraux conservés
 */
export function defineWordPressConfig<const T extends WordPressStructureConfig>(
    config: T,
): T {
    return config;
}
