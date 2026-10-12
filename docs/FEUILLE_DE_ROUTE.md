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

**État :** validée pour fusion dans `main` le 12 octobre 2026, après nouveaux tests automatisés ; la recette visuelle sur appareils réels reste explicitement à compléter.

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
| 1 | `feat/roadmap-phase-1-stabilisation` | Implémentée et testée | 23 tests / 90 assertions, contrôles Symfony et Doctrine OK | Poursuite phase 2 autorisée |
| 2 | `feat/roadmap-phase-2-data-ux` | Validée pour fusion | 38 tests / 230 assertions, Twig/YAML/Doctrine/Compose/JS OK | Fusion dans `main` demandée le 12/10/2026 |
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

**Décision reçue :** l'utilisateur a autorisé la poursuite avec la phase 2. Les travaux de la phase 1 restent hors de `main` et sont inclus dans la branche phase 2.

## Résultats et reprise — phase 2 (11 octobre 2026)

**Commits de la phase :**
- `b0c5dad` — fiche client enrichie de trois champs de représentant facultatifs et migration Doctrine additive ;
- `be95442` — import CSV robuste, bilan des erreurs, export en streaming et tests PostgreSQL ;
- `fcfebfe` — fiabilisation du rapport d'import en interface, injection du service et gestion mémoire ciblée ;
- `ced8a68` — EasyAdmin en français, groupes de formulaire, historique traduit et tests fonctionnels.
- `27a1cc4` — titres des formulaires clients et assertions complémentaires sur le parcours de création.

**Fonctions livrées :**
- Séparation raison sociale / représentant, sans déduire l'identité de la personne du nom du commerce.
- Informations du représentant facultatives, éditables dans EasyAdmin et proposées dans le formulaire assisté (aucune décision présélectionnée).
- Import CSV UTF-8 (`;`), 1 Mo / 3 000 lignes : validation complète avant insertion, détection de doublons insensible à la casse, insertions par lots de 100 sous transaction PostgreSQL et bilan des erreurs (40 détails maximum). Les clients existants ne sont pas modifiés.
- Export CSV toujours sur les 3 colonnes prévues, en parcours progressif et avec neutralisation des formules Excel.
- Administration en français, champs regroupés et historique de suivi traduit.

**Contrôles exécutés et réussis :**
- PHPUnit complet avec PostgreSQL isolé : **33 tests, 172 assertions, aucun échec**.
- Twig : 13 templates valides ; YAML : 11 fichiers valides ; conteneur Symfony valide.
- Doctrine : mapping et schéma applicatif synchronisés ; 3 migrations appliquées, aucune nouvelle migration en attente.
- Docker Compose : validation de la configuration (`config --quiet`) réussie ; Git : `diff --check` sans erreur.
- Tests HTTP : index en français, filtre de statuts, actions rapides, saisie assistée préremplie, formulaire CSV et bilan de validation ; tests d'intégration avec données fictives sur `velay_phase1_test` exclusivement.
- Inspection Brave desktop : `/admin/client` affiche « Filtres », « Ajouter un client », et une balise `<html lang="fr">` ; l'écran `/admin/import` charge correctement.

**Migration locale appliquée :** `DoctrineMigrations\\Version20261011191500`. Avant la migration, une **sauvegarde temporaire de la base de développement** a été créée dans le conteneur PostgreSQL sous `/tmp/velay-consentement-pre-phase2.dump`. Ce fichier interne n'est pas une stratégie de sauvegarde externalisée. La migration ajoute trois colonnes `VARCHAR(120) NULL` à `client`, sans mise à jour des décisions existantes.

**Points restant hors périmètre ou à valider manuellement :**
- Vérification visuelle réelle à 320, 375, 390, 768, 1024 et 1440 px, et changement d'orientation tablette/mobile ; les tests HTML/CSS ne remplacent pas cette recette.
- Contrôle humain des imports/exports réellement téléchargés et ouverture sur Excel.
- SMTP réel, hébergement HTTPS, RGPD et sauvegarde/restauration externalisée (phases 3 et 4).

**Règle de reprise :** ne démarrer la phase 3, ne fusionner dans `main` et ne déployer publiquement qu'après instruction explicite. La branche phase 2 contient la phase 1 en historique, car celle-ci n'avait pas encore été fusionnée à `main`.

## Correction après retour utilisateur — responsive mobile (11 octobre 2026)

**Retour :** capture de la liste Clients sur mobile avec superposition des libellés « Email d'invitation », « Statut », « Date » et « Actions rapides ».

**Cause établie :** EasyAdmin 5 fournit un style datagrid mobile avec pseudo-libellés en `position: absolute`, largeur `35%`, et retrait de `35%` sur les cellules. Les styles de fiches personnalisés ne réinitialisaient pas ces propriétés.

**Branche de correction :** `fix/responsive-review-phase2` basée sur `feat/roadmap-phase-2-data-ux`.

**Commits :**
- `9a3e0d1` : réinitialisation ciblée des styles mobiles du tableau Clients, vrais libellés des boutons, meilleure disposition des tableaux de suivi, en-tête adapté aux écrans étroits et tests de garde CSS ;
- `ab88e38` : tests de non-régression sur les pages publiques et les écrans administratifs (liste, ajout, édition, formulaire assisté, suivi, import CSV).

**Contrôles effectués :**
- Suite complète avec PostgreSQL de test isolé : **38 tests et 224 assertions réussis**.
- 13 templates Twig, 11 fichiers YAML, conteneur Symfony, schéma Doctrine : valides.
- Inspection HTML en Brave : index Clients, nouveau client, modification, suivi / preuves, import CSV, accueil et confirmation ; ouverture du filtre vérifiée.
- Le formulaire public et le cas d'un lien invalide sont également couverts par des tests HTTP.

**Limites et contrôle utilisateur restant :** les outils navigateur disponibles permettent la navigation et l'inspection du DOM, mais **pas un contrôle visuel effectif à toutes les largeurs d'écran**. La checklist des tailles **320 / 375 / 390 / 768 / 1024 / 1440 px** et des parcours est consignée dans `docs/RESPONSIVE_QA.md`. La fusion dans `main` a été expressément demandée le 12 octobre 2026 ; le lancement de la phase 3 reste soumis à une instruction distincte.
