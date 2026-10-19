# Comprendre la configuration du Starter

## Image mentale

`config/src/project.ts` = identité du projet, organisation et Design System. `config/src/wordpress.ts` = pages, menus, articles et CPT. `config/src/pages/*.ts` = définitions facultatives des champs d'une page. `config/src/index.ts` = assemblage, puis `scripts/generate-project-config.ts` = traduction en PHP pour WordPress.

Le **Starter** contient les fondations. **Local** exécute WordPress et stocke ses contenus en base. **Sublime Text** sert à modifier les sources. **Node/Vite** sont utilisés au développement, pas obligatoirement sur OVH/IONOS en production.

## Qui fait quoi ?

| Fichier | Rôle | Ne fait pas |
|---|---|---|
| `config/src/project.ts` | identité, contacts, tokens | modifier WordPress en base |
| `config/src/wordpress.ts` | définir pages, menus et CPT | créer le design du projet |
| `config/src/pages/Services.ts` **(exemple à créer)** | schéma des champs Services | être chargé sans import |
| `config/src/index.ts` | assembler explicitement les sources | analyser automatiquement les fichiers du dossier |
| `config/generated/project-config.php` | rendre la configuration lisible par PHP | servir de source à éditer |
| `scripts/wp-setup.php` | synchroniser des pages/menus sur WordPress local via WP-CLI | éditer les templates |

## Exemple pratique

Pour un site avec Accueil, Services, une sous-page Maintenance, un sous-menu, un CPT Réalisations et des champs personnalisés, suivre le guide `docs/09-pages-cpt-templates.md` dans l'ordre. Une déclaration de menu n'est pas une déclaration de page. Une déclaration CPT n'est pas un template.

## Règle d'exploitation

La configuration TypeScript est la source de vérité technique. Les contenus éditoriaux sont dans la base WordPress. Avant de synchroniser : sauvegarder la base, contrôler le site Local ciblé et vérifier que les changements sont souhaités. Des éléments de navigation modifiés dans WordPress peuvent être réécrits lors de la prochaine synchronisation. Les anciennes entrées de menus ne sont pas supprimées automatiquement.
