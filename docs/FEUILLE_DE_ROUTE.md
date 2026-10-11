# Feuille de route — Velay Consentement

**Référence :** audit technique et métier du 11 octobre 2026.  
**Base Git :** `main` au commit `69abe51`.  
**Méthode :** une phase à la fois, avec une branche dédiée, des commits ciblés, des tests, un compte rendu et **validation explicite avant la phase suivante**. Pas de push ni de fusion automatique dans `main`.

## Principes transversaux

- Préserver les données et justificatifs existants : aucun test destructif sur la base de développement.
- Préserver les modifications concurrentes d'autres agents : vérifier `git status`, la branche active et l'historique avant toute intervention, puis avant chaque commit.
- Privilégier les changements isolés, réversibles et compatibles avec Symfony 7.4, PHP 8.5, EasyAdmin 5, PostgreSQL 18 et FrankenPHP.
- Tests systématiques après chaque phase : PHPUnit, Twig, YAML, conteneur, Doctrine et scénarios fonctionnels concernés. Signaler explicitement ce qui ne peut pas être vérifié.
- Ne pas confondre recueil de décision déclarative et signature électronique certifiée.
- Pas de migration de données, déploiement, envoi SMTP réel ou décision pour un vrai client sans instruction spécifique.

## Phase 1 — Stabilisation de la V1

**Objectif :** garantir les parcours essentiels et corriger les régressions observées.

1. **Responsive Clients** : corriger les sélecteurs CSS dépendant d'une classe EasyAdmin absente (`.page-index` au lieu de `.ea-index`), conserver les actions accessibles au clavier et sur mobile.
2. **Invitations concurrentes** : empêcher qu'une invitation ou son envoi ne rétablisse un statut en attente après l'enregistrement définitif d'une décision ; gérer proprement les échecs SMTP et les événements d'audit.
3. **Téléchargement des justificatifs** : vérifier les cas PDF valide, absent, altéré, identifiant inexistant et accès anonyme, sans créer de décision réelle.
4. **Tests de non-régression** : ajouter des tests ciblés aux parcours modifiés, notamment les classes réellement présentes dans le HTML EasyAdmin et les transitions métier.

**Critères de fin de phase :**
- La liste Clients se présente comme prévu sur petits écrans, sans masquer les actions essentielles.
- Aucune invitation concurrente ne peut revenir sur une décision Oui/Non finale.
- Les téléchargements refusent les preuves absentes/altérées et les utilisateurs non authentifiés.
- Les vérifications automatisées passent ; les contrôles navigateur non réalisables sont listés.
- Chaque changement est commité séparément avec un message explicite.

**État :** en cours sur `feat/roadmap-phase-1-stabilisation`.

## Phase 2 — Qualité des données et ergonomie

- Distinguer clairement raison sociale et identité du représentant ; préremplir uniquement les données connues et vérifiées dans la fiche.
- Extraire l'import CSV dans un service : validation, doublons, lots, limites, compte rendu des rejets, tests.
- Optimiser l'export CSV (itération/streaming) et vérifier les protections contre les formules Excel.
- Traduire les libellés EasyAdmin et vérifier la cohérence des écrans sur mobile/tablette/desktop.
- Ajouter des tests fonctionnels sur les filtres, actions rapides et parcours assisté.

**Critères de fin de phase :** imports et exports vérifiés sur des fixtures ; pas de confusion entre client et représentant ; UI française et recette responsive validée.

**État :** à planifier après validation de la phase 1.

## Phase 3 — Sécurité, conformité et gouvernance

- Faire valider **par les responsables concernés** le texte de consentement et les informations RGPD ; intégrer les textes validés, sans prendre de décision juridique à leur place.
- Définir responsabilité du traitement, minimisation des données, droits, conservation/purge et journalisation des accès.
- Introduire si nécessaire des autorisations différenciées (technicien, commercial, responsable) et les tester.
- Vérifier double soumission, relecture de liens, expiration, IP/proxy et protections CSRF/session.
- Définir une procédure de sauvegarde **conjointe** PostgreSQL + PDF, chiffrée et externalisée, puis tester la restauration isolée.

**Critères de fin de phase :** règles formalisées, protections testées, restauration documentée et démontrée ; validation métier/juridique explicite.

**État :** en attente.

## Phase 4 — Préparation et mise en production

- Définir l'hébergement et la topologie HTTPS/reverse-proxy, DNS, secrets, SMTP et délivrabilité.
- Figer versions/images Docker ; préparer migrations contrôlées, retour arrière et stockage persistant.
- Mettre en place supervision, journaux, alertes de pannes et contrôle des sauvegardes.
- Déployer d'abord dans un environnement de préproduction isolé puis réaliser la recette exhaustive.
- **Production réelle uniquement après autorisation explicite** et validation des prérequis de la phase 3.

**Critères de fin de phase :** préproduction validée et processus d'exploitation documenté ; mise en service distinctement approuvée.

**État :** en attente.

## Phase 5 — Évolutions métier (V2 à arbitrer)

- Définir les relances et le traitement des emails non distribués.
- Étudier un connecteur Codial fiable (référence, synchronisation, conflits, traçabilité).
- Définir des contacts/représentants multiples par client, éventuelles campagnes ou versions de consentement.
- Prévoir la révision d'un choix sans effacer la décision et les justificatifs antérieurs.
- Évaluer tableaux de bord, exports additionnels et automatisations selon le volume réel.

**Critères de fin de phase :** besoins priorisés et validés avant implémentation, tests par cas métier.

**État :** en attente.

## Journal d'exécution

| Phase | Branche | État | Tests | Validation utilisateur |
| --- | --- | --- | --- | --- |
| 1 | `feat/roadmap-phase-1-stabilisation` | En cours | À compléter | En attente |
| 2 | À définir | En attente | — | — |
| 3 | À définir | En attente | — | — |
| 4 | À définir | En attente | — | — |
| 5 | À définir | En attente | — | — |

Les résultats effectifs sont renseignés seulement après exécution des contrôles.  
