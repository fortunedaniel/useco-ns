# Inventaire technique du Starter ComKani

**Référence : archive `comkani-starter(6).zip`, analysée le 10 octobre 2026.** L’inventaire ci-dessous décrit les fichiers réellement présents dans cette copie. Il ne constitue pas une validation fonctionnelle des modules WordPress.

## Règles de conservation

- Ne pas supprimer un module PHP uniquement parce qu’il ne figure pas dans les imports TypeScript : WordPress charge aussi les fichiers par hooks, inclusions et conditions.
- Les fichiers SCSS sont des sources à préserver, sans ajout d’en-têtes documentaires.
- Les fichiers JSON standards ne peuvent pas recevoir de commentaires PHPDoc/JSDoc.
- `node_modules/`, `release/`, `.DS_Store`, `__MACOSX/` et les fichiers `._*` ne font pas partie de l’archive source livrable. Leur présence dans le ZIP reçu ne signifie pas qu’ils doivent être versionnés.
- Les fichiers compilés et générés doivent être reconstruits depuis leurs sources, jamais modifiés à la main.

## Contrôles sur les fonctionnalités annoncées

| Domaine | Éléments trouvés dans le code | Conclusion sur le code | Validation fonctionnelle |
|---|---|---|---|
| Composants facultatifs PHP | `inc/components.php` et `inc/component-helpers.php` | Activation conditionnelle présente pour contact, booking, faq et whatsapp | À confirmer sous WordPress |
| Composants TypeScript | `vite.config.ts`, `config/src/features/components.ts` | Entrée Vite virtuelle qui sélectionne booking, faq et testimonials selon la configuration | Exclusion réelle du bundle à tester avec une activation et une désactivation |
| Édition des pages | `packages/comkani-core/includes/editor.php` | Désactivation Gutenberg pour les pages, retrait conditionnel du support éditeur natif ; exception prévue pour les pages légales reconnues | Tester page standard, page légale, page non configurée |
| Articles et CPT | `packages/comkani-core/includes/post-types.php` | Gestion des types de contenus présente ; règles spécifiques distinctes des pages | Vérifier les permissions et écrans d’édition |
| Duplication | `packages/comkani-core/includes/duplicate.php` | Module présent dans le Core | Tester contenu, métadonnées et rôles |
| Formulaires | `packages/comkani-core/includes/requests.php`, `request-settings.php`, `smtp.php` | Modules présents | Tester de bout en bout les envois, notifications et anti-spam |
| Journal d’activité | `packages/comkani-core/includes/activity-log.php` | Module présent | Tester permissions et restauration de contenus |
| Live Reload WordPress | `scripts/dev-live-reload.cjs`, `inc/dev-live-reload.php` | Mécanisme local présent | Tester dans navigateur Local |
| Manuel | `scripts/docs/generate-guide.ts`, `scripts/docs/dev.cjs` | Génération HTML et surveillance des Markdown présentes | Vérifier le rafraîchissement navigateur sur Mac |
| Livraison | `scripts/package-project.ts`, `scripts/lib/package-theme.ts` | Scripts de conditionnement présents | Vérifier archives produites et installation distante |

## Scripts npm du ZIP reçu

Les commandes officielles sont définies par le champ `scripts` de `package.json`. `npm run qa` n’existe pas dans la version analysée. `npm run dev` est une surveillance continue et ne se termine pas tout seul.

| Commande | Action déclarée |
|---|---|
| `npm run init` | `tsx scripts/init-project.ts` |
| `npm run dev` | `npm run internal:dev-build` |
| `npm run check` | `npm run internal:clean && npm run internal:docs-generate && tsx scripts/check-project.ts` |
| `npm run build` | `npm run internal:generate && vite build` |
| `npm run preprod` | `npm run internal:verify-release && tsx scripts/package-project.ts preproduction` |
| `npm run prod` | `npm run internal:verify-release && tsx scripts/package-project.ts production` |
| `npm run docs` | `npm run internal:docs-generate && tsx scripts/docs/serve.ts` |
| `npm run internal:generate` | `tsx scripts/generate-project-config.ts && tsx scripts/generate-design-system.ts` |
| `npm run internal:verify-release` | `npm run internal:generate && npm run internal:lint-scss && npm run internal:typecheck && vite build && npm run check` |
| `npm run internal:lint-scss` | `tsx scripts/check-scss.ts` |
| `npm run internal:typecheck` | `tsc --noEmit` |
| `npm run internal:docs-generate` | `tsx scripts/docs/generate-guide.ts` |
| `npm run internal:dev-build` | `npm run internal:generate && node scripts/dev-live-reload.cjs` |
| `npm run internal:release` | `tsx scripts/release.ts` |
| `npm run internal:clean` | `tsx scripts/clean-system.ts` |
| `npm run internal:wp-setup` | `tsx scripts/wp/run.ts setup` |
| `npm run internal:wp-setup-adopt` | `tsx scripts/wp/run.ts setup:adopt` |
| `npm run internal:wp-setup-prune` | `tsx scripts/wp/run.ts setup:prune` |
| `npm run internal:wp-db-check` | `tsx scripts/wp/run.ts db:check` |
| `npm run internal:wp-db-clean` | `tsx scripts/wp/run.ts db:clean` |
| `npm run internal:wp-themes-check` | `tsx scripts/wp/run.ts themes:check` |
| `npm run internal:wp-themes-clean` | `tsx scripts/wp/run.ts themes:clean` |
| `npm run internal:wp-cache` | `tsx scripts/wp/run.ts cache` |

## Inventaire des chemins présents

Cette liste est générée à partir des sources extraites, hors dépendances npm et archives de livraison. Les fichiers `docs/*.md` sont présentés selon le nouvel ordre de lecture.

### PHP et templates (56)

- `404.php`
- `archive.php`
- `components/booking/booking.php`
- `components/contact/contact.php`
- `components/faq/faq.php`
- `components/testimonials/testimonials.php`
- `components/whatsapp/whatsapp.php`
- `config/generated/project-config.php` (généré, ne pas éditer directement)
- `footer.php`
- `front-page.php`
- `functions.php`
- `header.php`
- `home.php`
- `inc/assets.php`
- `inc/component-helpers.php`
- `inc/components.php`
- `inc/dev-live-reload.php`
- `inc/project.php`
- `inc/theme.php`
- `inc/uploads.php`
- `index.php`
- `packages/comkani-core/comkani-core.php`
- `packages/comkani-core/includes/activity-log.php`
- `packages/comkani-core/includes/admin.php`
- `packages/comkani-core/includes/cache.php`
- `packages/comkani-core/includes/capabilities.php`
- `packages/comkani-core/includes/consent.php`
- `packages/comkani-core/includes/database.php`
- `packages/comkani-core/includes/duplicate.php`
- `packages/comkani-core/includes/editor.php`
- `packages/comkani-core/includes/faq.php`
- `packages/comkani-core/includes/integrations.php`
- `packages/comkani-core/includes/legal-pages.php`
- `packages/comkani-core/includes/media.php`
- `packages/comkani-core/includes/notification-bar.php`
- `packages/comkani-core/includes/post-types.php`
- `packages/comkani-core/includes/profile.php`
- `packages/comkani-core/includes/project.php`
- `packages/comkani-core/includes/request-settings.php`
- `packages/comkani-core/includes/requests-privacy.php`
- `packages/comkani-core/includes/requests.php`
- `packages/comkani-core/includes/security.php`
- `packages/comkani-core/includes/seo.php`
- `packages/comkani-core/includes/setup.php`
- `packages/comkani-core/includes/shared-content.php`
- `packages/comkani-core/includes/smtp.php`
- `page.php`
- `scripts/wp-setup.php`
- `scripts/wp/cache-clear.php`
- `scripts/wp/database-cleanup.php`
- `scripts/wp/theme-cleanup.php`
- `search.php`
- `single.php`
- `templates/legal/mentions.php`
- `templates/legal/privacy-cookies.php`
- `templates/page-legal.php`

### TypeScript / JavaScript (50)

- `assets/build/main 2.js` (généré, ne pas éditer directement)
- `assets/build/main 3.js` (généré, ne pas éditer directement)
- `assets/build/main.js` (généré, ne pas éditer directement)
- `components/booking/booking.ts`
- `components/faq/faq.ts`
- `components/testimonials/testimonials.ts`
- `config/framework/config.ts`
- `config/framework/editor.ts`
- `config/framework/fields.ts`
- `config/framework/wordpress.ts`
- `config/src/features/components.ts`
- `config/src/features/consent.ts`
- `config/src/features/duplication.ts`
- `config/src/features/editor.ts`
- `config/src/features/forms.ts`
- `config/src/features/security.ts`
- `config/src/features/seo.ts`
- `config/src/features/shared-content.ts`
- `config/src/index.ts`
- `config/src/project.ts`
- `config/src/wordpress.ts`
- `packages/comkani-core/assets/admin.js`
- `packages/comkani-core/assets/consent.js`
- `packages/comkani-core/assets/editor.js`
- `packages/comkani-core/assets/media.js`
- `postcss.config.js`
- `resources/ts/layout/header-scroll.ts`
- `resources/ts/main.ts`
- `resources/ts/shared/button-motion.ts`
- `resources/ts/shared/modal.ts`
- `resources/ts/shared/motion.ts`
- `resources/ts/shared/navigation.ts`
- `resources/ts/shared/scroll-shift.ts`
- `scripts/check-project.ts`
- `scripts/check-scss.ts`
- `scripts/clean-system.ts`
- `scripts/dev-live-reload.cjs`
- `scripts/docs/dev.cjs`
- `scripts/docs/generate-guide.ts`
- `scripts/docs/serve.ts`
- `scripts/generate-design-system.ts`
- `scripts/generate-project-config.ts`
- `scripts/init-project.ts`
- `scripts/lib/package-theme.ts`
- `scripts/lib/project-config-validation.ts`
- `scripts/lib/project-config.ts`
- `scripts/package-project.ts`
- `scripts/release.ts`
- `scripts/wp/run.ts`
- `vite.config.ts`

### SCSS et styles (34)

- `assets/build/main 2.css` (généré, ne pas éditer directement)
- `assets/build/main.css` (généré, ne pas éditer directement)
- `packages/comkani-core/assets/admin.css`
- `packages/comkani-core/assets/login.css`
- `packages/comkani-core/assets/toolbar.css`
- `resources/scss/base/_animations.scss`
- `resources/scss/base/_global.scss`
- `resources/scss/base/_reset.scss`
- `resources/scss/components/_booking.scss`
- `resources/scss/components/_buttons.scss`
- `resources/scss/components/_consent.scss`
- `resources/scss/components/_faq.scss`
- `resources/scss/components/_footer.scss`
- `resources/scss/components/_forms.scss`
- `resources/scss/components/_header.scss`
- `resources/scss/components/_modal.scss`
- `resources/scss/components/_testimonials.scss`
- `resources/scss/components/_whatsapp.scss`
- `resources/scss/foundations/_effects.scss`
- `resources/scss/foundations/_layout.scss`
- `resources/scss/foundations/_motions.scss`
- `resources/scss/foundations/_radius.scss`
- `resources/scss/foundations/_shadows.scss`
- `resources/scss/foundations/_spacings.scss`
- `resources/scss/pages/_legal.scss`
- `resources/scss/pages/_page-404.scss`
- `resources/scss/style.scss`
- `resources/scss/theme/_colors-dark.scss`
- `resources/scss/theme/_colors-light.scss`
- `resources/scss/theme/_mixins.scss`
- `resources/scss/theme/_typography-custom.scss`
- `resources/scss/theme/_typography.scss`
- `resources/scss/theme/_variables.scss`
- `style.css`

### Configuration, médias et autres (29)

- `.gitignore`
- `.nvmrc`
- `README.md`
- `config/development.example.json`
- `config/development.json`
- `config/generated/manifest.json` (généré, ne pas éditer directement)
- `config/tokens/borders.tokens.json`
- `config/tokens/colors/dark.tokens.json`
- `config/tokens/colors/light.tokens.json`
- `config/tokens/effects.tokens.json`
- `config/tokens/grid.tokens.json`
- `config/tokens/motions.tokens.json`
- `config/tokens/radius.tokens.json`
- `config/tokens/shadows.tokens.json`
- `config/tokens/spacings/desktop.tokens.json`
- `config/tokens/spacings/mobile.tokens.json`
- `config/tokens/spacings/tablet.tokens.json`
- `config/tokens/typography/desktop.tokens.json`
- `config/tokens/typography/mobile.tokens.json`
- `config/tokens/typography/styles.tokens.json`
- `config/tokens/typography/tablet.tokens.json`
- `docs/index.html` (généré, ne pas éditer directement)
- `docs/media/architecture-starter.svg`
- `package-lock.json`
- `package.json`
- `packages/comkani-core/assets/logo-agence-comkani-white.svg`
- `screenshot.png`
- `theme.json`
- `tsconfig.json`

### Sources Markdown (29)

- `docs/00-preambule.md`
- `docs/01-comprendre-le-starter.md`
- `docs/02-architecture.md`
- `docs/03-prise-en-main.md`
- `docs/04-configuration.md`
- `docs/05-commandes.md`
- `docs/06-initialisation.md`
- `docs/07-design-system.md`
- `docs/08-typescript-et-javascript.md`
- `docs/09-pages-cpt-templates.md`
- `docs/10-composants.md`
- `docs/11-comkani-core.md`
- `docs/12-administration-core.md`
- `docs/13-qualite-tests-depannage.md`
- `docs/14-qualite-documentation.md`
- `docs/15-preproduction-production.md`
- `docs/16-parcours-livraison.md`
- `docs/17-git-et-maintenance.md`
- `docs/18-contribuer-au-guide.md`
- `docs/19-audit-etat-starter.md`
- `docs/20-audit-technique.md`
- `docs/21-generalisation-v1.md`
- `docs/22-demarrage-multipage.md`
- `docs/23-limites-et-tests.md`
- `docs/24-securite-dependances.md`
- `docs/25-guide-exploitation-starter.md`
- `docs/26-recette-core-1-9.md`
- `docs/27-inventaire-fichiers.md`
- `docs/28-verification-documentation-code.md`
- `docs/28-verification-documentation-code.md`

## Limites de cette vérification

La présence et la cohérence des déclarations ont été comparées avec les sources fournies. Un module présent ne prouve pas son fonctionnement complet. Les tests navigateur WordPress Local, les tests de formulaires SMTP, la restauration, la vérification détaillée des droits et la livraison sur hébergement restent des contrôles distincts.
