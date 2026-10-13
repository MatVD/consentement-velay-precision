# Velay Consentement — sécurité et gouvernance des données (phase 3)

**État au 12 octobre 2026 : socle technique implémenté ; arbitrages RGPD et juridiques à valider par Velay Précision avant la production.**

Ce document n'est ni une consultation juridique ni une attestation de conformité RGPD ou eIDAS. Le formulaire recueille une **déclaration de choix concernant le recours futur à la signature électronique** ; il ne réalise aucune signature électronique qualifiée et ne vérifie pas indépendamment l'identité du répondant.

## 1. Périmètre de données

| Support | Informations collectées | Usage métier | Point d'attention |
| --- | --- | --- | --- |
| `client` | Nom/raison sociale, email, référence Codial, représentant facultatif, statut et dates | Identifier le client, préparer son invitation et suivre son choix | Éviter les coordonnées non nécessaires et ne pas confondre entreprise et personne |
| `consent_request` | Empreinte SHA-256 du jeton, canal, création, expiration, date d'envoi/utilisation | Limiter et tracer l'accès au formulaire | Le **jeton en clair n'est pas stocké** en base ; il figure dans le lien transmis et peut apparaître dans les journaux d'accès d'un reverse proxy |
| `consent_decision` | Identité déclarée, fonction, Oui/Non, version + texte exact, horodatage UTC, IP, User-Agent limité à 500 caractères, empreinte de la décision | Constituer la trace déclarative et conserver le texte présenté | Données de personnes physiques ; la collecte IP/UA et leur durée de conservation doivent être justifiées |
| `evidence_file` et volume privé | PDF de récépissé, chemin interne aléatoire, MIME et SHA-256 | Télécharger un justificatif associé à la décision | Empreinte seule ≠ preuve inviolable ni preuve d'identité |
| `audit_event` | Actions métier, état des invitations, opérateur de saisie assistée, accès aux justificatifs (ID de l'utilisateur + ID du PDF) | Traçabilité et détection d'accès sensibles | Journal dépendant de la base ; pas de scellement externe actuellement |
| `app_user` et session Symfony | Email professionnel, mot de passe haché, rôles, session | Accès aux tâches et justificatifs | Un compte nominatif par collaborateur ; révocation rapide lors des départs |

## 2. Rôles applicatifs implémentés

Tous les comptes déjà créés avec `ROLE_ADMIN` conservent leur statut ; **aucun compte n'est promu automatiquement** à `ROLE_ADMIN` à partir d'un rôle inférieur. Les droits sont cumulés selon la hiérarchie `ADMIN > OPERATOR > AUDITOR`.

| Opération | `ROLE_AUDITOR` | `ROLE_OPERATOR` | `ROLE_ADMIN` |
| --- | :---: | :---: | :---: |
| Connexion / consultation liste et fiches clients | Oui | Oui | Oui |
| Consulter et télécharger les justificatifs PDF (accès audité) | Oui | Oui | Oui |
| Créer/modifier une fiche client | Non | Oui | Oui |
| Envoyer des invitations / générer un lien individuel | Non | Oui | Oui |
| Saisie assistée et confirmation de la décision | Non | Oui | Oui |
| Import CSV et export global CSV | Non | Non | Oui |
| Gestion des comptes par commande serveur | — | — | Personnel habilité disposant de l'accès serveur |

Créer un collaborateur *via console protégée seulement*, avec mot de passe saisi de façon masquée :

```bash
docker compose -f compose.yaml -f compose.dev.yaml exec app php bin/console app:create-user collaborateur@example.fr --role=operator
```

Autres valeurs admises : `admin` et `auditor`. La décision de qui reçoit quel rôle, la procédure de retrait des accès et la gestion d'un éventuel compte d'urgence restent à valider avec le responsable de Velay Précision. Pour les administrateurs historiques, `app:create-admin` demeure compatible. Aucune interface publique ne permet de s'attribuer des rôles.

## 3. Protection des accès et du trafic

- Accès `/admin` protégé par authentification, avec limitation des tentatives de login (5 / 15 minutes) et jetons CSRF pour les opérations d'écriture.
- Le formulaire public utilise un lien individuel de **256 bits** de hasard, enregistré seulement sous empreinte SHA-256 ; expiration 14 jours (email) ou 24 h (lien présentiel), usage unique et verrouillage transactionnel.
- Réponse HTTP `Referrer-Policy: no-referrer` pour éviter de transmettre le jeton d'URL à un autre site ; `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Permissions-Policy`.
- `Cache-Control: private, no-store` sur login, administration, pages `/c/...` (y compris expiration/erreurs), confirmation et réponses associées.
- En `APP_ENV=prod`, cookie de session `Secure` ; utiliser **exclusivement HTTPS**, puis vérifier le proxy et le protocole vus par Symfony. Ne jamais définir aveuglément `TRUSTED_PROXIES=0.0.0.0/0`.
- Les routes de téléchargement refusent une preuve absente ou dont l'empreinte diverge, puis enregistrent un événement `evidence_downloaded` lié au client et à l'utilisateur autorisé.
- Les anciennes décisions ne sont jamais écrasées par un second formulaire.
- Le formulaire assisté utilise un brouillon de session limité à 30 minutes, avec confirmation explicite **réservée au technicien**.

**Limites explicites :** pas de 2FA intégrée, pas de système de révocation centralisé des sessions, pas de signature/scellement externe des journaux, pas de Content-Security-Policy stricte validée pour EasyAdmin (qui contient du JavaScript et des styles inline), pas de politique de supervision encore déployée. Ces décisions sont à compléter avant la production.

## 3 bis. Cycle de vie des dossiers (décisions du 12 octobre 2026)

L'entreprise indique manuellement la fin contractuelle. Pendant **1 an** à compter de cette date, le dossier reste en archive normale, avec les données et justificatifs historiques. L'option A a été retenue le 13 octobre 2026 : uniquement des **compteurs mensuels agrégés accessibles aux administrateurs** jusqu'à +3 ans (sous réserve de la validation des durées), puis suppression de ces statistiques. La signature papier sera proposée aux clients ayant refusé le recours à la signature électronique.

**Mise en œuvre actuelle :** la clôture, la conservation nominative d'une première archive, la restriction de la création de nouveaux liens et un historique général non nominatif sont implémentés. **Le code de suppression et d'agrégation est implémenté mais désactivé par défaut**, protégé par une variable d'environnement et des confirmations. Les événements « Client anonymisé / Client supprimé » sont enregistrés **uniquement après traitement réel**.

Le journal général ne contient ni nom, ni email, ni référence Codial, ni ID client, et ne conserve qu'un événement par mois et type. La trace individuelle « Client archivé » est supprimée lors de la purge effective. La possibilité de réidentification par recoupement reste à évaluer.

**Décision juridique à confirmer :** les délais de conservation de la preuve et leur justification, ainsi que le caractère réellement irréversible de l'anonymisation. Le seuil provisoire de cinq dossiers par compteur, le risque de réidentification des petites cohortes, la granularité mensuelle et la destruction des sauvegardes restent à faire approuver. Voir `docs/CYCLE_VIE_CLIENTS.md`.

## 4. Gouvernance RGPD — décisions non prises par le code

**À arbitrer et faire valider formellement :**

1. Identifier le **responsable de traitement** (entité Velay Précision), ses coordonnées exactes, l'éventuel délégué/point de contact et les sous-traitants (hébergeur, SMTP, prestataire de sauvegarde).
2. Décrire les **finalités**, déterminer et documenter la **base légale RGPD de la conservation des données** et les modalités de preuve attendues. Ne pas confondre cette base légale avec le choix Oui/Non relatif à la signature électronique.
3. Valider le texte de recueil, la **notice d'information** à afficher (finalités, base légale, destinataires, droits, durées, sécurité, transferts hors UE le cas échéant), ainsi que l'identité et la capacité du représentant répondant.
4. Définir des **durées distinctes et motivées** : clients en attente, invitations inutilisées/expirées, décisions, récépissés PDF, logs techniques, brouillons de session, comptes inactifs, sauvegardes.
5. Décrire la gestion des **droits des personnes**, des corrections et des demandes de suppression en tenant compte d'éventuelles obligations de conservation de preuve. Une modification de consentement nécessitera un historique non destructif (V2).
6. Prévoir un registre des traitements, la gestion des violations de données et le plan de communication/notification des incidents selon les obligations applicables.
7. Faire valider les mesures d'accès et la politique de conservation par le responsable métier et, si nécessaire, son conseil juridique / DPO.

**Aucune durée n'est inventée ni appliquée automatiquement. Aucune purge destructrice n'est introduite tant que ces points ne sont pas approuvés.**

Inventaire non destructif pour aider à la décision :

```bash
docker compose -f compose.yaml -f compose.dev.yaml exec -T app php bin/console app:privacy:inventory --older-than-days=365
```

La commande affiche uniquement des **comptages**, sans nom, email, lien ou identifiant de client, et sans modifier la base. Le seuil 365 jours est un simple exemple de rapport, **pas** une durée de conservation approuvée.

## 5. Sauvegardes / résilience

Voir `docs/SAUVEGARDES.md`. Une copie exploitable doit comprendre **PostgreSQL et le volume privé des PDF**, chiffrée avant transfert vers une destination séparée de la machine hébergeant l'application. La restauration doit être répétée sur un environnement isolé. Une preuve non liée à une ligne de la base n'est pas une restauration métier complète.

## 6. Bloqueurs avant mise en service

- Notice RGPD, durée de conservation, base légale et texte métier validés par les responsables.
- HTTPS et configuration de proxy vérifiés de bout en bout ; test de délivrabilité du vrai SMTP.
- Sauvegarde chiffrée **externalisée**, clé sécurisée et procédure restaurable contrôlée par l'exploitant.
- Mise en place d'une politique de rotation des journaux et d'alertes, puis recette sur environnement de préproduction.
- Vérification utilisateur des écrans mobiles réels selon `docs/RESPONSIVE_QA.md`.

Ce sont des conditions de préparation à la production et non un prétexte pour déclarer un système automatiquement « conforme ».
