# Administration WordPress et ComKani Core

## Notre objectif

L'administration doit rester facile à comprendre et à utiliser pour les clients. ComKani Core apporte des fonctions mutualisées au Starter; il ne doit pas rendre le client dépendant d'un outil de compilation front-end.

## Où sont les fichiers

- Modules PHP : `packages/comkani-core/includes/`
- Styles d'administration : `packages/comkani-core/assets/admin.css`
- Scripts autonomes du Core : `packages/comkani-core/assets/`
- Chargement des modules : vérifier le fichier principal du Core et les hooks WordPress avant d'affirmer qu'un fichier est inutilisé

## Fonctions à contrôler dans la recette

- Rôles, capacités, accès au tableau de bord et masquage des actions non autorisées
- Formulaires et validation des champs, anti-spam, envoi et journalisation selon configuration
- Médias, contenus partagés, duplication et suppression/restauration lorsque disponible
- Journal d'activité et distinction entre un contenu dans la corbeille et une suppression définitive
- Cache et invalidation ciblée, sans effacer aveuglément des transients tiers
- SMTP, réglages de sécurité et affichage des erreurs en environnement local

## Exemple : changer une couleur d'administration

Modifier `packages/comkani-core/assets/admin.css`, puis enregistrer. Ce fichier est servi directement par WordPress et ne passe pas par la compilation Vite. Avec le Live Reload local actif, le navigateur peut s’actualiser automatiquement. Sinon, actualiser la page et vérifier le cache du navigateur ainsi que les règles CSS actives.

## Précautions

Les paramètres secrets (mots de passe SMTP, clés, jetons) restent hors Git. Les opérations destructives sur la base ou les médias exigent une sauvegarde et un environnement identifié. Ne jamais confondre le site Local principal et le site de recette.
