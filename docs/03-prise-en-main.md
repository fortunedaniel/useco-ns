# Prise en main du Starter

**But** : comprendre les couches, reproduire une installation, contribuer sans casser les acquis. ATTENTION : ne pas prétendre que tous les tests métier sont validés. Si des modifications sont nécessaires me prévenir pour que je juge si cela est important ou pas.

## Avant de toucher au code

1. Consulter `README.md`, `docs/02-architecture.md`, `docs/25-guide-exploitation-starter.md`, `docs/26-recette-core-1-9.md`.
2. Distinguer : Sublime Text ou VS Code(éditeur), Local (serveur de développement), WordPress (CMS), thème Starter (rendu), ComKani Core (modules métier), Node/Vite (compilation de développement), hébergement (production).
3. Localiser le thème maître **`starter-wordpress`** et le site WordPress vierge **`comkani-starter`**. Vérifier le site actif dans Local avant toute commande WP-CLI.
4. Ne jamais importer une BDD d'ancien client dans un nouveau projet. Ne jamais partager `wp-config.php` ni les secrets SMTP.

## Procédure de démarrage

- `node -v` : diagnostic en lecture seule ; affiche Node.js installé.
- `npm ci` : recrée `node_modules` dans le thème, à partir de `package-lock.json`. Ne modifie ni WordPress ni la BDD. Si installation échouée : relancer après diagnostic.
- `npm run check` : nettoie les métadonnées/copies parasites et régénère le manuel, puis contrôle la cohérence. Il **peut déplacer des copies différentes** vers une quarantaine temporaire ; vérifier le message du terminal.
- `npm run docs` : régénère et ouvre le guide local. Modifier les sources du manuel, pas `docs/index.html`.
- `npm run dev` : compile les ressources publiques en surveillance et démarre le Live Reload Local. Le terminal reste ouvert, l’arrêter avec `Ctrl + C`. Les fichiers PHP et CSS du Core ne nécessitent pas Vite pour s’exécuter.
- `npm run preprod` : génère le ZIP à tester dans une installation WordPress indépendante. Il ne déploie rien.
- `npm run prod` : génère le ZIP de production **seulement après validation de la recette**.

## Travaux de recette à entreprendre

1. Confirmer l'activation de Core dans le ZIP de recette et les permissions agence/client.
2. Tester les formulaires contact, devis, candidature, réservation : validation, stockage, erreur SMTP, protection anti-abus.
3. Tester l'éditeur, duplication, FAQ, contenu partagé, bandeau et médias.
4. Tester cache, journal, restauration depuis la corbeille et intégrations SEO/consentement.
5. Vérifier que les fonctions techniques sont inaccessibles aux comptes clients.
6. Inscrire *testé/non testé*, compte, environnement, résultat et anomalie dans `docs/26-recette-core-1-9.md` et le suivi Asana.

## Retour arrière

Conserver un ZIP de la version précédente du thème et une sauvegarde complète BDD + `uploads` avant la recette. Restaurer les fichiers précédents si régression visuelle ; pour toute migration de la BDD, restaurer la sauvegarde correspondante.
