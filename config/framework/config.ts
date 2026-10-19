// Helpers de configuration ComKani. Les modules features conservent defineConfig().
export type ConfigObject = Readonly<Record<string, unknown>>;

export interface ProjectIdentity {
    readonly name: string;
    readonly slug: string;
    readonly type: 'multipage' | 'onepage' | 'landing';
    readonly language: string;
    readonly timezone: string;
    readonly tagline?: string;
}

export interface Organization {
    readonly name: string;
    readonly legalName?: string;
    readonly description?: string;
    readonly email?: string;
    readonly privacyEmail?: string;
    readonly phone?: string;
    readonly address?: string;
    readonly city?: string;
    readonly postalCode?: string;
    readonly country?: string;
    readonly areaServed?: readonly string[];
    readonly sameAs?: readonly string[];
    readonly legalForm?: string;
    readonly siren?: string;
    readonly siret?: string;
    readonly vatNumber?: string;
    readonly capital?: string;
    readonly registration?: string;
    readonly publicationDirector?: string;
    readonly professionalRegistration?: Readonly<{
        organization?: string;
        region?: string;
        number?: string;
    }>;
    readonly hosting?: Readonly<{
        name?: string;
        legalName?: string;
        address?: string;
        postalCode?: string;
        city?: string;
        country?: string;
        phone?: string;
        url?: string;
    }>;
}

export interface DesignSystem {
    readonly theme: Readonly<{
        defaultMode: 'light' | 'dark';
        supportsDarkMode: boolean;
    }>;
    readonly tokens: Readonly<{
        light: string;
        dark?: string;
        typography?: string;
        spacings?: string;
        borders?: string;
        radius?: string;
        shadows?: string;
        motions?: string;
        grid?: string;
        effects?: string;
    }>;
    readonly foundations?: Readonly<{
        spacings?: 'responsive' | 'figma';
        radius?: 'responsive' | 'figma';
        borders?: 'responsive' | 'figma';
        shadows?: 'responsive' | 'figma';
        motions?: 'responsive' | 'figma';
        grid?: 'responsive' | 'figma';
        effects?: readonly string[];
    }>;
    readonly typography?: Readonly<{
        primary?: Readonly<{family: string}>;
        secondary?: Readonly<{family: string}>;
    }>;
}

export interface Footer {
    readonly socials: readonly unknown[];
    readonly credit?: Readonly<{label: string; url: string}>;
}

export interface ProjectConfig {
    readonly project: ProjectIdentity;
    readonly organization: Organization;
    readonly designSystem: DesignSystem;
    readonly footer: Footer;
}

/** Preserve le type littéral des modules de configuration génériques. */
export function defineConfig<const T extends ConfigObject>(config: T): T {
    return config;
}

/** Vérifie la structure de la configuration générale sans changer sa valeur. */
export function defineProjectConfig<const T extends ProjectConfig>(config: T): T {
    return config;
}
