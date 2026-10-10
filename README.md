# Velay Précision — Recueil du choix de signature électronique

Application autonome de recueil d'un accord **déclaratif** sur le recours à la signature électronique. Elle ne constitue **pas** une solution de signature électronique qualifiée, avancée ou certifiée.

## Fonctionnalités de cette base de projet

- Formulaire public via un **lien individuel non devinable**, sans connexion ni email de confirmation.
- Identité et qualité **déclarées** du répondant, choix **Oui / Non**, date serveur et version exacte du texte.
- Administration protégée par identifiant/mot de passe avec **EasyAdmin 5**.
- Clients, consultation des preuves, statuts **À faire / Mail envoyé / Oui / Non**.
- Envoi de l'**invitation initiale** par email depuis EasyAdmin (SMTP requis), ou génération d'un lien pour une visite technicien.
- Export **CSV UTF-8** en trois colonnes « Identité », « Date », « Statut » ; import CSV clients.
- Récépissé PDF **non certifié** archivé dans un volume Docker privé, empreinte SHA-256 et journal d'événements.
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

5. Appliquer les migrations, puis créer l'administrateur :

   ```bash
   docker compose -f compose.yaml -f compose.dev.yaml exec app php bin/console doctrine:migrations:migrate --no-interaction
   docker compose -f compose.yaml -f compose.dev.yaml exec -it app php bin/console app:create-admin admin@velay-precision.fr
   ```

   Le mot de passe administrateur sera demandé **sans affichage** dans le terminal (12 caractères minimum).

   Après le premier lancement, créer le fichier de verrouillage des dépendances **sur l'hôte** :

   ```bash
   docker compose -f compose.yaml -f compose.dev.yaml exec app composer install --no-scripts --no-interaction
   ```

   Le fichier `composer.lock` sera écrit dans le dossier monté. Conserver ce fichier dans le dépôt Git.

6. Ouvrir les adresses :

   - Application : http://localhost:8080
   - Administration : http://localhost:8080/admin
   - Emails de test (Mailpit) : http://localhost:8025

7. Dans EasyAdmin, ajouter un client, renseigner son email, cliquer sur **Formulaire / preuves**, puis envoyer l'invitation ou générer un lien présentiel.

### Import CSV

Page **Importer des clients** dans EasyAdmin. Exemples d'en-têtes acceptées (séparateur `;`, UTF-8) :

```csv
Identité;Email;Référence Codial
Boulangerie Martin;contact@example.fr;COD123
Restaurant du Centre;restaurant@example.fr;COD124
```

L'import **n'importe pas les statuts** : il ajoute seulement des clients absents (dans la limite de 3000 nouvelles lignes par opération).

### Export CSV

Menu **Exporter le CSV** : colonnes `Identité;Date;Statut`. La date correspond à la dernière transition métier, affichée en heure française. La sortie utilise un BOM UTF-8 pour Excel et protège les cellules de texte contre l'exécution de formules.

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

Exemple d'export PostgreSQL (à stocker sur support chiffré) :

```bash
mkdir -p backups
docker compose -f compose.yaml -f compose.prod.yaml exec -T database pg_dump -U velay -d velay -Fc > backups/velay.dump
```

Effectuer une copie du volume Docker `evidence_data` à l'aide d'un conteneur temporaire, en conservant les droits et l'arborescence. Tester une restauration sur un environnement isolé. Une stratégie automatisée, chiffrée et externalisée est requise en production.

## Sécurité, RGPD et valeur probante

- Il n'y a **aucune vérification forte d'identité** : l'identité, la qualité du signataire et le contrôle du lien sont déclaratifs. Un lien individuel ne garantit pas que la bonne personne l'a ouvert.
- Le PDF est un **récépissé**, non une signature cryptographique de la personne. Ne pas le présenter comme signature électronique qualifiée.
- L'application conserve une version du texte, une date UTC, des journaux, l'IP et l'agent utilisateur (données à traiter conformément au RGPD). Définir et appliquer une politique de conservation et de purge après validation juridique.
- Les fichiers PDF sont stockés hors de `/public`; leur téléchargement est limité aux comptes administrateurs et contrôlé par SHA-256.
- Compte administrateur : mot de passe haché, protection CSRF, limitation des tentatives.
- L'export CSV et les détails des réponses ne doivent jamais être accessibles anonymement.
- Sans système externe d'horodatage, ni stockage WORM, un administrateur système disposant d'un accès complet au serveur peut théoriquement altérer les données et les justificatifs. Les preuves sont **techniques et non certifiées**.
- Définir un responsable du traitement, l'information des personnes, la durée de conservation, les accès et le contrat SMTP/hébergement avant production.

## Développement et vérifications

```bash
docker compose -f compose.yaml -f compose.dev.yaml exec app php bin/console debug:router
docker compose -f compose.yaml -f compose.dev.yaml exec app php bin/console doctrine:schema:validate
docker compose -f compose.yaml -f compose.dev.yaml exec app php bin/console lint:twig templates/
docker compose -f compose.yaml -f compose.dev.yaml exec app php vendor/bin/simple-phpunit
```

## Limites de l'archive initiale

Le code source, Docker Compose, une migration, un contrôleur d'administration, l'export et le parcours client sont inclus. **Les dépendances `vendor/` et `composer.lock` ne sont pas inclus**, faute d'accès réseau Composer dans l'environnement de génération. Le premier `docker compose build` les téléchargera ; il faudra **committer le `composer.lock` généré** après la première installation pour rendre les builds reproductibles. Les tests d'intégration Docker/SMTP/PostgreSQL et le démarrage réel sont à exécuter sur votre poste avant la production.

## Références officielles

- https://frankenphp.dev/docs/docker/
- https://frankenphp.dev/docs/symfony/
- https://symfony.com/releases/7.4
- https://symfony.com/bundles/EasyAdminBundle/current/dashboards.html
- https://hub.docker.com/_/postgres
