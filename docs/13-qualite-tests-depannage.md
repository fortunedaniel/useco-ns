# Qualité, tests et dépannage

## Nos niveaux de vérification

- Contrôles statiques : cohérence, syntaxe, types, génération documentaire et packaging
- Tests dans Local : pages publiques, administration, formulaires, interactions, cache, permissions
- Recette séparée : installation et usage de l'archive comme le ferait un nouveau projet
- Production : vérifications après déploiement et suivi des incidents

**Une compilation réussie ne prouve pas que ComKani Core est entièrement testé.** Les procédures et résultats des tests doivent rester datés.

## Face à un problème

1. Identifier l'environnement concerné : Starter source, site Local, recette ou production
2. Reproduire l'erreur et noter la commande, la page et le message complet
3. Vérifier la source modifiée et les dépendances réellement chargées
4. Établir si le problème concerne PHP, SCSS, TypeScript, WordPress, Local ou le navigateur
5. Corriger une seule cause à la fois, puis effectuer un test de non-régression
6. Documenter la décision et le retour arrière

## Cas courants

- `npm run dev` reste ouvert après `watching for file changes` : comportement normal d'une surveillance continue. Arrêter avec `Ctrl + C`.
- Vite compile mais la page ne se rafraîchit pas : la compilation et le Live Reload sont deux mécanismes distincts.
- La modification de `admin.css` ne déclenche pas Vite : ce fichier n'est pas dans le pipeline du thème public.
- Un script npm n'existe pas : vérifier `package.json` avant d'utiliser une commande indiquée dans une ancienne documentation.
- Une fonctionnalité fonctionne avec un administrateur mais pas avec un autre compte : contrôler les capacités, le rôle et les conditions métier avant de modifier les permissions.

## Traçabilité

Pour chaque anomalie, conserver le contexte, la correction, le test effectué, le résultat et le statut : confirmé, à confirmer ou non testé. La documentation ne doit pas présenter une hypothèse comme une validation.
