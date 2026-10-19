// =============================================================================
// Fichier : scripts/generate-design-system.ts
// Rôle : générer les foundations SCSS depuis les tokens du Design System
//
// Ce qu'il fait :
// - lit les couleurs Light et Dark
// - lit typographies, espacements, bordures, radius, ombres, motions et grille
// - génère les variables CSS consommées par le thème
// - conserve les JSON Figma comme source de vérité du Design System
//
// Ce qu'il ne fait pas :
// - ne modifie pas WordPress
// - ne modifie pas les composants PHP
// - ne déploie aucun fichier
// =============================================================================

import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const tokenRoot = path.join(root, 'config', 'tokens');
const requiredFamilies = ['primary', 'secondary'] as const;
const colorNamePattern = /^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/;
const shadePattern = /^\d{1,4}$/;


const generatedHeader = (source: string): string => `// =============================================================================
// FICHIER GÉNÉRÉ AUTOMATIQUEMENT - NE PAS MODIFIER DIRECTEMENT
// Source : ${source}
// Générateur : scripts/generate-design-system.ts
// Toute modification manuelle sera remplacée par npm run tokens/dev/qa.
// =============================================================================

`;

/** Lit un fichier JSON de tokens. */
async function readJson(relativePath: string): Promise<any> {
    return JSON.parse(await readFile(path.join(tokenRoot, relativePath), 'utf8'));
}

/** Extrait une couleur hexadécimale d'un token Figma. */
function colorHex(token: any, label: string): string {
    const value = token?.$value?.hex;
    if (!/^#[0-9A-F]{6}$/i.test(value ?? '')) throw new Error(`Couleur invalide : ${label}`);
    return value.toLowerCase();
}

/**
 * Valide une palette Figma et identifie dynamiquement les familles.
 * Les familles primary et secondary sont obligatoires, les autres libres.
 * Les notifications sont des couleurs fonctionnelles sans graduation numérique.
 */
function readColorStructure(tokens: Record<string, unknown>, mode: string): Map<string, string[]> {
    const structure = new Map<string, string[]>();
    for (const family of requiredFamilies) {
        if (!Object.prototype.hasOwnProperty.call(tokens, family)) {
            throw new Error(`Famille obligatoire absente dans ${mode} : ${family}`);
        }
    }
    for (const [family, raw] of Object.entries(tokens)) {
        if (family.startsWith('$')) continue;
        if (!colorNamePattern.test(family)) throw new Error(`Nom de famille couleur invalide dans ${mode} : ${family}`);
        if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
            throw new Error(`Famille couleur invalide dans ${mode} : ${family}`);
        }
        const entries = Object.entries(raw);
        if (!entries.length) throw new Error(`Famille couleur vide dans ${mode} : ${family}`);
        const keys = entries.map(([key, token]) => {
            const validKey = family === 'notification' ? colorNamePattern.test(key) : shadePattern.test(key);
            if (!validKey) throw new Error(`Nuance de couleur invalide dans ${mode} : ${family}.${key}`);
            colorHex(token, `${mode}.${family}.${key}`);
            return key;
        });
        structure.set(family, keys);
    }
    return structure;
}

/** Génère toutes les familles déclarées et exige une structure identique en Light et Dark. */
async function generateColors(): Promise<void> {
    const modes = ['light', 'dark'] as const;
    const palettes = await Promise.all(modes.map(mode => readJson(`colors/${mode}.tokens.json`)));
    const structures = palettes.map((tokens, index) => readColorStructure(tokens, modes[index]));
    const [lightStructure, darkStructure] = structures;
    for (const [family, keys] of lightStructure) {
        const darkKeys = darkStructure.get(family);
        if (!darkKeys || keys.some(key => !darkKeys.includes(key)) || darkKeys.length !== keys.length) {
            throw new Error(`La famille ${family} doit avoir les mêmes nuances en Light et Dark`);
        }
    }
    for (const family of darkStructure.keys()) {
        if (!lightStructure.has(family)) throw new Error(`Famille ${family} présente uniquement en Dark`);
    }
    for (const [index, mode] of modes.entries()) {
        const tokens = palettes[index];
        const lines: string[] = [];
        for (const [family, keys] of structures[index]) {
            const sortedKeys = family === 'notification' ? keys : [...keys].sort((a, b) => Number(a) - Number(b));
            for (const key of sortedKeys) {
                lines.push(`    --cl-${family}-${key}: ${colorHex(tokens[family][key], `${family}.${key}`)};`);
            }
            lines.push('');
        }
        const selector = mode === 'light' ? ':root,\n[data-theme="light"]' : '[data-theme="dark"]';
        const scheme = mode === 'dark' ? '    color-scheme: dark;\n\n' : '';
        await writeFile(
            path.join(root, `resources/scss/theme/_colors-${mode}.scss`),
            `${generatedHeader(`config/tokens/colors/${mode}.tokens.json`)}${selector} {\n${scheme}${lines.join('\n').trimEnd()}\n}\n`
        );
    }
}

/** Génère les espacements responsives depuis les trois modes Figma. */
async function generateSpacings(): Promise<void> {
    const modes = await Promise.all(['mobile', 'tablet', 'desktop'].map((name) => readJson(`spacings/${name}.tokens.json`)));
    const render = (tokens: any) => Object.entries(tokens).filter(([key]) => key !== '$extensions').map(([key, token]: any) => `        --spacing-${key.replace('spacing ', '')}: ${token.$value}px;`).join('\n');
    const content = `${generatedHeader('config/tokens/spacings/*.tokens.json')}:root {\n${render(modes[0]).replace(/^ {8}/gm, '    ')}\n}\n\n@media (min-width: 768px) {\n    :root {\n${render(modes[1])}\n    }\n}\n\n@media (min-width: 1200px) {\n    :root {\n${render(modes[2])}\n    }\n}\n`;
    await writeFile(path.join(root, 'resources/scss/foundations/_spacings.scss'), content);
}

/** Génère les radius et les épaisseurs de bordure. */
async function generateGeometry(): Promise<void> {
    const radius = await readJson('radius.tokens.json');
    const borders = await readJson('borders.tokens.json');
    const radiusLines = Object.entries(radius).filter(([key]) => key !== '$extensions').map(([key, token]: any) => `    --radius-${key.replace('radius ', '')}: ${token.$value}px;`);
    const borderLines = Object.entries(borders).filter(([key]) => key !== '$extensions').map(([key, token]: any) => `    --border-${key.replace('border ', '')}: ${token.$value}px;`);
    await writeFile(
        path.join(root, 'resources/scss/foundations/_radius.scss'),
        `${generatedHeader('config/tokens/radius.tokens.json + config/tokens/borders.tokens.json')}:root {\n${radiusLines.join('\n')}\n\n${borderLines.join('\n')}\n}\n`
    );
}

/** Génère les tailles et styles typographiques depuis les tokens du projet. */
async function generateTypography(): Promise<void> {
    const names = ['mobile', 'tablet', 'desktop'];

    const modes = await Promise.all(
        names.map((name) => readJson(`typography/${name}.tokens.json`))
    );

    const styles = await readJson('typography/styles.tokens.json');
    const baseTokens = modes[0];

    /**
     * Retourne la famille de police déclarée dans les tokens Figma.
     */
    const getFontFamily = (name: 'primary' | 'secondary'): string => {
        const value = baseTokens.font?.[name]?.$value;

        if (typeof value !== 'string' || !value) {
            throw new Error(`Famille de police introuvable : ${name}`);
        }

        return `"${value}"`;
    };

    /**
     * Convertit un nom de graisse en valeur CSS.
     *
     * Exemples :
     * black → 900
     * bold → 700
     * semi-bold → 600
     * medium → 500
     * normal → 400
     */
    const getFontWeight = (name: unknown): number | null => {
        if (name === undefined || name === null) {
            return null;
        }

        if (typeof name === 'number') {
            return name;
        }

        if (typeof name !== 'string') {
            throw new Error(`Graisse typographique invalide : ${String(name)}`);
        }

        const value = baseTokens[name]?.$value;

        if (typeof value !== 'number') {
            throw new Error(`Graisse typographique inconnue : ${name}`);
        }

        return value;
    };

    /**
     * Convertit le letter-spacing Figma en valeur CSS valide.
     *
     * Figma :
     * -1% → CSS : -0.01em
     * 1%  → CSS : 0.01em
     * 0%  → CSS : 0em
     */
    const getLetterSpacing = (value: unknown): string | null => {
        if (value === undefined || value === null) {
            return null;
        }

        if (typeof value === 'number') {
            if (value === 0) {
                return '0';
            }

            return `${value}em`;
        }

        if (typeof value !== 'string') {
            throw new Error(
                `Letter-spacing invalide : ${String(value)}`
            );
        }

        const trimmed = value.trim();

        if (trimmed.endsWith('%')) {
            const percentage = Number.parseFloat(trimmed);

            if (Number.isNaN(percentage)) {
                throw new Error(`Letter-spacing invalide : ${value}`);
            }

            if (percentage === 0) {
                return '0';
            }

            return `${percentage / 100}em`;
        }

        return trimmed;
    };

    /**
     * Génère les variables CSS de tailles typographiques.
     */
    const renderSizes = (tokens: any, indent = '    '): string =>
        Object.entries(tokens['font-size'])
            .map(
                ([key, token]: any) =>
                    `${indent}--font-size-${key}: ${token.$value}px;`
            )
            .join('\n');

    /**
     * Génère les propriétés d'un niveau de titre.
     */
    const renderHeading = (tag: string): string => {
        const style = styles.headings?.[tag] ?? {};
        const properties: string[] = [
            `    font-size: var(--font-size-${tag});`
        ];

        const fontWeight = getFontWeight(style['font-weight']);

        if (fontWeight !== null) {
            properties.push(`    font-weight: ${fontWeight};`);
        }

        if (style['line-height'] !== undefined) {
            properties.push(
                `    line-height: ${style['line-height']};`
            );
        }

        const letterSpacing = getLetterSpacing(
            style['letter-spacing']
        );

        if (letterSpacing !== null) {
            properties.push(
                `    letter-spacing: ${letterSpacing};`
            );
        }

        if (style['text-transform']) {
            properties.push(
                `    text-transform: ${style['text-transform']};`
            );
        }

        return `${tag} {\n${properties.join('\n')}\n}`;
    };

    /**
     * Génère les propriétés typographiques des paragraphes.
     */
    const renderParagraph = (): string => {
        const style = styles.paragraph ?? {};
        const properties: string[] = [
            '    margin: 0;',
            '    color: var(--text-primary);',
            '    font-size: var(--font-size-body-m);'
        ];

        const fontWeight = getFontWeight(style['font-weight']);

        if (fontWeight !== null) {
            properties.push(`    font-weight: ${fontWeight};`);
        }

        if (style['line-height'] !== undefined) {
            properties.push(
                `    line-height: ${style['line-height']};`
            );
        }

        const letterSpacing = getLetterSpacing(
            style['letter-spacing']
        );

        if (letterSpacing !== null) {
            properties.push(
                `    letter-spacing: ${letterSpacing};`
            );
        }

        if (style['text-transform']) {
            properties.push(
                `    text-transform: ${style['text-transform']};`
            );
        }

        return `p {\n${properties.join('\n')}\n}`;
    };

    const primaryFont = getFontFamily('primary');
    const secondaryFont = getFontFamily('secondary');

    const headings = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']
        .map(renderHeading)
        .join('\n\n');

    const content = `${generatedHeader('config/tokens/typography/*.tokens.json, dont styles.tokens.json')}:root {
    --ft-primary: ${primaryFont}, arial, sans-serif;
    --ft-secondary: ${secondaryFont}, ${primaryFont}, arial, sans-serif;

${renderSizes(modes[0])}
}

@media (min-width: 768px) {
    :root {
${renderSizes(modes[1], '        ')}
    }
}

@media (min-width: 1200px) {
    :root {
${renderSizes(modes[2], '        ')}
    }
}

h1,
h2,
h3,
h4,
h5,
h6 {
    margin: 0;
    color: var(--text-primary);
    font-family: var(--ft-secondary);
}

${headings}

${renderParagraph()}
`;

    await writeFile(
        path.join(root, 'resources/scss/theme/_typography.scss'),
        content
    );
}


/** Génère les variables de grille responsive depuis les tokens Figma. */
async function generateGrid(): Promise<void> {
    const grid = await readJson('grid.tokens.json');
    const modes = [
        ['mobile-small', null],
        ['mobile', 393],
        ['mobile-large', 440],
        ['tablet', 768],
        ['laptop', 1200],
        ['desktop', 1440],
        ['desktop-large', 1728],
    ] as const;

    const render = (name: string, indent = '    '): string => {
        const token = grid[name];
        if (!token) throw new Error(`Grille introuvable : ${name}`);
        return [
            `${indent}--layout-frame: ${token.frame}px;`,
            `${indent}--layout-padding: ${token.margin}px;`,
            `${indent}--grid-columns: ${token.columns};`,
            `${indent}--grid-gutter: ${token.gutter}px;`,
        ].join('\n');
    };

    const [first, ...rest] = modes;
    let content = `${generatedHeader('config/tokens/grid.tokens.json')}:root {\n${render(first[0])}\n}\n`;

    for (const [name, breakpoint] of rest) {
        content += `\n@media (min-width: ${breakpoint}px) {\n    :root {\n${render(name, '        ')}\n    }\n}\n`;
    }

    await writeFile(path.join(root, 'resources/scss/foundations/_layout.scss'), content);
}

/** Génère les foundations simples qui ne dépendent pas d'un mode responsive. */
async function generateEffects(): Promise<void> {
    const shadows = await readJson('shadows.tokens.json');
    const motions = await readJson('motions.tokens.json');
    const effects = await readJson('effects.tokens.json');
    const shadowLines = Object.entries(shadows.shadows).map(([name, value]: any) => `    --shadow-${name}: ${value.x}px ${value.y}px ${value.blur}px ${value.spread}px rgb(24 24 36 / ${Math.round(value.opacity * 100)}%);`);
    await writeFile(
        path.join(root, 'resources/scss/foundations/_shadows.scss'),
        `${generatedHeader('config/tokens/shadows.tokens.json')}:root {\n${shadowLines.join('\n')}\n}\n`
    );
    await writeFile(
        path.join(root, 'resources/scss/foundations/_motions.scss'),
        `${generatedHeader('config/tokens/motions.tokens.json')}:root {\n    --motion-fast: ${motions.duration.fast}ms;\n    --motion-default: ${motions.duration.default}ms;\n    --motion-slow: ${motions.duration.slow}ms;\n    --ease-standard: ${motions.easing.standard};\n}\n\n@media (prefers-reduced-motion: reduce) {\n    :root {\n        --motion-fast: 0ms;\n        --motion-default: 0ms;\n        --motion-slow: 0ms;\n    }\n}\n`
    );
    const glass = effects.glass.web;
    await writeFile(
        path.join(root, 'resources/scss/foundations/_effects.scss'),
        `${generatedHeader('config/tokens/effects.tokens.json')}:root {\n    --glass-background: ${glass.background};\n    --glass-border: ${glass.border};\n    --glass-blur: ${glass.backdropBlur}px;\n    --glass-saturation: ${glass.saturation}%;\n    --glass-shadow: ${glass.shadow};\n}\n\n@mixin glass {\n    border: 1px solid var(--glass-border);\n    background: var(--glass-background);\n    box-shadow: var(--glass-shadow);\n    backdrop-filter: blur(var(--glass-blur)) saturate(var(--glass-saturation));\n}\n`
    );
}

await generateColors();
await generateSpacings();
await generateGeometry();
await generateTypography();
await generateGrid();
await generateEffects();
console.log('Design System ComKani : tokens synchronisés pour le projet actif.');
