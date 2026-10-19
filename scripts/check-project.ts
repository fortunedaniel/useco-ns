// =============================================================================
// Fichier : scripts/check-project.ts
// Rôle : contrôler la cohérence technique d’un projet issu du Starter ComKani
//
// Objectif :
// - détecter les incohérences avant préproduction ou production
// - fournir des erreurs directement exploitables par les développeurs de l’agence
// - afficher, lorsque cela est possible, le fichier, la ligne et l’extrait fautif
//
// Ce qu’il vérifie :
// - présence des fichiers indispensables
// - cohérence de l’identité du projet
// - cohérence package.json / package-lock.json / style.css
// - structure WordPress et pages déclarées
// - configuration des articles, menus, pages légales et sections
// - présence des fichiers du Design System
// - références historiques interdites
// - secrets potentiellement présents dans les sources
// - fichiers parasites
// - médias lourds et bundles volumineux sous forme d’avertissements non bloquants
//
// Ce qu’il ne fait pas :
// - ne modifie aucun fichier
// - ne modifie pas WordPress
// - ne modifie pas la base de données
// - ne déploie rien
// - ne corrige pas automatiquement les anomalies
//
// Utilisation :
//   npm run check
//
// Utilisé également par :
//   npm run preprod / npm run prod
//
// Résultat attendu :
//   Contrôle ComKani : conforme
//
// En cas d’erreur, le format est autant que possible :
//   [1] chemin/fichier.php:123
//       Référence interdite : "Annonceur"
//       > extrait de la ligne concernée
//
// Maintenance :
// - toute nouvelle règle de contrôle doit être documentée dans ce fichier
// - privilégier les messages explicites et directement actionnables
// - conserver les exceptions internes strictement limitées aux scripts ComKani
// =============================================================================

import fs from 'node:fs';
import path from 'node:path';
import ts from 'typescript';
import { loadProjectConfig } from './lib/project-config.ts';

// =============================================================================
// CONFIGURATION GÉNÉRALE
// =============================================================================

const root = process.cwd();

/**
 * Dossiers complètement ignorés pendant l’analyse récursive.
 *
 * Important :
 * `assets` est ignoré car il contient principalement des fichiers compilés
 * et binaires qui ne doivent pas être réanalysés par le contrôle des sources.
 */
const ignored = new Set([
    'node_modules',
    '.git',
    'dist',
    '.comkani-backups',
    'project-assets',
    'assets',
    'release',
]);

/**
 * Extensions considérées comme des fichiers texte analysables.
 */
const textExtensions = new Set([
    '.php',
    '.js',
    '.ts',
    '.mjs',
    '.scss',
    '.css',
    '.json',
    '.txt',
    '.xml',
    '.html',
]);

/**
 * Références historiques ou dépendances interdites dans un projet final.
 *
 * Chaque entrée contient :
 * - une expression régulière
 * - le message affiché par le contrôle
 */
const forbiddenPatterns = [
    [/Annonceur/gi, 'Référence historique Annonceur'],
];

/**
 * Motifs simples permettant de détecter des secrets potentiellement
 * enregistrés directement dans les sources.
 *
 * Attention :
 * ce contrôle est volontairement prudent. Une détection doit être vérifiée
 * manuellement avant toute suppression.
 */
const secretPatterns = [
    [
        /(password|passwd|secret|api[_-]?key)\s*[:=]\s*["'][^"']{8,}["']/gi,
        'Secret potentiel',
    ],
];

/**
 * Liste complète des anomalies détectées pendant l’audit.
 *
 * Une anomalie peut contenir :
 * - file : chemin relatif du fichier
 * - message : description du problème
 * - line : numéro de ligne si connu
 * - excerpt : extrait de la ligne si disponible
 */
const issues = [];
const warnings = [];

// =============================================================================
// OUTILS DE JOURNALISATION DES ANOMALIES
// =============================================================================

/**
 * Ajoute une anomalie à la liste de résultats.
 *
 * @param {string} file Chemin relatif du fichier concerné.
 * @param {string} message Message décrivant l’anomalie.
 * @param {number|null} [line=null] Numéro de ligne, si connu.
 * @param {string} [excerpt=''] Extrait de la ligne concernée.
 * @returns {void}
 */
function addIssue(
    file,
    message,
    line = null,
    excerpt = ''
) {
    issues.push({
        file,
        message,
        line,
        excerpt,
    });
}

/**
 * Ajoute un avertissement non bloquant au rapport QA.
 *
 * Un avertissement informe l'agence d'un point à vérifier mais ne fait jamais
 * échouer npm run check, npm run preprod ou npm run prod.
 *
 * @param {string} file Chemin du fichier concerné.
 * @param {string} message Description du point de vigilance.
 * @returns {void}
 */
function addWarning(file, message) {
    warnings.push({
        file,
        message,
    });
}

/**
 * Retourne le numéro de ligne correspondant à un index dans une chaîne.
 *
 * Les numéros de ligne commencent à 1, comme dans Sublime Text et la plupart
 * des éditeurs de code.
 *
 * @param {string} content Contenu complet du fichier.
 * @param {number} index Position du caractère dans le contenu.
 * @returns {number} Numéro de ligne correspondant.
 */
function getLineNumber(content, index) {
    return content
        .slice(0, Math.max(0, index))
        .split('\n')
        .length;
}

/**
 * Retourne le contenu nettoyé d’une ligne précise.
 *
 * L’extrait est volontairement limité pour conserver un affichage lisible
 * dans le terminal.
 *
 * @param {string} content Contenu complet du fichier.
 * @param {number} lineNumber Numéro de ligne commençant à 1.
 * @param {number} [maxLength=180] Longueur maximale de l’extrait.
 * @returns {string} Extrait de la ligne.
 */
function getLineExcerpt(
    content,
    lineNumber,
    maxLength = 180
) {
    const lines = content.split(/\r?\n/);
    const rawLine = lines[lineNumber - 1] ?? '';
    const normalized = rawLine.trim();

    if (normalized.length <= maxLength) {
        return normalized;
    }

    return `${normalized.slice(0, maxLength - 1)}…`;
}

/**
 * Analyse un contenu avec une expression régulière et ajoute une anomalie
 * pour chaque ligne où le motif est rencontré.
 *
 * Si plusieurs occurrences du même motif existent sur une même ligne,
 * une seule anomalie est remontée pour cette ligne afin d’éviter les doublons.
 *
 * @param {string} relativePath Chemin relatif du fichier analysé.
 * @param {string} content Contenu du fichier.
 * @param {RegExp} pattern Expression régulière à rechercher.
 * @param {string} message Message associé à la règle.
 * @returns {void}
 */
function addPatternIssues(
    relativePath,
    content,
    pattern,
    message
) {
    const flags = pattern.flags.includes('g')
        ? pattern.flags
        : `${pattern.flags}g`;

    const regex = new RegExp(
        pattern.source,
        flags
    );

    const seenLines = new Set();

    for (const match of content.matchAll(regex)) {
        const index = match.index ?? 0;
        const line = getLineNumber(
            content,
            index
        );

        if (seenLines.has(line)) {
            continue;
        }

        seenLines.add(line);

        addIssue(
            relativePath,
            message,
            line,
            getLineExcerpt(content, line)
        );
    }
}

// =============================================================================
// OUTILS DE LECTURE ET DE VALIDATION
// =============================================================================

/**
 * Lit et parse un fichier JSON du projet.
 *
 * @param {string} relativePath Chemin relatif depuis la racine du thème.
 * @returns {object|null} Objet JSON parsé ou null en cas d’erreur.
 */
function readJson(relativePath) {
    const fullPath = path.join(
        root,
        relativePath
    );

    if (!fs.existsSync(fullPath)) {
        addIssue(
            relativePath,
            'Fichier requis manquant'
        );

        return null;
    }

    try {
        return JSON.parse(
            fs.readFileSync(
                fullPath,
                'utf8'
            )
        );
    } catch (error) {
        addIssue(
            relativePath,
            `JSON invalide : ${error.message}`
        );

        return null;
    }
}

/**
 * Lit une valeur dans l’en-tête WordPress de style.css.
 *
 * Exemple :
 *   Theme Name: Yellow Office
 *
 * @param {string} content Contenu complet de style.css.
 * @param {string} field Nom du champ WordPress recherché.
 * @returns {string|null} Valeur trouvée ou null.
 */
function getThemeHeader(content, field) {
    const pattern = new RegExp(
        `^${field}:\\s*(.+)$`,
        'm'
    );

    const match = content.match(pattern);

    return match
        ? match[1].trim()
        : null;
}

/**
 * Vérifie qu’un fichier existe avec exactement la casse attendue.
 *
 * Cette vérification évite les erreurs qui passent sur macOS mais peuvent
 * casser sur un serveur Linux sensible à la casse.
 *
 * @param {string} relativePath Chemin relatif attendu.
 * @returns {boolean} true si le fichier existe avec la bonne casse.
 */
function exactFileExists(relativePath) {
    const normalized = relativePath.replaceAll(
        '\\',
        '/'
    );

    const directory = path.join(
        root,
        path.dirname(normalized)
    );

    const expectedName = path.basename(
        normalized
    );

    if (!fs.existsSync(directory)) {
        return false;
    }

    return fs
        .readdirSync(directory)
        .includes(expectedName);
}

/**
 * Parcourt récursivement les sources du projet.
 *
 * Le parcours :
 * - ignore les dossiers techniques définis dans `ignored`
 * - signale les fichiers parasites
 * - analyse uniquement les extensions textuelles autorisées
 * - détecte les références historiques et les secrets potentiels
 *
 * @param {string} directory Dossier absolu à parcourir.
 * @returns {void}
 */
function walk(directory) {
    const entries = fs.readdirSync(
        directory,
        {
            withFileTypes: true,
        }
    );

    for (const entry of entries) {
        if (ignored.has(entry.name)) {
            continue;
        }

        const fullPath = path.join(
            directory,
            entry.name
        );

        if (entry.isDirectory()) {
            walk(fullPath);
            continue;
        }

        const relative = path.relative(
            root,
            fullPath
        );

        if (
            entry.name === '.DS_Store'
            || entry.name === 'Thumbs.db'
        ) {
            addIssue(
                relative,
                'Fichier parasite'
            );

            continue;
        }

        if (
            !textExtensions.has(
                path.extname(entry.name)
            )
        ) {
            continue;
        }

        const content = fs.readFileSync(
            fullPath,
            'utf8'
        );

        for (
            const [pattern, message]
            of forbiddenPatterns
        ) {
            addPatternIssues(
                relative,
                content,
                pattern,
                message
            );
        }

        for (
            const [pattern, message]
            of secretPatterns
        ) {
            addPatternIssues(
                relative,
                content,
                pattern,
                message
            );
        }
    }
}

/**
 * Vérifie les entrées d’un menu configuré dans le projet.
 *
 * Une entrée peut cibler :
 * - une page déclarée
 * - une page légale activée
 * - une URL interne ou externe valide
 *
 * @param {string} menuName Nom logique du menu contrôlé.
 * @param {unknown} entries Entrées du menu.
 * @param {Set<string>} pageSlugs Slugs des pages connues.
 * @param {Set<string>} enabledLegalPages Clés des pages légales actives.
 * @returns {void}
 */
function validateMenuEntries(
    menuName,
    entries,
    pageSlugs,
    enabledLegalPages
) {
    if (!Array.isArray(entries)) {
        addIssue(
            'config/src/project.ts',
            `Le menu ${menuName} doit être un tableau`
        );

        return;
    }

    for (const entry of entries) {
        if (typeof entry === 'string') {
            if (
                !pageSlugs.has(entry)
                && !enabledLegalPages.has(entry)
            ) {
                addIssue(
                    'config/src/project.ts',
                    `Référence inconnue dans le menu ${menuName} : ${entry}`
                );
            }

            continue;
        }

        if (
            !entry
            || typeof entry !== 'object'
            || Array.isArray(entry)
        ) {
            addIssue(
                'config/src/project.ts',
                `Entrée invalide dans le menu ${menuName}`
            );

            continue;
        }

        if (
            entry.page
            && !pageSlugs.has(
                String(entry.page)
            )
        ) {
            addIssue(
                'config/src/project.ts',
                `Page inconnue dans le menu ${menuName} : ${entry.page}`
            );
        }

        if (
            entry.legal
            && !enabledLegalPages.has(
                String(entry.legal)
            )
        ) {
            addIssue(
                'config/src/project.ts',
                `Page légale inactive dans le menu ${menuName} : ${entry.legal}`
            );
        }

        if (entry.url) {
            const url = String(
                entry.url
            ).trim();

            if (
                !url.startsWith('/')
                && !/^https?:\/\//i.test(url)
            ) {
                addIssue(
                    'config/src/project.ts',
                    `URL invalide dans le menu ${menuName} : ${url}`
                );
            }
        }

        if (
            !entry.page
            && !entry.legal
            && !entry.url
        ) {
            addIssue(
                'config/src/project.ts',
                `Entrée sans page, legal ou url dans le menu ${menuName}`
            );
        }
    }
}

// =============================================================================
// 1. FICHIERS INDISPENSABLES
// =============================================================================

const requiredFiles = [
    'config/src/project.ts',
    'config/src/wordpress.ts',
    'config/src/features/components.ts',
    'config/src/features/forms.ts',
    'config/src/features/security.ts',
    'config/src/features/seo.ts',
    'config/src/features/editor.ts',
    'config/generated/project-config.php',
    'package.json',
    'package-lock.json',
    'style.css',
    'assets/build/main.css',
    'assets/build/main.js',
    'packages/comkani-core/comkani-core.php',
    'packages/comkani-core/includes/media.php',
    'packages/comkani-core/assets/media.js',
    'scripts/init-project.ts',
    'scripts/wp-setup.php',
    'scripts/check-project.ts',
    'scripts/package-project.ts',
    'scripts/clean-system.ts',
    'scripts/generate-design-system.ts',
    'scripts/docs/generate-guide.ts',
    'docs/index.html',
];

for (const requiredFile of requiredFiles) {
    if (
        !fs.existsSync(
            path.join(
                root,
                requiredFile
            )
        )
    ) {
        addIssue(
            requiredFile,
            'Fichier requis manquant'
        );
    }
}

// =============================================================================
// 2. IDENTITÉ ET CONFIGURATION DU PROJET
// =============================================================================

let projectConfig = null;

try {
    projectConfig = loadProjectConfig(root);
} catch (error) {
    addIssue(
        'config/',
        error.message
    );
}

const packageJson = readJson(
    'package.json'
);

const packageLock = readJson(
    'package-lock.json'
);

if (projectConfig?.project) {
    const projectName = String(
        projectConfig.project.name ?? ''
    ).trim();

    const projectSlug = String(
        projectConfig.project.slug ?? ''
    ).trim();

    const starterMode = (
        projectName === 'ComKani Starter'
        && projectSlug === 'comkani-starter'
    );

    if (!projectName) {
        addIssue(
            'config/src/project.ts',
            'project.name est vide'
        );
    }

    if (!projectSlug) {
        addIssue(
            'config/src/project.ts',
            'project.slug est vide'
        );
    }

    if (
        projectSlug
        && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(
            projectSlug
        )
    ) {
        addIssue(
            'config/src/project.ts',
            'project.slug contient des caractères invalides'
        );
    }

    const expectedPackageName = starterMode
        ? 'comkani-wordpress-starter'
        : `${projectSlug}-website`;

    const expectedThemeFolder =
        `${projectSlug}-comkani`;

    const expectedTextDomain = starterMode
        ? 'comkani-starter'
        : expectedThemeFolder;

    const expectedThemeName = starterMode
        ? 'ComKani Starter'
        : projectName;

    if (
        packageJson
        && packageJson.name
            !== expectedPackageName
    ) {
        addIssue(
            'package.json',
            `Nom npm attendu : ${expectedPackageName}`
        );
    }

    if (packageLock) {
        if (
            packageLock.name
                !== expectedPackageName
        ) {
            addIssue(
                'package-lock.json',
                `Nom npm attendu : ${expectedPackageName}`
            );
        }

        if (
            packageLock.packages
            && packageLock.packages['']
            && packageLock
                .packages['']
                .name !== expectedPackageName
        ) {
            addIssue(
                'package-lock.json',
                `Nom du package racine attendu : ${expectedPackageName}`
            );
        }
    }

    const styleCssPath = path.join(
        root,
        'style.css'
    );

    if (fs.existsSync(styleCssPath)) {
        const styleCss = fs.readFileSync(
            styleCssPath,
            'utf8'
        );

        const themeName = getThemeHeader(
            styleCss,
            'Theme Name'
        );

        const textDomain = getThemeHeader(
            styleCss,
            'Text Domain'
        );

        if (
            themeName !== expectedThemeName
        ) {
            addIssue(
                'style.css',
                `Theme Name attendu : ${expectedThemeName}`
            );
        }

        if (
            textDomain !== expectedTextDomain
        ) {
            addIssue(
                'style.css',
                `Text Domain attendu : ${expectedTextDomain}`
            );
        }
    }

    if (
        !starterMode
        && path.basename(root)
            !== expectedThemeFolder
    ) {
        addIssue(
            '.',
            `Dossier du thème attendu : ${expectedThemeFolder}`
        );
    }

    if (
        projectConfig.project.type
            === 'multipage'
        && Array.isArray(
            projectConfig.onepageSections
        )
        && projectConfig
            .onepageSections
            .length > 0
    ) {
        addIssue(
            'config/src/project.ts',
            'Un projet multipage ne doit pas conserver de sections one-page'
        );
    }

    // =========================================================================
    // 3. DESIGN SYSTEM
    // =========================================================================

    const tokenPaths = [
        projectConfig
            .designSystem
            ?.tokens
            ?.light,
        projectConfig
            .designSystem
            ?.tokens
            ?.dark,
    ].filter(
        (value) => (
            typeof value === 'string'
            && value.trim() !== ''
        )
    );

    for (const tokenPath of tokenPaths) {
        if (
            !exactFileExists(tokenPath)
        ) {
            addIssue(
                'config/src/project.ts',
                `Fichier de tokens absent ou mauvaise casse : ${tokenPath}`
            );
        }
    }

    // =========================================================================
    // 4. PAGES WORDPRESS
    // =========================================================================

    const pages = Array.isArray(
        projectConfig.pages
    )
        ? projectConfig.pages
        : [];

    const pageSlugs = new Set();
    let homeCount = 0;

    for (const page of pages) {
        const slug = String(
            page?.slug ?? ''
        ).trim();

        const title = String(
            page?.title ?? ''
        ).trim();

        if (!slug || !title) {
            addIssue(
                'config/src/project.ts',
                'Chaque page doit avoir un title et un slug'
            );

            continue;
        }

        if (pageSlugs.has(slug)) {
            addIssue(
                'config/src/project.ts',
                `Slug de page dupliqué : ${slug}`
            );
        }

        pageSlugs.add(slug);

        if (page.home === true) {
            homeCount += 1;
        }
    }

    if (
        projectConfig.project.type
            === 'multipage'
        && homeCount !== 1
    ) {
        addIssue(
            'config/src/project.ts',
            'Un projet multipage doit définir exactement une page home'
        );
    }

    for (const page of pages) {
        if (
            page?.parent
            && !pageSlugs.has(
                String(page.parent)
            )
        ) {
            addIssue(
                'config/src/project.ts',
                `Parent de page introuvable : ${page.parent}`
            );
        }
    }

    // =========================================================================
    // 5. ARTICLES WORDPRESS
    // =========================================================================

    const postsConfig = (
        projectConfig
            .wordpress
            ?.posts
        ?? {}
    );

    if (postsConfig.enabled === true) {
        const indexPage = String(
            postsConfig.indexPage ?? ''
        ).trim();

        const permalinkStructure = String(
            postsConfig
                .permalinkStructure
            ?? ''
        ).trim();

        if (
            !indexPage
            || !pageSlugs.has(indexPage)
        ) {
            addIssue(
                'config/src/project.ts',
                `Page des articles introuvable : ${indexPage || '(vide)'}`
            );
        }

        if (
            !permalinkStructure
                .startsWith('/')
            || !permalinkStructure
                .endsWith('/')
            || !permalinkStructure
                .includes('%postname%')
        ) {
            addIssue(
                'config/src/project.ts',
                'wordpress.posts.permalinkStructure doit commencer et finir par "/" et contenir %postname%'
            );
        }
    }

    // =========================================================================
    // 6. SECTIONS DE SERVICES
    // =========================================================================

    const serviceSections =
        Array.isArray(
            projectConfig.serviceSections
        )
            ? projectConfig
                .serviceSections
            : [];

    const serviceAnchors =
        new Set();

    if (
        serviceSections.length > 0
        && !pageSlugs.has('services')
    ) {
        addIssue(
            'config/src/project.ts',
            'serviceSections nécessite une page Services déclarée'
        );
    }

    for (
        const section
        of serviceSections
    ) {
        const label = String(
            section?.label ?? ''
        ).trim();

        const anchor = String(
            section?.anchor ?? ''
        ).trim();

        if (!label || !anchor) {
            addIssue(
                'config/src/project.ts',
                'Chaque serviceSection doit avoir un label et un anchor'
            );

            continue;
        }

        if (
            !/^[a-z0-9]+(?:-[a-z0-9]+)*$/
                .test(anchor)
        ) {
            addIssue(
                'config/src/project.ts',
                `Ancre de service invalide : ${anchor}`
            );
        }

        if (
            serviceAnchors.has(anchor)
        ) {
            addIssue(
                'config/src/project.ts',
                `Ancre de service dupliquée : ${anchor}`
            );
        }

        serviceAnchors.add(anchor);
    }

    // =========================================================================
    // 7. PAGES LÉGALES ET MENUS
    // =========================================================================

    const enabledLegalPages =
        new Set(
            Object.entries(
                projectConfig.legalPages
                ?? {}
            )
                .filter(
                    ([, enabled]) =>
                        enabled === true
                )
                .map(
                    ([key]) => key
                )
        );

    for (
        const [menuName, entries]
        of Object.entries(
            projectConfig.menus ?? {}
        )
    ) {
        if (
            menuName === 'footer'
            && entries
            && typeof entries === 'object'
            && !Array.isArray(entries)
        ) {
            for (
                const [
                    groupName,
                    group,
                ]
                of Object.entries(entries)
            ) {
                if (
                    !group
                    || typeof group
                        !== 'object'
                    || Array.isArray(group)
                ) {
                    addIssue(
                        'config/src/project.ts',
                        `Le groupe footer.${groupName} est invalide`
                    );

                    continue;
                }

                const label = String(
                    group.label ?? ''
                ).trim();

                if (!label) {
                    addIssue(
                        'config/src/project.ts',
                        `Le groupe footer.${groupName} doit avoir un label`
                    );
                }

                validateMenuEntries(
                    `footer.${groupName}`,
                    group.items,
                    pageSlugs,
                    enabledLegalPages
                );
            }

            continue;
        }

        if (menuName === 'footer') {
            addIssue(
                'config/src/project.ts',
                'menus.footer doit être organisé en groupes'
            );

            continue;
        }

        validateMenuEntries(
            menuName,
            entries,
            pageSlugs,
            enabledLegalPages
        );
    }
} else if (projectConfig) {
    addIssue(
        'config/src/project.ts',
        'Bloc project manquant'
    );
}

// =============================================================================
// 8. CONTRÔLE PERFORMANCE NON BLOQUANT
// =============================================================================

/**
 * Analyse les médias présents dans le thème et signale les fichiers lourds.
 *
 * Les seuils sont volontairement informatifs : certains projets nécessitent
 * une qualité élevée. L'agence garde donc la décision finale.
 *
 * @param {string} directory Dossier absolu à analyser.
 * @returns {void}
 */
function checkMediaPerformance(directory) {
    if (!fs.existsSync(directory)) {
        return;
    }

    const imageExtensions = new Set([
        '.jpg',
        '.jpeg',
        '.png',
        '.webp',
        '.avif',
        '.gif',
    ]);

    const videoExtensions = new Set([
        '.mp4',
        '.webm',
        '.mov',
        '.m4v',
    ]);

    const entries = fs.readdirSync(
        directory,
        {
            withFileTypes: true,
        }
    );

    for (const entry of entries) {
        const fullPath = path.join(
            directory,
            entry.name
        );

        if (entry.isDirectory()) {
            checkMediaPerformance(fullPath);
            continue;
        }

        const extension = path
            .extname(entry.name)
            .toLowerCase();

        if (
            !imageExtensions.has(extension)
            && !videoExtensions.has(extension)
        ) {
            continue;
        }

        const size = fs.statSync(fullPath).size;
        const relative = path.relative(root, fullPath);

        if (
            imageExtensions.has(extension)
            && size > 2 * 1024 * 1024
        ) {
            addWarning(
                relative,
                `Image lourde : ${(size / 1024 / 1024).toFixed(2)} Mo (> 2 Mo)`
            );
        }

        if (
            videoExtensions.has(extension)
            && size > 15 * 1024 * 1024
        ) {
            addWarning(
                relative,
                `Vidéo lourde : ${(size / 1024 / 1024).toFixed(2)} Mo (> 15 Mo)`
            );
        }
    }
}

/**
 * Contrôle la taille des bundles compilés sans bloquer le build.
 *
 * @returns {void}
 */
function checkBuildPerformance() {
    const bundles = [
        {
            file: 'assets/build/main.css',
            warningBytes: 250 * 1024,
            label: 'CSS compilé',
        },
        {
            file: 'assets/build/main.js',
            warningBytes: 200 * 1024,
            label: 'JavaScript compilé',
        },
    ];

    for (const bundle of bundles) {
        const fullPath = path.join(
            root,
            bundle.file
        );

        if (!fs.existsSync(fullPath)) {
            continue;
        }

        const size = fs.statSync(fullPath).size;

        if (size <= bundle.warningBytes) {
            continue;
        }

        addWarning(
            bundle.file,
            `${bundle.label} volumineux : ${(size / 1024).toFixed(1)} Ko`
        );
    }
}

checkMediaPerformance(
    path.join(root, 'assets')
);

checkBuildPerformance();

// =============================================================================
// 9. STYLE TYPESCRIPT DE LA CONFIGURATION
// =============================================================================

/**
 * Vérifie les conventions d’écriture des fichiers TypeScript de configuration.
 *
 * Les fichiers TypeScript du dossier `config/src/` doivent rester lisibles comme du TypeScript :
 * - clés simples sans guillemets
 * - chaînes de caractères avec des apostrophes simples
 *
 * Le contrôle s’appuie sur l’AST TypeScript afin de ne pas confondre un guillemet
 * présent dans le contenu d’une chaîne avec le délimiteur réellement utilisé.
 *
 * @param {string} directory Dossier contenant les fichiers TypeScript à contrôler.
 * @returns {void}
 */
/**
 * Gère checkConfigTypeScriptStyle dans le module ComKani.
 *
 * Les paramètres, effets secondaires et droits doivent être vérifiés
 * avec les appels du module lors de sa maintenance.
 */
function checkConfigTypeScriptStyle(directory) {
    if (!fs.existsSync(directory)) {
        return;
    }

    const visitDirectory = (currentDirectory) => {
        for (const entry of fs.readdirSync(currentDirectory, {withFileTypes: true})) {
            const fullPath = path.join(currentDirectory, entry.name);

            if (entry.isDirectory()) {
                visitDirectory(fullPath);
                continue;
            }

            if (!entry.isFile() || path.extname(entry.name) !== '.ts') {
                continue;
            }

            const relativePath = path.relative(root, fullPath);
            const content = fs.readFileSync(fullPath, 'utf8');
            const sourceFile = ts.createSourceFile(
                fullPath,
                content,
                ts.ScriptTarget.Latest,
                true,
                ts.ScriptKind.TS
            );

            const visitNode = (node) => {
                if (ts.isStringLiteral(node)) {
                    const raw = node.getText(sourceFile);

                    if (raw.startsWith('"')) {
                        const line = sourceFile.getLineAndCharacterOfPosition(node.getStart(sourceFile)).line + 1;

                        addIssue(
                            relativePath,
                            'Chaîne TypeScript entre guillemets doubles : utiliser des apostrophes simples',
                            line,
                            getLineExcerpt(content, line)
                        );
                    }
                }

                if (
                    ts.isPropertyAssignment(node)
                    && ts.isStringLiteral(node.name)
                    && /^[A-Za-z_$][A-Za-z0-9_$]*$/.test(node.name.text)
                ) {
                    const line = sourceFile.getLineAndCharacterOfPosition(node.name.getStart(sourceFile)).line + 1;

                    addIssue(
                        relativePath,
                        'Clé TypeScript inutilement entre guillemets',
                        line,
                        getLineExcerpt(content, line)
                    );
                }

                ts.forEachChild(node, visitNode);
            };

            visitNode(sourceFile);
        }
    };

    visitDirectory(directory);
}

checkConfigTypeScriptStyle(
    path.join(root, 'config', 'src')
);

// =============================================================================
// 10. ANALYSE DES SOURCES
// =============================================================================

walk(root);

// =============================================================================
// 11. EXCEPTIONS INTERNES COMKANI
// =============================================================================

/**
 * Certains scripts ComKani contiennent volontairement les mots recherchés
 * parce qu’ils définissent eux-mêmes les règles de contrôle.
 *
 * Ces occurrences ne doivent donc pas faire échouer l’audit.
 */
const internalScriptFiles = new Set([
    'scripts/check-project.ts',
    'scripts/init-project.ts',
]);

const filtered = issues.filter((issue) => {
    if (internalScriptFiles.has(issue.file) && issue.message === 'Référence historique Annonceur') {
        return false;
    }

    return true;
});

// =============================================================================
// 12. AFFICHAGE DU RAPPORT FINAL
// =============================================================================

/**
 * Affiche les avertissements QA sans modifier le code de sortie du script.
 *
 * @returns {void}
 */
function displayWarnings() {
    if (warnings.length === 0) {
        return;
    }

    console.warn(
        `\nContrôle ComKani : ${warnings.length} avertissement(s) non bloquant(s)\n`
    );

    warnings.forEach(
        (warning, index) => {
            console.warn(
                `[W${index + 1}] ${warning.file}`
            );

            console.warn(
                `     ${warning.message}\n`
            );
        }
    );
}

if (filtered.length > 0) {
    console.error(
        `\nContrôle ComKani : ${filtered.length} anomalie(s) détectée(s)\n`
    );

    filtered.forEach(
        (issue, index) => {
            const location = issue.line
                ? `${issue.file}:${issue.line}`
                : issue.file;

            console.error(
                `[${index + 1}] ${location}`
            );

            console.error(
                `    ${issue.message}`
            );

            if (issue.excerpt) {
                console.error(
                    `    > ${issue.excerpt}`
                );
            }

            console.error('');
        }
    );

    displayWarnings();

    console.error(
        'Contrôle ComKani : échec\n'
    );

    process.exit(1);
}

displayWarnings();

console.log(
    '\nContrôle ComKani : conforme\n'
);
