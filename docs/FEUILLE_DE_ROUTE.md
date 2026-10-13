# Feuille de route — Velay Consentement

**Référence :** audit technique et métier du 11 octobre 2026.  
**Base Git :** `main` au commit `69abe51`.  
**Méthode :** une phase à la fois, avec commits ciblés, tests, compte rendu et **validation explicite avant la suivante**. **Depuis la phase 3, les développements sont effectués directement sur `main` à la demande de l'utilisateur** (12 octobre 2026). Aucun push distant sans demande.

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

**État :** intégrée à `main` le 12 octobre 2026 avec la phase 2 ; le contrôle visuel sur appareils réels reste documenté dans `docs/RESPONSIVE_QA.md`.

## Phase 2 — Qualité des données et ergonomie

- Distinguer clairement raison sociale et identité du représentant ; préremplir uniquement les données connues et vérifiées dans la fiche.
- Extraire l'import CSV dans un service : validation, doublons, lots, limites, compte rendu des rejets, tests.
- Optimiser l'export CSV (itération/streaming) et vérifier les protections contre les formules Excel.
- Traduire les libellés EasyAdmin et vérifier la cohérence des écrans sur mobile/tablette/desktop.
- Ajouter des tests fonctionnels sur les filtres, actions rapides et parcours assisté.

**Critères de fin de phase :** imports et exports vérifiés sur des fixtures ; pas de confusion entre client et représentant ; UI française et recette responsive validée.

**État :** fusionnée dans `main` le 12 octobre 2026 (fast-forward, sans conflit) ; tests post-fusion réussis. La recette visuelle sur appareils réels reste à compléter.

## Phase 3 — Sécurité, conformité et gouvernance

- Faire valider **par les responsables concernés** le texte de consentement et les informations RGPD ; intégrer les textes validés, sans prendre de décision juridique à leur place.
- Définir responsabilité du traitement, minimisation des données, droits, conservation/purge et journalisation des accès.
- Introduire si nécessaire des autorisations différenciées (technicien, commercial, responsable) et les tester.
- Vérifier double soumission, relecture de liens, expiration, IP/proxy et protections CSRF/session.
- Définir une procédure de sauvegarde **conjointe** PostgreSQL + PDF, chiffrée et externalisée, puis tester la restauration isolée.

**Critères de fin de phase :** règles formalisées, protections testées, restauration documentée et démontrée ; validation métier/juridique explicite.

**État :** protections techniques et gouvernance documentaire implémentées sur `main` (12 octobre 2026). **Clôture juridique et opérationnelle conditionnelle** : validation des textes/mentions et durées par Velay Précision ; destination externe, secret de production et test `selftest` depuis un terminal opérateur autorisé encore à réaliser. Phase 4 non commencée.

## Ajustement demandé avant la phase 4 (13 octobre 2026)

- Fiche client : bouton « … » dans l'en-tête EasyAdmin ; panneau natif `dialog` coulissant depuis la droite, avec **Lien individuel** et **Clôture de la relation contractuelle**.
- Les informations, décisions, justificatifs et historique demeurent dans le corps de page.
- Les rôles (opérateur / administrateur / auditeur), les validations CSRF, les statuts bloquants et le parcours POST → redirection sont préservés.
- Après génération d'un lien, le panneau s'ouvre automatiquement pour présenter le lien **Copier / Ouvrir**.
- Contrôles et tests HTML/JS/CSS : voir `docs/RESPONSIVE_QA.md` ; recette visuelle en tailles réelles à confirmer.
- Aucun développement ni déploiement de la phase 4 n'a été commencé.

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
| 1 | `feat/roadmap-phase-1-stabilisation` | Fusionnée dans `main` avec la phase 2 | 23 tests / 90 assertions lors de la phase 1 | Poursuite phase 2 autorisée |
| 2 | `feat/roadmap-phase-2-data-ux` | Fusionnée dans `main` | 38 tests / 230 assertions, Twig/YAML/Doctrine/JS OK après fusion | Fusion effectuée le 12/10/2026 |
| 3 | `main` | Socle technique implémenté, validations externes en attente | Tests sécurité + chiffrement + PostgreSQL fictif (voir bilan) | Revue RGPD / exploitation requise |
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

**Règle de reprise :** les phases 1 et 2 ont été fusionnées dans `main` à la demande de l'utilisateur ; ne pas lancer la phase 3 ou un déploiement public sans nouvelle instruction explicite.

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

**Limites et contrôle utilisateur restant :** les outils navigateur disponibles permettent la navigation et l'inspection du DOM, mais **pas un contrôle visuel effectif à toutes les largeurs d'écran**. La checklist des tailles **320 / 375 / 390 / 768 / 1024 / 1440 px** et des parcours est consignée dans `docs/RESPONSIVE_QA.md`. La fusion dans `main` a été effectuée le 12 octobre 2026 ; la phase 3 a été ensuite autorisée et effectuée sur `main`.

## Clôture et contrôle après fusion — 12 octobre 2026

- Fusion de `feat/roadmap-phase-2-data-ux` dans `main` par **fast-forward** sans conflit, de `69abe51` à `93aa995`. La phase 1 est incluse dans cet historique.
- Nouvelle exécution de la suite complète **sur `main`** : **38 tests, 230 assertions, aucun échec**, avec la base PostgreSQL de test isolée.
- Twig (13 fichiers), YAML (11 fichiers), conteneur Symfony, mapping Doctrine, schéma PostgreSQL et syntaxe de `client-mobile-accordion.js` : contrôles réussis.
- Les développements ultérieurs présents sur `feat/client-link-on-detail` ont été préservés, mais **ne font pas partie** de cette fusion. Ils devront suivre leur propre revue et intégration.
- Aucun `push` effectué : aucun dépôt distant n'était configuré au moment du contrôle.
- **Recette complémentaire non automatisée** : rendu réel à 320/375/390/768/1024/1440 px, rotation mobile/tablette et ouverture de l'export dans Excel. Elle demeure dans `docs/RESPONSIVE_QA.md` ; les contrôles automatiques ne prouvent pas l'absence totale de défauts visuels.
- À la date de cette première fusion, les phases 3 à 5 n'étaient pas commencées. Les 7 commits de `feat/client-link-on-detail` ont ensuite été fusionnés dans `main` au commit `6c8d6c8`, puis la phase 3 a commencé.

## Phase 3 — Bilan technique et portes de validation (12 octobre 2026)

**Travail effectué directement sur `main` (sans branche) :**

- `64a400f` — suppression de la promotion systématique à `ROLE_ADMIN`, hiérarchie `ROLE_ADMIN / ROLE_OPERATOR / ROLE_AUDITOR`, contrôles d'accès sur les actions sensibles et le CSV, en-têtes HTTP anti-cache/anti-fuite et audit des téléchargements PDF.
- `5cc22a3` — sauvegarde conjointe PostgreSQL + fichiers de preuve, chiffrement authentifié avec libsodium, commande de vérification, restauration isolée et tests de cryptographie/validation d'archive.
- `906febf` — création contrôlée d'utilisateurs à rôle explicite, inventaire des données sans suppression, restrictions d'interface, tests CSRF/expiration/IP et documentation RGPD opérationnelle.

**Tests effectués après les modifications :**

- PHPUnit intégration + unitaires, base PostgreSQL de recette isolée : **59 tests, 405 assertions, zéro échec**.
- **5 tests Python** de validation des archives, noms de bases, empreintes et chemins.
- Twig : **14 templates** valides ; YAML : **12 fichiers** valides ; conteneur Symfony et schéma Doctrine synchronisés.
- Simulation réelle `pg_dump -Fc` / `pg_restore` sur deux bases PostgreSQL **fictives**, ligne de justificatif retrouvée ; les bases et le dump de test ont été supprimés.
- Une tentative du `selftest` de sauvegarde chiffrée end-to-end a été bloquée par le pont Hermes (accès de Docker Compose lancé par Python à `.env` refusé). **Ne pas annoncer l'outil de bout en bout comme validé** tant qu'il n'a pas été exécuté depuis un terminal de maintenance autorisé.
- Aucune migration ni purge de données clients existants ; aucun SMTP réel ni déploiement extérieur.

**Documents de référence :** `docs/GOUVERNANCE_SECURITE.md`, `docs/SAUVEGARDES.md`, et le présent journal.

**Arbitrages bloquants avant mise en production :** texte et notice RGPD approuvés, responsable de traitement et point de contact établis, durées de conservation motivées, affectation des rôles validée, stockage de sauvegarde externe et secret sécurisé choisis, restauration chiffrée testée intégralement en environnement autorisé. Aucune de ces décisions n'est imposée par le code.

**Statut :** implémentation technique livrée sur `main`, **validation juridique et opérationnelle incomplète**. Arrêt après phase 3 dans l'attente de l'avis utilisateur. Les phases 4 et 5 ne sont pas démarrées.

## Complément phase 3 — Option A (13 octobre 2026)

- Décision retenue : archive nominative jusqu'à +1 an après la fin contractuelle saisie, puis **destruction de toutes les données individuelles** et maintien provisoire de **statistiques agrégées uniquement** jusqu'à +3 ans.
- Nouvelle migration `Version20261013090000` : `retention_aggregate`, `evidence_purge_queue` et regroupement des événements de l'historique par type et mois, sans fiche client ni horodatage individuel.
- Commande `app:retention:process` : **simulation seule par défaut**, activation impossible sans variable serveur et deux confirmations ; aucune planification destructive configurée.
- Suppressions SQL atomiques des clients, décisions, invitations, journaux et métadonnées PDF ; effacement physique des preuves après commit, avec file de reprise en cas d'échec.
- Compteurs réservés à `ROLE_ADMIN` : seuil de confidentialité provisoire de 5, effectifs 1 à 4 non conservés, granularité mensuelle ; historique général visible à tous les profils habilités sans identifiants.
- Gestion des cas anciens (+3 ans déjà dépassés) sans relancer artificiellement une période de conservation statistique.
- Tests PostgreSQL fictifs incluant prévention d'une purge avant échéance, rejet des chemins PDF non autorisés, file de reprise, seuil d'agrégation, effacement après échéance et rôles.
- La migration a été appliquée sur la base de développement après sauvegarde interne. **Aucune donnée client existante n'a été purgée**.

**Points à approuver avant activation** : fondement juridique des durées de conservation des preuves, seuil de cinq clients, échéance mensuelle des statistiques (écart maximal d'environ un mois), rotation des sauvegardes contenant encore des données personnelles, procédure de supervision des suppressions physiques et restauration chiffrée.

**Complément du 12 octobre 2026 :** fin de contrat saisie manuellement, archive nominative 1 an, archive anonymisée réservée aux administrateurs 2 ans, puis suppression : décisions métier enregistrées dans `docs/CYCLE_VIE_CLIENTS.md`. La clôture manuelle et l'historique général sont implémentés et testés sur `main`. L'option A (statistiques agrégées sans dossier pseudonymisé) a été retenue le 13 octobre 2026. Le traitement de suppression irréversible et les statistiques sont maintenant **implémentés mais désactivés par défaut**, soumis à confirmations explicites, validation juridique et test d'exploitation. Aucun cron destructif n'est activé. Voir `docs/CYCLE_VIE_CLIENTS.md`.
