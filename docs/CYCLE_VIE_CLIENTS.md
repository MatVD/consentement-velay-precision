# Cycle de vie des clients — décisions métier du 12 octobre 2026

**Projet :** Velay Consentement — **mise en œuvre prudente sur `main`**.

## Décisions communiquées

- Si le client refuse la signature électronique, Velay Précision lui **propose un document papier avec signature manuscrite**. Ce refus ne doit pas être converti en « absence de réponse ».
- La **date de fin de relation contractuelle** est enregistrée manuellement.
- De la fin du contrat à **+1 an** : dossier placé en **archive normale**, avec le choix initial et les justificatifs conservés.
- De **+1 an à +3 ans** : **archive anonymisée**, réservée aux utilisateurs du rôle `ROLE_ADMIN`.
- À **+3 ans** : suppression des données restantes dans l'archive.
- Les utilisateurs qui disposent d'un accès à l'application doivent pouvoir consulter un **historique global** mentionnant les événements « Client archivé », « Client anonymisé » et « Client supprimé ».

**Ces durées sont des décisions métier proposées par l'entreprise ; elles ne remplacent pas la vérification des durées probatoires, comptables ou des obligations de conservation éventuellement applicables.** Si une obligation plus longue concerne certaines pièces, celle-ci doit être arbitrée **avant** toute suppression/anonymisation.

## Règle de calcul

La date de référence est **la date de fin contractuelle saisie**, pas la date technique de l'intervention en administration.

| Moment | État cible |
| --- | --- |
| Jusqu'à la clôture | Dossier actif |
| Date de fin contractuelle | Dossier archivé (conservation nominative) |
| Fin + 1 année calendaire | Fin de l'archive nominative ; anonymisation envisagée |
| Fin + 3 années calendaires | Suppression finale des seules données restantes |

Exemple : **fin du contrat le 12/10/2026** → archive nominative jusqu'au 12/10/2027 → archive anonymisée jusqu'au 12/10/2029 → suppression.

Une date de fin contractuelle rétrospective est permise lors de la saisie ; les échéances suivent la date saisie. Les transitions futures ne sont **pas** automatiquement activées dans cette livraison.

## Ce qui est implémenté aujourd'hui

- Formulaire de consentement **versionné** `2026-10-12-v2` : il précise l'alternative papier en cas de refus. Les décisions déjà enregistrées conservent l'ancien texte versionné.
- Un administrateur peut ouvrir la **fiche client**, saisir une date de fin et demander « Clôturer et archiver » ; confirmation et jeton CSRF sont requis. La clôture est enregistrée transactionnellement, sans toucher au choix Oui/Non ou au PDF.
- La liste Clients distingue les dossiers **Actifs** et **Archivés**, avec filtre.
- Aucun nouvel email, lien individuel, formulaire public ou recueil accompagné ne peut enregistrer de décision pour un client archivé ; les liens antérieurs sont désormais inopérants.
- Le menu **Historique des archives**, consultable par `ROLE_AUDITOR`, `ROLE_OPERATOR` et `ROLE_ADMIN`, présente un **journal général** limité aux libellés et dates (au jour près), **sans nom, email, identifiant de client ni référence Codial**.
- Une trace nominative `client_archived` existe également **dans la fiche individuelle** tant que l'archive normale est conservée. Elle devra être détruite lors de l'anonymisation effective.
- Les libellés « Client anonymisé » et « Client supprimé » sont **prévus par le journal global**, mais aucun événement de ce type n'est écrit tant que ces actions ne sont pas réellement réalisées.

**Aucune anonymisation, purge des PDFs, suppression de client ni tâche automatique destructive n'a été programmée.** Ces étapes attendent la décision ci-dessous et la validation de conformité.

## Arbitrage indispensable : contenu des deux années « anonymisées »

Une suppression des seuls noms et emails qui laisserait :
- une fiche par client avec identifiant stable ;
- ses choix horodatés, sa référence Codial ou sa fonction ;
- les PDF d'origine, adresses IP, user-agents ou leurs empreintes ;
- les historiques et journaux contenant son identifiant ;

ne constitue **pas nécessairement une anonymisation irréversible**. Elle pourrait demeurer une **pseudonymisation**, régie par le RGPD. La CNIL recommande de considérer aussi les possibilités d'individualisation et de recoupement.

**Deux solutions à arbitrer :**

1. **Statistiques agrégées non identifiantes** (recommandation pour une anonymisation effective). À +1 an, détruire toutes les données permettant d'identifier ou de relier le dossier à une personne, y compris le PDF et les événements individuels ; conserver pendant deux ans **uniquement des totaux agrégés**, sans fiche unique reconstituable, accessibles aux administrateurs. Après deux ans, supprimer ces agrégats selon une granularité calendaire définie. Le journal général des transitions reste sans lien client.
2. **Dossiers individuels pseudonymisés**. Conserver une fiche et certaines informations par dossier pendant deux ans, mais la qualifier correctement de **pseudonymisée**, l'isoler et l'exposer exclusivement aux administrateurs. La nécessité, la base légale et le risque de réidentification doivent être documentés. Supprimer intégralement les données à +3 ans, y compris les clés de rapprochement, PDF et journaux concernés.

Le choix dépend de l'utilité réelle de l'archive après un an : **l'entreprise veut-elle encore rechercher une décision client individuelle ou seulement connaître des volumes/statistiques ?**

## Historique visible par tous et suppression

Après une suppression véritable, **la fiche client n'existe plus**. Il n'est donc pas possible de conserver simultanément un historique *dans la fiche supprimée*. Le journal général proposé conserve uniquement le type d'événement et sa date (sans nom, identité, Codial, numéro de dossier ou lien à la personne).

Les événements nominativement reliés au client (`audit_event`, `consent_request`, `consent_decision`), la fiche client et les fichiers PDF doivent être purgés de manière cohérente, et la politique de purge doit couvrir les sauvegardes selon leur durée de rotation.

L'historique général n'est **pas** une preuve individuelle d'identité ou de suppression d'un client donné : il sert à suivre les transitions de manière non identifiante. Pour attester l'exécution d'une purge donnée auprès du responsable interne, prévoir un mécanisme distinct protégé, à durée limitée et juridiquement fondée.

## Autorisations restant à confirmer

- La **clôture manuelle** est provisoirement réservée aux administrateurs ; les techniciens ont des droits opérationnels mais la politique de clôture n'a pas encore été explicitement attribuée aux techniciens.
- L'accès à l'archive **nominative** suit provisoirement les règles de consultation existantes ; il doit être confirmé au regard du principe de séparation des archives intermédiaires.
- L'archive du deuxième cycle sera **réservée aux administrateurs** quelle que soit la solution retenue.

## Références CNIL

- [Durées de conservation — CNIL, avril 2026](https://www.cnil.fr/fr/passer-laction/les-durees-de-conservation-des-donnees)
- [Anonymisation et pseudonymisation — CNIL](https://www.cnil.fr/fr/identifier-les-donnees-personnelles)
- [Concilier durées de conservation et archives — CNIL](https://www.cnil.fr/fr/comment-concilier-les-durees-de-conservation-et-les-archives)
