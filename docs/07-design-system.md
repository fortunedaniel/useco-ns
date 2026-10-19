# Design System et styles du Starter

## Pourquoi nous utilisons un Design System

Nous centralisons les couleurs, espacements, typographies et règles visuelles pour obtenir un rendu cohérent et faciliter l'évolution des projets. Les valeurs spécifiques d'un client doivent être configurées pour son projet, sans devenir des valeurs permanentes du Starter générique.

## Où intervenir

- Configuration du projet : les fichiers de configuration du Starter.
- Styles du site public : fichiers SCSS du thème, puis génération dans `assets/build/main.css`.
- Interface d'administration du Core : `packages/comkani-core/assets/admin.css`, chargé directement par WordPress.

## Procédure de travail

1. Identifier la valeur ou le composant à modifier et vérifier s'il existe déjà un token réutilisable
2. Modifier la source SCSS plutôt que le fichier CSS compilé
3. Enregistrer, laisser Vite compiler et contrôler le résultat dans Local
4. Tester plusieurs tailles d'écran et les états survol, focus et erreur si nécessaire

## Limites

`npm run dev` ne signifie pas que toutes les pages du navigateur se rechargent automatiquement. Le Live Reload dépend du script de développement réellement installé et de la configuration locale. Le CSS autonome de ComKani Core ne foncitonne pas par la compilation Vite du thème.

## Vérification et retour arrière

Contrôler le CSS produit, l'interface sur Local et la console du navigateur. En cas de régression, revenir au commit Git précédent ou restaurer le fichier source sauvegardé. Ne pas corriger uniquement `assets/build/main.css` : cette modification serait écrasée par une nouvelle compilation.

## Familles de couleurs extensibles selon le projet

**Image mentale :** `primary` et `secondary` constituent les familles de base du Starter. Un projet peut déclarer d'autres familles dans les exports Figma, par exemple `tertiary`, `quaternary` ou `brand-gold`. Le générateur lit désormais toutes les familles présentes, au lieu de se limiter à une liste fixe.

**Source de vérité :** `config/tokens/colors/light.tokens.json` et `config/tokens/colors/dark.tokens.json`. Les familles `primary` et `secondary` sont obligatoires. Les autres sont facultatives, mais doivent être présentes **avec les mêmes noms de nuances dans les deux modes** pour garantir une structure stable. Le générateur conserve les noms des familles et les numéros de nuances, par exemple `tertiary.500`.

Exemple de famille supplémentaire dans **les deux fichiers JSON** :

```json
"tertiary": {
    "500": {
        "$type": "color",
        "$value": {
            "colorSpace": "srgb",
            "components": [0.80, 0.60, 0.20],
            "alpha": 1,
            "hex": "#CC9933"
        }
    }
}
```

L'extrait est une propriété à ajouter dans l'objet JSON existant, **pas un fichier JSON complet**. Respecter la virgule après la famille précédente et utiliser les vraies valeurs exportées depuis Figma. Le mode Dark peut avoir une autre valeur hexadécimale pour `tertiary.500`, mais le nom de la famille et de la nuance doivent correspondre.

Après `npm run internal:generate`, le générateur crée `--cl-tertiary-500` dans les fichiers SCSS générés du mode correspondant. Utilisation dans un composant :

```scss
.project-highlight {
    background-color: var(--cl-tertiary-500);
}
```

**Ne pas éditer** `resources/scss/theme/_colors-light.scss` ou `_colors-dark.scss` directement : ils sont régénérés. Le système conserve `notification` comme famille de couleurs fonctionnelles avec ses noms (`success`, `warning`, `critical`, etc.). Un nom de famille est en minuscules avec tirets éventuels ; les nuances de palette sont numériques.

**Vérifier :** exécuter le typecheck et la génération, regarder la présence de `--cl-tertiary-500` dans les deux SCSS et vérifier l'affichage en Light/Dark. **Retour arrière :** restaurer les JSON depuis Git puis relancer le générateur. Cela ne touche pas directement WordPress ni ses contenus.

**Attention :** générer des noms dynamiques ne crée pas automatiquement des rôles sémantiques (`--action-primary`, `--surface-default`, etc.). Ces rôles pourront être définis séparément pour stabiliser les composants réutilisables entre projets.
