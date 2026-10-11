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

**État :** implémentée et testée sur `feat/roadmap-phase-1-stabilisation` (11 octobre 2026). En attente de validation utilisateur ; contrôle visuel sur de vrais appareils restant à effectuer.

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
| 1 | `feat/roadmap-phase-1-stabilisation` | Implémentée et testée | 23 tests / 90 assertions, contrôles Symfony et Doctrine OK | En attente |
| 2 | À définir | En attente | — | — |
| 3 | À définir | En attente | — | — |
| 4 | À définir | En attente | — | — |
| 5 | À définir | En attente | — | — |

## Résultats et reprise — phase 1 (11 octobre 2026)

**Commits de la phase :**
- `beae928` — création de la feuille de route ;
- `2dbd37b` — corrections CSS mobile et test de garde ;
- `ce2baae` — cycle des invitations sécurisé par des transactions et verrouillage cohérent ;
- `fe9f9c9` — tests des décisions, du SMTP simulé, de PostgreSQL et des justificatifs.

**Contrôles exécutés et réussis :**
- PHPUnit complet avec tests PostgreSQL activés : **23 tests, 90 assertions, 0 échec**.
- Twig : 13 templates syntaxiquement valides.
- YAML : 11 fichiers de configuration valides.
- Conteneur de services Symfony : valide.
- Doctrine : mapping valide, schéma de la base applicative en phase avec les entités.
- Doctrine Migrations : 2 migrations appliquées, aucune en attente.
- Navigateur Brave : la page `/admin/client` rend `<body class="ea ea-index ...">`, inclut `assets/admin.css` et fournit la colonne `quickActions` utilisée par les sélecteurs corrigés.
- Test HTTP du téléchargement : redirection vers le login sans authentification, HTTP 404 pour ID inconnu ou fichier altéré, téléchargement valide avec en-têtes privés.
- Test avec deux EntityManager PostgreSQL : une invitation créée depuis un statut obsolète est refusée, et une décision enregistrée pendant le transport SMTP reste définitive.
- Parcours de consentement public sur la base de test : jeton à usage unique, décision enregistrée, récépissé PDF généré.

**Exécuter les tests :**

```bash
# Prérequis ponctuel : créer une base dédiée, et jamais la base `velay`.
docker compose -f compose.yaml -f compose.dev.yaml exec -T database createdb -U velay velay_phase1_test

# Si cette base existe déjà, ne pas relancer createdb.
docker compose -f compose.yaml -f compose.dev.yaml exec -T -e VELAY_RUN_DB_TESTS=1 -e APP_DEBUG=0 app php vendor/bin/simple-phpunit
docker compose -f compose.yaml -f compose.dev.yaml exec -T app php bin/console lint:twig templates
docker compose -f compose.yaml -f compose.dev.yaml exec -T app php bin/console lint:yaml config
docker compose -f compose.yaml -f compose.dev.yaml exec -T app php bin/console lint:container
docker compose -f compose.yaml -f compose.dev.yaml exec -T app php bin/console doctrine:schema:validate
```

**Important :** les tests d'intégration sont désactivés par défaut. Avec `VELAY_RUN_DB_TESTS=1`, ils utilisent **exclusivement `velay_phase1_test`** et remettent les tables de cette base de test à zéro entre scénarios. Ne pas exécuter les tests d'intégration simultanément dans plusieurs agents partageant cette base. Les fichiers PDF de test sont générés dans des répertoires temporaires et supprimés après exécution ; aucun email réel n'est envoyé.

**Points non validés automatiquement :**
- Inspection visuelle sur de vrais écrans mobiles et tablettes aux largeurs 320, 375, 390, 768, 1024 et 1440 px, avec rotation.
- Utilisation SMTP réel, HTTPS public et sauvegarde/restauration (phases 3 et 4).
- La correction du préremplissage d'identité commerciale et les améliorations d'import CSV appartiennent à la phase 2.

**Décision requise :** attendre la revue et l'accord utilisateur avant de passer à la phase 2 ou de fusionner dans `main`.
