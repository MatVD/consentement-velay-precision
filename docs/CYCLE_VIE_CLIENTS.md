# Velay Consentement — cycle de conservation, option A

**Choix métier confirmé le 13 octobre 2026 : option A — statistiques agrégées, sans fiche pseudonymisée.**

## 1. Règles retenues

- En cas de refus de la signature électronique, Velay Précision propose un **document papier à signer manuscritement**.
- La fin de la relation contractuelle est saisie **manuellement** par un administrateur.
- De la fin du contrat à **+1 an** : dossier conservé en **archive normale**, avec les décisions et PDF.
- À **+1 an** : suppression irréversible de la fiche client, des identifiants, contacts, décisions nominatives, invitations, audits individuels et PDF. Aucun lien client ne survit.
- De **+1 à +3 ans** : uniquement des **totaux agrégés**, accessibles à `ROLE_ADMIN` ; aucun dossier individuel anonymisé ou pseudonymisé.
- À **+3 ans** : suppression des agrégats devenus obsolètes.
- Historique général, visible aux trois rôles, avec « Client archivé », « Client anonymisé » et « Client supprimé », **groupé par type d'événement et mois**, jamais relié à un dossier.

**Attention :** les durées et la nature des justificatifs doivent être **validées par le responsable de traitement et, si besoin, son conseil juridique**. L'article 1367 du Code civil et les règles de preuve peuvent rendre prématurée la destruction de certains justificatifs. Le code reste désactivé pour les transitions irréversibles jusqu'à l'accord requis.

## 2. Statistiques réellement agrégées

La table `retention_aggregate` ne comporte que :

| Champ | Utilité |
| --- | --- |
| `period` | Mois de fin contractuelle, au format AAAA-MM |
| `client_total` | Total d'un groupe de **5 dossiers ou plus**, sinon **NULL** |
| `expires_on` | Date d'expiration de la cohorte |

Aucune référence client, nom, email, Codial, décision Oui/Non, IP, PDF, identifiant d'audit ou date individuelle n'y est copiée.

Pour les groupes de 1 à 4 clients, **aucun effectif exact n'est conservé**. Les comptages sont volontairement incomplets : c'est un compromis de confidentialité, non une anomalie. Le seuil **5** est une mesure conservatrice provisoire à valider ; ce seuil seul **ne prouve pas** qu'une anonymisation est irréversible, surtout dans une petite entreprise où des connaissances externes peuvent permettre des recoupements.

La cohorte est expirée au **premier jour du mois suivant celui de la fin contractuelle, augmenté de trois ans**. Le regroupement par mois peut donc ajouter **jusqu'à un mois** à la durée exacte de trois ans pour les seuls agrégats, jamais pour la fiche nominative. Ce choix de granularité doit être approuvé avant activation. Les dossiers clôturés depuis plus de trois ans lors de la première exécution sont supprimés sans création d'une nouvelle cohorte.

## 3. Historique général

La table `client_lifecycle_event` conserve **au plus un événement par type et mois**. Son enregistrement ne contient aucune identité, aucun compteur individuel ni horodatage précis.

L'espace **Historique des archives** est consultable par tous les utilisateurs authentifiés ayant les rôles administrateur, opérateur ou auditeur. L'écran **Statistiques anonymisées**, contenant les agrégats mensuels, est strictement réservé à `ROLE_ADMIN`, y compris côté serveur.

Il n'est pas possible de conserver simultanément un historique **individualisé** d'un client réellement anonymisé/supprimé et de garantir l'absence de rapprochement avec son identité : l'historique fourni est donc **global et non nominatif**, conformément à l'option A.

## 4. Fonctionnement technique et sécurité

Le traitement `app:retention:process` :

1. Liste les dossiers éligibles à la fin de la première année, d'après **la date manuelle de fin contractuelle**, pas la date d'archivage ;
2. En exécution autorisée, verrouille et supprime transactionnellement les données individuelles, en respectant l'ordre des clés étrangères ;
3. Enregistre uniquement les cohortes mensuelles non identifiantes et le type de transition dans l'historique général ;
4. Place les noms de PDF aléatoires dans `evidence_purge_queue` avant le commit SQL, puis efface les fichiers du volume privé **après** commit ;
5. Supprime les anciennes cohortes arrivées à leur échéance, en créant l'événement générique « Client supprimé » ;
6. Réessaie les suppressions de PDF échouées. Si des PDF restent présents, la commande retourne une erreur : **une alerte opérationnelle est nécessaire**.

Tous les changements SQL d'une exécution sont groupés dans une transaction. En cas d'erreur, les fiches et leurs relations ne sont pas effacées partiellement. Une panne disque après commit ne peut pas être annulée par PostgreSQL : la file `evidence_purge_queue` garde les opérations non terminées, à surveiller sans délai.

**Aucune tâche automatique destructive n'a été branchée au Scheduler ou à cron** et aucune purge n'a été appliquée aux données de la base `velay`.

## 5. Commandes

Par défaut, **simulation en lecture seule**, utilisable dès maintenant :

```bash
docker compose -f compose.yaml -f compose.dev.yaml exec -T app php bin/console app:retention:process
```

La commande affiche seulement le nombre de dossiers dus, de cohortes expirées et de PDF en attente, sans identité client.

**La commande destructrice doit être autorisée explicitement à trois niveaux** : variable d'environnement serveur `VELAY_RETENTION_PURGE_ENABLED=1`, option `--apply`, et confirmations `--confirm-irreversible` et `--confirm-legal-approval`. Exemple **documentaire uniquement — ne pas exécuter sur les données existantes avant validation** :

```bash
php bin/console app:retention:process --apply --confirm-irreversible --confirm-legal-approval --limit=100
```

Le secret/la variable serveur ne sont **pas définis** par défaut. Ne pas déployer de planification automatique ni de reprise en tâche de fond sans procédure approuvée, sauvegarde vérifiée et supervision de la file de PDF.

## 6. Recette et obligations restantes

**Vérifications automatisées dans la base isolée `velay_phase1_test` :** suppression des relations SQL et du PDF fictif, seuil de 5, absence d'effectif détaillé en dessous de 5, absence de purge avant échéance, groupes périmés, expiration des cohortes, historique limité au mois, refus de l'exécution sans autorisation, prévisualisation non destructive, échec et reprise potentielle de la suppression physique d'un fichier.

**À valider avant toute activation réelle :**
- Règles de preuve et de conservation applicables aux documents concernés ;
- Acceptation de la suppression des preuves nominatives à un an et de l'impossibilité ultérieure de retrouver un justificatif individuel ;
- Granularité mensuelle, possible décalage maximal d'un mois pour les seuls agrégats, et seuil provisoire de 5 ;
- Rotation des **sauvegardes contenant encore des données nominatives** : un dossier effacé de la base peut rester dans un ancien backup ; définir leur durée, chiffrement et destruction ;
- Supervision quotidienne des erreurs et files de fichiers à purger ;
- Test de restauration et de continuité sur une installation isolée.

### Références

- [CNIL — les durées de conservation des données](https://www.cnil.fr/fr/passer-laction/les-durees-de-conservation-des-donnees)
- [CNIL — identifier les données personnelles (anonymisation et pseudonymisation)](https://www.cnil.fr/fr/identifier-les-donnees-personnelles)
- [CNIL — avis sur les techniques d'anonymisation](https://www.cnil.fr/fr/le-g29-publie-un-avis-sur-les-techniques-danonymisation)
