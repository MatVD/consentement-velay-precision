# Phase 4 — Recette préproduction et décision GO / NO-GO

Version initiale : 13 octobre 2026
Statut actuel : **NON EFFECTUÉ**, checklist préparée avant tout déploiement.
Aucun case cochée n'est réputée validée par le seul développement de l'application.

## Consignes

- N'utiliser que des **données fictives** sur la préproduction, sans email envoyé à un vrai client.
- Chaque ligne doit être accompagnée du résultat obtenu, de la date, de l'opérateur et d'une preuve **non sensible** (journal expurgé, test, capture fictive).
- Ne pas coller d'URL contenant un jeton, d'adresse personnelle, de certificat privé, de secret Microsoft, d'extrait de base client ou de PDF réel dans les comptes rendus.
- Tests destructifs uniquement sur l'environnement de recette, bases et fichiers de test explicitement identifiés.
- Une anomalie **critique** ou un bloqueur légal non validé entraîne NO-GO.

## A. Hébergement, DNS, TLS et isolation

- [ ] Fournisseur / région / titulaire du contrat approuvés et confirmés au nom de Velay Précision.
- [ ] Préproduction et production isolées (VPS ou isolation équivalente approuvée, stockage/base/secrets distincts).
- [ ] Accès SSH nominatif par clés, audit des droits, pare-feu, mises à jour Linux automatiques ou planifiées.
- [ ] PostgreSQL non exposé à Internet, ports publics limités, accès administrateur restreint.
- [ ] Préproduction protégée des visiteurs non autorisés ; aucune indexation de pages réelles.
- [ ] DNS préproduction validé sans modification des MX ni de la messagerie existante.
- [ ] Certificat HTTPS valide, renouvellement automatique vérifié, redirection HTTP vers HTTPS.
- [ ] En-têtes HTTP/cookies validés, y compris cohérence des en-têtes Caddy et Symfony.
- [ ] Journalisation expurgée des chemins /c/<jeton> et absence de fuite des URL de formulaire.

## B. Application et base

- [ ] Révision Git/versions/images figées et documentées.
- [ ] APP_ENV=prod, APP_DEBUG=0, configuration et secrets distincts par environnement ; aucun secret dans Git.
- [ ] Migration depuis une base de recette vide avec journal d'application, puis relecture du schéma Doctrine.
- [ ] Page d'accueil, login/logout, rôles administrateur, opérateur, auditeur testés.
- [ ] Liste Clients, création, filtres, import/export CSV fictif et fiches responsive.
- [ ] Page de consentement individuelle : jeton valide, inconnu, expiré, consommé, double soumission.
- [ ] Formulaire et saisie assistée identiques du point de vue client ; validation Oui/Non.
- [ ] Cas Refus : alternative de signature manuscrite visible et formulée clairement.
- [ ] Récépissé PDF fictif consultable uniquement par le rôle habilité, empreinte SHA-256 vérifiée.
- [ ] Clôture client, archive normale, refus des nouveaux liens et historique global.
- [ ] Simulation de purge sans effacement de données ; aucun planificateur destructif actif.
- [ ] Parcours responsive réels à 320/375/390/768/1024/1440 px avec clavier et panneau latéral « … ».
- [ ] Jeu d'essai volumétrique et performance suffisant pour les dimensions choisies.

## C. Microsoft 365 / Graph

- [ ] Boîte émettrice et Reply-To approuvés ; application Entra dédiée en place.
- [ ] Permission Graph d'envoi limitée à la boîte concernée et **test de refus** depuis une autre boîte réussi.
- [ ] Secrets/chiffrement/rotation gérés hors Git, accès limités.
- [ ] Envoi de test vers adresse interne autorisée, acceptation Graph et **réception effective** confirmées.
- [ ] Vérifications SPF, DKIM et DMARC sur message reçu.
- [ ] Simulation d'erreur Graph, indisponibilité, quota/rate limit et comportement de réessai.
- [ ] Journalisation sans contenu de message sensible ni jeton de consentement.

## D. Sauvegardes, sécurité opérationnelle et supervision

- [ ] Stockage de sauvegarde hors VPS, privé, chiffré et distinct de la production.
- [ ] Script de chiffrement exécuté intégralement depuis un terminal autorisé avec données fictives.
- [ ] Backup PostgreSQL + PDF vérifié, restauré dans un environnement isolé, fichiers/empreintes concordants.
- [ ] RPO et RTO validés par Stéphane et mesurés lors d'un exercice.
- [ ] Rotation et purge des backups compatibles avec les délais RGPD et l'option A.
- [ ] Surveillance uptime, certificat, disque, DB, erreurs PHP/Caddy, email et file de PDF à supprimer.
- [ ] Alertes de test réellement reçues et responsable identifié.
- [ ] Procédures incident, secret compromis, récupération et révocation d'accès rédigées.

## E. Validations métier et juridiques obligatoires

- [ ] Dénomination sociale du responsable de traitement, contacts RGPD et sous-traitants exacts approuvés.
- [ ] Notice d'information RGPD, finalité, base légale et version des formulaires validées.
- [ ] Conservation des décisions et pièces probatoires justifiée par les règles applicables avant purge d'un PDF.
- [ ] Conditions de l'option A validées : groupe minimal, agrégation mensuelle, incertitude de réidentification, durée des backups.
- [ ] Gestion des droits, revue d'accès, techniciens nomades et éventuellement MFA approuvés.
- [ ] Responsabilités de maintenance et budget récurrent acceptés.

## F. Décision GO / NO-GO

| Champ | Valeur à compléter |
| --- | --- |
| Version Git / digest image | À renseigner |
| Environnement de recette | À renseigner |
| Date de recette | À renseigner |
| Responsable technique | À renseigner |
| Responsable métier | Stéphane JOURDAIN (validation attendue) |
| Anomalies critiques ouvertes | À renseigner |
| Tests de restauration mesurés | À renseigner |
| Avis sur la validation RGPD | À renseigner |
| Décision | **NO-GO par défaut** |
| Date et approbateur de la production | À renseigner |

**Règle obligatoire :** aucune mise en production automatique sur simple succès des tests. Une autorisation distincte doit être demandée après avoir établi et fait signer le résultat de cette checklist.

## Plan de retour arrière à préparer lors de la recette

- Conserver l'ancienne image immuable et la version de la migration.
- Vérifier la compatibilité base de données / ancienne application avant rollback logiciel.
- Prendre une sauvegarde **avant** migration, tester la restauration sur base isolée.
- En cas de problème, suspendre les nouveaux envois et conserver les données reçues : éviter de restaurer une ancienne base par-dessus des décisions nouvelles.
- Réserver le changement DNS à une étape approuvée ; conserver TTL et plan de reprise.
- Documenter les décisions de crise, responsables, temps d'indisponibilité et communication interne.

La checklist n'autorise pas de purge réelle sur les clients déjà enregistrés.
