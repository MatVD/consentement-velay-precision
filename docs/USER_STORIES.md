# Velay Consentement — User stories

**Projet :** Velay Précision — recueil de l'accord relatif au recours à la signature électronique  
**Version :** V1 autonome (sans synchronisation Codial)  
**Date :** 11 octobre 2026

## 1. Objectif et périmètre

L'application permet à Velay Précision de demander à chaque client s'il accepte ou refuse le **recours à la signature électronique pour de futurs documents**, puis de conserver la décision et sa traçabilité.

**Important :** la réponse au formulaire n'est pas une signature électronique d'un devis ou d'un contrat. Chaque document devra être présenté et signé séparément. Le formulaire recueille une déclaration ; il ne vérifie pas indépendamment l'identité du répondant.

### Acteurs

- **Collaborateur / administrateur :** utilisateur authentifié qui gère les clients, les invitations, les décisions et les exports.
- **Client / représentant :** personne qui reçoit un lien individuel et répond au formulaire public, sans créer de compte.
- **Responsable :** collaborateur qui consulte les statuts, l'historique et les justificatifs.

### Parcours général

1. Ajouter ou importer un client → **À faire**.
2. Lui transmettre un lien personnel par email (14 jours) ou générer un lien présentiel (24 heures).
3. Le client renseigne son prénom, son nom, sa qualité/fonction, puis répond **Oui** ou **Non**.
4. L'application horodate et conserve la décision, l'historique et un justificatif PDF.
5. Le lien utilisé ne permet plus une seconde réponse ; le statut **Oui** ou **Non** devient définitif dans cette V1.

---

## US-01 — Ajouter un client

**En tant que** collaborateur de Velay Précision,  
**je souhaite** créer une fiche client dans l'administration,  
**afin de** pouvoir lui demander sa décision concernant la signature électronique.

### Exemple

Un commercial enregistre la « Boulangerie Martin », qui vient d'acheter une caisse ETPOS.

### Parcours utilisateur

1. Se connecter à l'espace d'administration.
2. Ouvrir **Clients**, puis **Add Client**.
3. Renseigner l'identité du client ; ajouter son email et, si disponible, sa référence Codial.
4. Enregistrer la fiche.
5. Retrouver le client dans la liste avec le statut **À faire**.

### Critères d'acceptation

- **Étant donné** un collaborateur authentifié, **quand** il crée un client avec une identité valide, **alors** la fiche est enregistrée et visible dans la liste.
- **Étant donné** une nouvelle fiche, **quand** elle vient d'être enregistrée, **alors** son statut initial est **À faire**.
- **Étant donné** un formulaire de création incomplet ou invalide, **quand** il est soumis, **alors** la saisie doit être corrigée avant enregistrement.
- La création du client **n'envoie aucun email automatiquement**.

---

## US-02 — Inviter un client par email

**En tant que** commercial ou collaborateur,  
**je souhaite** envoyer un lien personnel par email,  
**afin que** le client puisse répondre à distance et à son rythme.

### Exemple

Le responsable de la « Boulangerie Martin » souhaite répondre depuis son bureau.

### Parcours utilisateur

1. Ouvrir la fiche du client, puis **Formulaire / preuves**.
2. Cliquer sur **Envoyer le formulaire par email**.
3. L'application génère un lien individuel valable **14 jours** et envoie l'invitation.
4. Vérifier que le statut du client passe à **Mail envoyé** après envoi réussi.
5. Le client ouvre le lien, lit le texte, saisit prénom, nom et fonction, puis sélectionne **Oui** ou **Non**.
6. Le client confirme et voit la page de remerciement.
7. Le collaborateur retrouve la décision, son horodatage, l'historique et le PDF justificatif.

### Critères d'acceptation

- **Étant donné** un client sans email, **quand** le collaborateur souhaite l'inviter, **alors** l'envoi doit être empêché.
- **Étant donné** un envoi réussi, **quand** l'email part, **alors** le statut devient **Mail envoyé** et l'historique conserve les événements associés.
- **Étant donné** un lien valide non utilisé, **quand** le client valide une décision, **alors** le statut devient **Oui** ou **Non**, et le justificatif PDF est enregistré.
- **Étant donné** un lien déjà utilisé, expiré ou inconnu, **quand** il est ouvert, **alors** le formulaire n'est plus accessible.
- Un email envoyé ne signifie **pas** que le client l'a lu ni qu'il a accepté.

---

## US-03 — Recueillir une réponse chez le client

**En tant que** technicien en intervention,  
**je souhaite** générer un formulaire utilisable en présence du commerçant,  
**afin de** recueillir sa décision sans devoir lui envoyer un email.

### Exemple

Pendant l'installation d'une balance chez un boucher, le technicien propose le formulaire au responsable du magasin.

### Parcours utilisateur

1. Retrouver la fiche du client et ouvrir **Formulaire / preuves**.
2. Cliquer sur **Générer un lien présentiel**.
3. Ouvrir le lien individuel sur un appareil pouvant accéder à l'application.
4. Laisser le représentant du client prendre connaissance du texte et renseigner ses informations.
5. Le représentant choisit **Oui, j'accepte** ou **Non, je refuse**, puis confirme.
6. Le collaborateur vérifie le statut et le justificatif dans la fiche.

### Critères d'acceptation

- **Étant donné** un client sans décision définitive, **quand** un lien présentiel est généré, **alors** il est valable **24 heures**.
- La génération du lien, à elle seule, ne change pas le statut **À faire** en **Mail envoyé**.
- **Étant donné** un refus, **quand** le formulaire est validé, **alors** le statut devient **Non** et un justificatif est généré.
- **Étant donné** une décision déjà enregistrée, **quand** le lien est réutilisé ou une nouvelle invitation demandée, **alors** le parcours normal ne permet pas de remplacer la décision.

---

## US-04 — Consulter les décisions et exporter le suivi

**En tant que** responsable de Velay Précision,  
**je souhaite** consulter les statuts des clients et leurs justificatifs,  
**afin de** suivre les réponses et retrouver la trace d'une décision.

### Exemple

Le responsable souhaite identifier les clients encore en attente et retrouver la réponse d'un commerce précis.

### Parcours utilisateur

1. Ouvrir l'administration et consulter la liste **Clients**.
2. Rechercher un client ou filtrer la liste par statut.
3. Ouvrir **Formulaire / preuves** pour consulter les décisions et l'historique.
4. Télécharger le justificatif PDF associé à une décision existante.
5. Cliquer sur **Exporter le CSV** pour récupérer le suivi.

### Critères d'acceptation

- Les quatre statuts sont visibles : **À faire**, **Mail envoyé**, **Oui**, **Non**.
- La fiche de suivi présente la décision, la personne déclarée, sa fonction, la date et les événements d'historique disponibles.
- Le PDF est uniquement accessible depuis une route d'administration protégée et doit correspondre au justificatif conservé.
- L'export CSV contient les colonnes **Identité ; Date ; Statut**.
- Un collaborateur non authentifié ne peut pas accéder aux écrans d'administration, aux exports ni aux justificatifs.

---

## US-05 — Importer plusieurs clients depuis un CSV

**En tant que** responsable administratif,  
**je souhaite** importer une liste de clients,  
**afin d'**éviter de créer les fiches une par une.

### Exemple

Velay Précision dispose d'une liste de 250 commerces et souhaite les ajouter à l'application.

### Parcours utilisateur

1. Préparer un fichier **CSV UTF-8**, séparé par des points-virgules.
2. Inclure une colonne **Identité** obligatoire ; **Email** et **Référence Codial** sont facultatives.
3. Dans l'administration, ouvrir **Importer des clients** et sélectionner le fichier.
4. Lancer l'import, puis vérifier le message récapitulatif.
5. Contrôler les nouvelles fiches dans **Clients**.

### Exemple de CSV

~~~csv
Identité;Email;Référence Codial
Boulangerie Martin;contact@example.fr;COD123
Boucherie du Centre;boucherie@example.fr;COD124
Restaurant des Halles;restaurant@example.fr;COD125
~~~

### Critères d'acceptation

- **Étant donné** un CSV au bon format, **quand** il est importé, **alors** les clients valides et non déjà présents sont ajoutés au statut **À faire**.
- **Étant donné** un client existant détecté comme doublon, **quand** le fichier est importé, **alors** la fiche existante et son statut sont conservés.
- **Étant donné** un fichier invalide, trop volumineux ou dépourvu de colonne d'identité, **quand** l'import est tenté, **alors** l'utilisateur reçoit un message d'erreur.
- La V1 prévoit une limite de **1 Mo par fichier** et de **3 000 nouveaux clients par import**.
- La référence Codial est stockée comme information facultative : **aucune synchronisation automatique avec Codial** n'est active.

---

## 2. Règles métier communes

| Statut | Signification | Déclencheur |
|---|---|---|
| **À faire** | Fiche créée, aucune décision définitive | Création ou import du client |
| **Mail envoyé** | Invitation envoyée, en attente de réponse | Envoi email réussi |
| **Oui** | Accord déclaratif enregistré | Validation du choix Oui |
| **Non** | Refus déclaratif enregistré | Validation du choix Non |

- **Oui** et **Non** sont définitifs dans le parcours prévu par la V1.
- Le lien individuel est à usage unique et limité dans le temps.
- Le formulaire public n'impose ni compte ni confirmation par email.
- La décision mémorise les informations déclarées, la version du texte, l'horodatage et les éléments techniques de traçabilité prévus par l'application.
- Un justificatif PDF est archivé avec une empreinte SHA-256 pour détecter une altération du fichier.
- L'authentification est exigée pour toutes les routes d'administration.
- Le consentement recueilli ne remplace jamais la signature ultérieure d'un document.

## 3. État de la recette

**Parcours déjà vérifiés dans le navigateur (11 octobre 2026) :**

- Création d'une fiche client depuis EasyAdmin.
- Envoi d'une invitation de test via Mailpit et passage au statut **Mail envoyé**.
- Ouverture d'un lien individuel et validation du choix **Oui**.
- Génération d'un lien présentiel et validation du choix **Non**.
- Création et contrôle des PDF justificatifs et de leurs empreintes.
- Historique des décisions et impossibilité de réutiliser le lien déjà consommé.
- Accès protégé à l'administration.

**Contrôles complémentaires encore à finaliser :**

- Import d'un véritable fichier CSV de recette (fichier valide, doublons et erreurs).
- Vérification du contenu exact d'un export CSV téléchargé.
- Vérification du téléchargement et de la lecture d'un PDF depuis le navigateur.

Les clients fictifs et leurs justificatifs créés pour les scénarios Oui/Non ont été supprimés à la fin des tests.

## 4. Conditions avant une utilisation réelle

L'environnement actuel est **local**, accessible à l'adresse http://localhost:8081 ; les emails sont dirigés vers **Mailpit** (http://localhost:8025). Les clients externes ne peuvent pas utiliser directement les liens localhost.

Avant une mise en production : disposer d'une URL publique sécurisée en HTTPS, configurer un transport SMTP réel, assurer la sauvegarde et la protection des données, et valider les informations juridiques / RGPD liées au recueil et à la conservation des décisions.

---

*Document fonctionnel de référence — Velay Consentement V1.*
