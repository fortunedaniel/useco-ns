# Git, maintenance et évolution du Starter

## Source de vérité

Git permet de retrouver l'origine d'une modification et de revenir en arrière. Le Starter source est distinct des projets clients dérivés et des archives de livraison.

## Avant chaque intervention

1. Vérifier la branche, les fichiers modifiés et le dernier commit validé
2. Enregistrer une modification cohérente et documenter son objectif
3. Ne pas versionner `node_modules`, les secrets, les archives temporaires ou les données personnelles de clients
4. Examiner les différences avant de supprimer un fichier. Un module PHP peut être utilisé par chargement dynamique et non par import statique
5. Lancer les contrôles réellement disponibles dans `package.json` et préparer les tests fonctionnels

## Documentation du code

Les fichiers PHP, TypeScript, JavaScript et scripts d'outillage doivent comporter un en-tête utile. Documenter les fonctions significatives avec PHPDoc ou JSDoc, en précisant leurs paramètres, leurs retours et leurs effets. Ne pas ajouter de commentaires artificiels aux fichiers SCSS. Le JSON standard n'accepte pas de commentaires.

## Maintenir un Starter réutilisable

Ne pas intégrer de contenus, paramètres SMTP ou identifiants propres à un client dans le socle. Conserver une interface WordPress compréhensible. Prévoir des configurations explicites et supprimer les outils obsolètes seulement après vérification de leurs usages.

## Retour arrière

Restaurer un fichier ou revenir à un commit précis après avoir évalué les modifications locales. Pour une opération modifiant les données WordPress, le retour arrière nécessite également une sauvegarde adaptée, car Git ne restaure pas une base de données.
