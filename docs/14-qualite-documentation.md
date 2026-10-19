# Notre manière de documenter le code

## Une règle simple

Chaque fichier de code concerné doit expliquer sa mission avant son implémentation. Une personne qui découvre le starter doit pouvoir comprendre son rôle sans lire immédiatement tout son contenu.

## Les en-têtes de fichier

Indiquer : chemin du fichier, rôle, ce qu'il fait et ce qu'il ne fait pas. Ces mentions décrivent aussi les effets indirects, par exemple lorsqu'un script appelle un autre outil.

## Les fonctions

Documenter les fonctions et méthodes pertinentes avec **JSDoc** en JavaScript/TypeScript ou **PHPDoc** en PHP. Expliquer les paramètres, le retour et les comportements qui ne sont pas évidents.

## Exceptions

Les fichiers SCSS et CSS suivent leurs propres conventions. Les fichiers JSON stricts n'acceptent pas les commentaires : ils sont documentés dans le guide.

## La documentation vivante

Lorsqu'une fonction change de comportement, sa documentation et le chapitre correspondant doivent être mis à jour **dans le même lot**.

## Pour les notions complexes

Nous pouvons ajouter des schémas, des captures de notre environnement de développement et des exemples concrets, avec un texte alternatif et une légende. Ne jamais intégrer de secrets ou d'informations propres à un client dans ces illustrations.
