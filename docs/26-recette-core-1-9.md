# Recette de non-régression : ComKani Core 1.9

**Statut initial : À TESTER.** Les contrôles statiques et la compilation ne remplacent pas ces vérifications.

| ID | Test sur WordPress neuf | Résultat attendu | Statut |
|---|---|---|---|
| R01 | Activation du thème et premier chargement | Core et deux tables chargés, aucun warning | À TESTER |
| R02 | Dashboard avec compte administrateur | Santé + widgets modules activés seulement | À TESTER |
| R03 | Dashboard avec compte client restreint | Aucun accès technique indu ; widgets autorisés | À TESTER |
| R04 | Contact, devis, réservation, candidature | Enregistrement conforme pour chaque formulaire | À TESTER |
| R05 | Tentatives répétées et purge du cache | Compteurs anti-abus toujours actifs | À TESTER |
| R06 | SMTP : configuration valide puis erreur simulée | Message envoyé ou erreur tracée ; demande conservée | À TESTER |
| R07 | Candidature avec fichier non autorisé | Rejet sans fichier public accessible | À TESTER |
| R08 | Prévisualiser, optimiser puis restaurer un média | Sources sauvegardées et médias intacts | À TESTER |
| R09 | FAQ, édition et contenus partagés | Affichage et droits conformes au module actif | À TESTER |
| R10 | Dupliquer page, article, brouillon | Copie distincte, original inchangé | À TESTER |
| R11 | SEO avec Yoast actif puis absent | Absence de données structurées en double | À TESTER |
| R12 | Consentement accepté, refusé et retiré | Aucun traceur non essentiel avant autorisation | À TESTER |
| R13 | URL de connexion personnalisée | Nouvelle route utilisable et accès récupérable | À TESTER |
| R14 | Export SQL / import en bac à sable | Sauvegarde, récupération et droits agence | À TESTER |
| R15 | Audit préproduction / production | Sans source npm, secrets, fichiers parasites | À TESTER |
| R16 | PHP 8.2+ / WordPress récent / OVH / IONOS | Absence d'erreurs et compatibilité vérifiée | À TESTER |

## Procédure de validation

Pour chaque scénario, noter : environnement (Local/recette/préproduction), version WordPress/PHP/Core, utilisateur, configuration, résultat observé, capture ou log, statut **VALIDÉ / À CORRIGER**, et correctif éventuel. Un résultat non exécuté reste « À TESTER ».

## Retour arrière

Avant une installation, exporter BDD et fichiers ou faire un point de restauration Local. Une régression Core se traite par restauration de l'archive précédente et, si nécessaire, de la sauvegarde BDD. Ne jamais restaurer uniquement la BDD si ses médias ont été supprimés.
