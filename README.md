# Velay Précision — Recueil du choix de signature électronique

Application autonome de recueil d'un accord **déclaratif** sur le recours à la signature électronique. Elle ne constitue **pas** une solution de signature électronique qualifiée, avancée ou certifiée.

## Fonctionnalités de cette base de projet

- Formulaire public via un **lien individuel non devinable**, sans connexion ni email de confirmation.
- Identité et qualité **déclarées** du répondant, choix **Oui / Non**, date serveur et version exacte du texte.
- Administration protégée par identifiant/mot de passe avec **EasyAdmin 5**, rôles différenciés `admin`, `operator` et `auditor` (accès et actions contrôlés côté serveur).
- Clients, consultation des preuves, statuts **À faire / Mail envoyé / Oui / Non**.
- **Représentant facultatif** renseigné séparément de la raison sociale ; son prénom, son nom et sa fonction préremplissent tous les formulaires (lien mail, lien présentiel, saisie accompagnée) mais restent modifiables. Aucun nom de personne n’est déduit de la raison sociale.
- Envoi de l'**invitation initiale** par email depuis EasyAdmin (SMTP requis), ou génération d'un lien pour une visite technicien.
- Export **CSV UTF-8** progressif en trois colonnes « Identité », « Date », « Statut » ; import CSV validé en lots avec bilan des rejets et doublons.
- Récépissé PDF **non certifié** archivé dans un volume Docker privé, empreinte SHA-256 et journal d'événements, dont les téléchargements sont désormais tracés par identifiant utilisateur.
- Connecteur Codial : **non implémenté**, champ facultatif `codial_id` réservé.

## Technologies

- PHP 8.5, Symfony **7.4 LTS**, Twig, Doctrine, EasyAdmin **5**.
- FrankenPHP 1.x (Caddy), **mode classique** (worker non activé).
- PostgreSQL **18** ; volume nommé `pg_data` monté sur `/var/lib/postgresql`.
- Docker Compose : service `app` (FrankenPHP/Symfony) + `database` ; Mailpit ajouté en développement.

## Prérequis

Docker Engine / Docker Desktop et le plugin `docker compose` fonctionnel ; accès internet pour construire les images et installer les paquets Composer. Aucune installation PHP locale n'est nécessaire.

### Démarrage local

1. Décompresser l'archive, ouvrir un terminal dans le dossier.
2. Copier `.env.example` vers `.env` :

   ```bash
   cp .env.example .env
   ```

3. Générer de **vraies valeurs aléatoires** et remplacer `APP_SECRET` et `DB_PASSWORD` dans `.env` :

   ```bash
   openssl rand -hex 32
   openssl rand -hex 24
   ```

   Le mot de passe PostgreSQL doit être alphanumérique/hexadécimal pour ne pas nécessiter d'encodage dans l'URL de connexion.

4. Lancer l'environnement de développement et attendre la disponibilité des services :

   ```bash
   docker compose -f compose.yaml -f compose.dev.yaml up -d --build --wait
   ```

   Les fichiers CSS et JavaScript d'EasyAdmin sont installés automatiquement au démarrage. En cas de problème d'affichage, relancer `docker compose -f compose.yaml -f compose.dev.yaml exec app php bin/console assets:install public` puis actualiser la page.

5. Appliquer les migrations, puis créer l'administrateur :

   ```bash
   docker compose -f compose.yaml -f compose.dev.yaml exec app php bin/console doctrine:migrations:migrate --no-interaction
   docker compose -f compose.yaml -f compose.dev.yaml exec -it app php bin/console app:create-admin admin@velay-precision.fr
   ```

   Le mot de passe administrateur sera demandé **sans affichage** dans le terminal (12 caractères minimum).

   Les dépendances sont figées dans `composer.lock` et les recettes Symfony dans `symfony.lock`. Ces deux fichiers sont intégrés à la construction Docker. Ne pas relancer `composer install` pour le premier démarrage : Symfony Flex peut appliquer des recettes modifiant les fichiers de configuration. Toute mise à jour des dépendances doit être vérifiée séparément dans Git.

6. Ouvrir les adresses :

   - Application : http://localhost:8081
   - Administration : http://localhost:8081/admin
   - Emails de test (Mailpit) : http://localhost:8025

7. Dans EasyAdmin (interface en français), ajouter un client : renseigner l'**entreprise / client**, l'email d'invitation et, si disponibles, le prénom, le nom et la fonction de son représentant. Depuis la liste **Clients**, utiliser les icônes **Email** ou **Accompagner**. La génération de **lien individuel** est disponible dans la **fiche client** (menu d'actions « ... »), avec copie du lien valable 24 heures. Les permissions affichées dépendent du rôle : l'auditeur peut consulter sans modifier.

### Import CSV

Page **Importer des clients** dans EasyAdmin. Fichier `.csv` **UTF-8**, séparateur `;`, taille maximale **1 Mo**, limite de **3 000 lignes de données**. Seule la colonne `Identité` ou `Entreprise` est obligatoire ; les autres colonnes sont facultatives :

```csv
Identité;Email;Référence Codial;Prénom représentant;Nom représentant;Fonction représentant
Boulangerie Martin;contact@example.fr;COD123;Anne;Martin;Gérante
Restaurant du Centre;restaurant@example.fr;COD124;;;
```

Le traitement importe les clients non présents par lots de 100, sans importer les statuts et sans modifier les fiches existantes. Les doublons sont recherchés sans distinction de casse, selon la référence Codial ou le couple (nom, email). Un bilan affiche le nombre de clients **ajoutés**, de **doublons ignorés**, de **lignes rejetées** et jusqu'à 40 erreurs détaillées. Un CSV comportant plus de 3 000 lignes de données est refusé dans son ensemble.

### Export CSV

Menu **Exporter le CSV** : colonnes `Identité;Date;Statut`. La date correspond à la dernière transition métier, affichée en heure française. Le fichier est généré progressivement, avec un BOM UTF-8 pour Excel et une protection des cellules de texte contre l'exécution de formules.

## Fonctionnement des statuts

| Statut | Déclencheur |
|---|---|
| À faire | Nouveau client |
| Mail envoyé | Transport SMTP a accepté l'envoi initial ; **ne prouve pas la réception** |
| Oui | Réponse Oui validée sur le formulaire, récépissé archivé |
| Non | Réponse Non validée sur le formulaire, récépissé archivé |

La décision est **finale pour cette V1**. Toute révision du choix nécessite un nouveau processus métier à développer ultérieurement, pour ne pas écraser les réponses précédentes.

Le lien est expiré après 14 jours s'il est envoyé par mail, ou 24 heures s'il est généré en présentiel. Le jeton brut n'est jamais stocké dans la base : seul son SHA-256 est conservé. La soumission du formulaire est protégée contre les relectures concurrentes et les doubles validations.

## Mise en production (avant de lancer les premiers vrais envois)

1. Examiner et faire valider le texte de consentement (`src/Service/ConsentPolicy.php`) et l'information RGPD.
2. Configurer une infrastructure avec DNS, ports 80/443 libres, sauvegardes et supervision. **Sur un serveur déjà occupé par Nginx sur 80/443, ne pas déployer tel quel** : prévoir une migration ou une reverse-proxy maîtrisée.
3. Dans `.env`, renseigner les paramètres :

   ```dotenv
   APP_ENV=prod
   APP_SECRET=<secret-long-aleatoire>
   DB_PASSWORD=<mot-de-passe-hexadecimal>
   SERVER_NAME=consentement.exemple.fr
   PUBLIC_BASE_URL=https://consentement.exemple.fr
   MAIL_FROM=contact@exemple.fr
   MAILER_DSN=smtp://utilisateur:motdepasse-encode@smtp.exemple.fr:587
   ```

   Adapter le domaine et les adresses à votre hébergement. Les secrets restent hors du dépôt.

4. Lancer la production :

   ```bash
   docker compose -f compose.yaml -f compose.prod.yaml up -d --build --wait
   docker compose -f compose.yaml -f compose.prod.yaml exec app php bin/console doctrine:migrations:migrate --no-interaction
   docker compose -f compose.yaml -f compose.prod.yaml exec -it app php bin/console app:create-admin admin@exemple.fr
   ```

5. Contrôler l'accès HTTPS, le login, la sécurité des liens, les PDFs, les emails, la sauvegarde et la restauration avant le moindre envoi client.

FrankenPHP/Caddy gère directement HTTPS si le DNS pointe vers ce serveur et que les ports requis sont accessibles. Ses données de certificat persistent dans les volumes `caddy_data` et `caddy_config`. Le mode worker est désactivé volontairement.

### Sauvegardes à prévoir

**La base et les PDF sont deux ensembles distincts : les sauvegarder ensemble.** Ne pas effectuer `docker compose down -v` sous peine de supprimer leurs volumes !

L'outil `scripts/backup.py` produit un ensemble **PostgreSQL + justificatifs**, chiffré avec libsodium ; il permet la vérification cryptographique et un test de restauration dans une base éphémère. La destination externalisée, les clés et la planification d'exploitation **ne sont pas encore configurées**. Voir **`docs/SAUVEGARDES.md`** avant toute utilisation. Ne jamais créer de sauvegarde en clair dans le dépôt.

## Sécurité, RGPD et valeur probante

- Il n'y a **aucune vérification forte d'identité** : l'identité, la qualité du signataire et le contrôle du lien sont déclaratifs. Un lien individuel ne garantit pas que la bonne personne l'a ouvert.
- Le PDF est un **récépissé**, non une signature cryptographique de la personne. Ne pas le présenter comme signature électronique qualifiée.
- L'application conserve une version du texte, une date UTC, des journaux, l'IP et l'agent utilisateur (données à traiter conformément au RGPD). Définir et appliquer une politique de conservation et de purge après validation juridique.
- Les fichiers PDF sont stockés hors de `/public` ; leur téléchargement est réservé aux rôles autorisés, contrôlé par SHA-256 et **journalisé**.
- Comptes nominatifs : `ROLE_ADMIN` (toutes les opérations), `ROLE_OPERATOR` (actions client sans import/export global), `ROLE_AUDITOR` (consultation), mot de passe haché, CSRF et limitation des tentatives.
- Les pages sensibles envoient `Cache-Control: private, no-store` et `Referrer-Policy: no-referrer`, afin de limiter notamment les risques de diffusion des liens d'invitation. Le cookie de session est `Secure` en environnement `prod`.
- L'export CSV et les détails des réponses ne doivent jamais être accessibles anonymement.
- Sans système externe d'horodatage, ni stockage WORM, un administrateur système disposant d'un accès complet au serveur peut théoriquement altérer les données et les justificatifs. Les preuves sont **techniques et non certifiées**.
- Définir un responsable du traitement, l'information des personnes, la durée de conservation, les accès et le contrat SMTP/hébergement avant production. **Ces validations restent à obtenir**. Les données ne sont pas purgées automatiquement ; la commande de lecture seule `app:privacy:inventory` fournit des comptages.
- Voir **`docs/GOUVERNANCE_SECURITE.md`** pour la cartographie des données, les droits d'accès, les décisions RGPD en attente et les limites probatoires.

## Développement et vérifications

```bash
docker compose -f compose.yaml -f compose.dev.yaml exec app php bin/console debug:router
docker compose -f compose.yaml -f compose.dev.yaml exec app php bin/console doctrine:schema:validate
docker compose -f compose.yaml -f compose.dev.yaml exec app php bin/console lint:twig templates/
docker compose -f compose.yaml -f compose.dev.yaml exec app php vendor/bin/simple-phpunit
```

## État du dépôt et tests d'intégration

`composer.lock` et `symfony.lock` sont versionnés. Le dossier `vendor/` est volontairement exclu de Git ; les dépendances sont installées dans l'image Docker. Les tests d'intégration sont disponibles, mais ils ne doivent **jamais** être dirigés vers la base de développement : ils utilisent exclusivement la base isolée `velay_phase1_test`, créée une seule fois avec :

```bash
docker compose -f compose.yaml -f compose.dev.yaml exec -T database createdb -U velay velay_phase1_test
```

Si cette base existe déjà, ne pas relancer la commande. Lancer ensuite la suite complète :

```bash
docker compose -f compose.yaml -f compose.dev.yaml exec -T -e VELAY_RUN_DB_TESTS=1 -e APP_DEBUG=0 app php vendor/bin/simple-phpunit
```

**Attention :** ces tests effacent/recréent les tables de **cette base isolée** ; ne jamais exécuter la suite en parallèle avec plusieurs agents partageant `velay_phase1_test`. Ils ne modifient ni les clients, ni les décisions, ni les justificatifs réels de la base `velay`. Un contrôle visuel sur appareils mobiles et une recette SMTP/HTTPS réels restent nécessaires avant production.

## Références officielles

- https://frankenphp.dev/docs/docker/
- https://frankenphp.dev/docs/symfony/
- https://symfony.com/releases/7.4
- https://symfony.com/bundles/EasyAdminBundle/current/dashboards.html
- https://hub.docker.com/_/postgres
