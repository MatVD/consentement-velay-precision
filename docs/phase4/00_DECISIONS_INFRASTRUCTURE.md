# Phase 4 — Registre des décisions d'infrastructure

Date : 13 octobre 2026
Statut : planification, sans déploiement.
Projet : Velay Consentement — Symfony 7.4 / PHP 8.5 / FrankenPHP-Caddy / PostgreSQL 18.
Décisionnaire métier : Stéphane JOURDAIN (direction de Velay Précision).
Règle de travail : commits ciblés directement sur main ; pas de push ni de déploiement sans demande.

## 1. Décisions confirmées

| Référence | Sujet | Décision |
| --- | --- | --- |
| D4-01 | Modèle d'hébergement | Hébergeur externe, pas de serveur interne à l'entreprise |
| D4-02 | URL publique finale | https://consentement.velay-precision.fr |
| D4-03 | Messagerie professionnelle | Microsoft 365 / Outlook ; les invitations sont émises au nom de Velay Précision |
| D4-04 | Technos de base | Préserver Symfony 7.4, PHP 8.5, PostgreSQL 18, Docker Compose et FrankenPHP/Caddy |
| D4-05 | Processus | Préproduction d'abord, production seulement après recette et accord explicite |
| D4-06 | Conformité | Pas d'envoi réel ni de purge de données avant validation des obligations métier/RGPD |

Ce document est un registre de choix. Un élément proposé ci-dessous n'est **pas** une autorisation d'achat ou de mise en ligne.

## 2. Architecture recommandée — à faire approuver

| Réf. | Proposition technique | État / décideur |
| --- | --- | --- |
| D4-07 | OVHcloud, VPS en datacenter français. Alternative : prestataire UE offrant VPS, DPA, sauvegarde exportable et support adaptés | PROPOSÉ — Stéphane valide le fournisseur, le budget et le titulaire du contrat |
| D4-08 | **Deux environnements isolés** : VPS production dédié et VPS préproduction séparé. Pas de volume PostgreSQL ni de secret partagé | PROPOSÉ — accord budgétaire |
| D4-09 | Production : point de départ 4 vCPU / 8 Go RAM / disque SSD 80 Go minimum à ajuster selon volumétrie PDF. Préproduction : 2 vCPU / 4 Go et 40 Go au départ | HYPOTHÈSE de capacité — benchmark et chiffrage |
| D4-10 | Linux LTS maintenu, Docker Engine/Compose, firewall entrant 80/443, SSH réservé aux administrateurs (clé, IP/VPN si possible). PostgreSQL jamais exposé publiquement | RECOMMANDÉ — responsable technique |
| D4-11 | **Caddy/FrankenPHP seul** en terminaison TLS dans un premier temps : pas de Nginx frontal supplémentaire sans besoin démontré | RECOMMANDÉ — technique |
| D4-12 | Hostname de préproduction proposé : preprod-consentement.velay-precision.fr, certificat HTTPS et contrôle d'accès complémentaire (VPN/SSO/allowlist), sans données réelles | PROPOSÉ — responsable DNS |
| D4-13 | La production utilise ses volumes persistants distincts : base, PDF et données Caddy/certificats, avec chiffrage et sauvegarde hors serveur | RECOMMANDÉ — exploitant |
| D4-14 | Envoi depuis une boîte dédiée **proposée** : consentement@velay-precision.fr ; adresse de réponse : contact@velay-precision.fr, sous réserve de disponibilité des boîtes et des droits | À CONFIRMER — administrateur Microsoft 365 |
| D4-15 | **Microsoft Graph via Symfony Mailer** et application Entra dédiée, plutôt que SMTP avec utilisateur/mot de passe | RECOMMANDÉ — administrateur Microsoft 365 |
| D4-16 | Permission d'envoi strictement limitée à la seule boîte dédiée via **Exchange Online RBAC for Applications** (ou contrôle compatible vérifié), test de refus sur toute autre boîte | CONDITION BLOQUANTE de sécurité — administrateur Microsoft 365 |
| D4-17 | Préproduction : emails simulés et/ou uniquement vers adresses de tests internes autorisées ; identifiants d'application Entra distincts si un envoi réel est testé | RECOMMANDÉ — équipe technique |
| D4-18 | Sauvegarde quotidienne chiffrée, base PostgreSQL + justificatifs PDF, stockage **externe au VPS** et distinct des volumes de production | CONDITION BLOQUANTE — exploitant |
| D4-19 | RPO cible ≤ 24 h, RTO cible ≤ 8 h ; test de restauration isolée et journal d'exécution de sauvegarde | OBJECTIFS PROPOSÉS — Stéphane / exploitant |
| D4-20 | Administration sécurisée : comptes nominatifs, restriction /admin (accès conditionnel ou VPN si compatible techniciens nomades), MFA à examiner avant ouverture | À ARBITRER — direction / technique |
| D4-21 | Responsable exploitation désigné, chaîne d'alerte, astreinte éventuelle et procédure incidents / renouvellement des secrets | À ARBITRER — direction |
| D4-22 | Validation RGPD, délai de conservation des justificatifs et purge option A **avant** données clients réelles ; purge destructive inactive jusque-là | CONDITION BLOQUANTE — direction / conseil juridique |

### Pourquoi cette proposition ?

- Les charges et le nombre de clients ne justifient pas, à ce stade, Kubernetes ni une architecture distribuée.
- Deux VPS créent une réelle séparation opérationnelle et permettent de répéter migrations/restaurations sans manipuler de données clients.
- Un VPS est en général **non administré** : la gestion OS, Docker, PostgreSQL, sauvegardes et incidents reste à la charge de Velay Précision ou de son prestataire. Vérifier ce service dans le contrat.
- Les tailles indiquées servent à demander des devis. Elles **ne valent pas dimensionnement validé** sans tests de génération PDF, volume de données et trafic.

## 3. DNS et certificat

### Domaines prévus

| Usage | Nom proposé | État |
| --- | --- | --- |
| Production | consentement.velay-precision.fr | Nom confirmé ; **pas de pointage décidé/appliqué** |
| Préproduction | preprod-consentement.velay-precision.fr | Nom proposé, à approuver |
| Email d'envoi | consentement@velay-precision.fr | Adresse proposée, boîte à créer ou à confirmer |

Plan DNS : créer ou modifier uniquement les enregistrements web A/AAAA (ou CNAME si adapté au fournisseur) après allocation des VPS et feu vert. Ne pas modifier les MX existants. Conserver une traçabilité des anciennes valeurs et un TTL adapté à la bascule.

Caddy gère les certificats et renouvellements TLS lorsque le DNS pointe vers l'hôte et que 80/443 sont accessibles ; ses volumes /data et /config doivent être persistants. Confirmer la chaîne de proxy si un pare-feu ou CDN est ajouté. Prévoir HTTPS, vérification du certificat, HSTS uniquement après recette, et surveillance d'expiration.

Important : le domaine **web** consentement.velay-precision.fr n'est pas nécessairement le domaine **expéditeur** velay-precision.fr. Vérifier SPF/DKIM/DMARC pour le domaine exact de la boîte émettrice ; ne jamais créer un second enregistrement SPF concurrent. Les CNAME DKIM doivent être récupérés depuis le tenant Microsoft 365, jamais inventés.

## 4. Microsoft 365 / Graph

Le code actuel utilise Symfony Mailer et l'environnement MAILER_DSN. Pour Microsoft Graph, Symfony documente le bridge symfony/microsoft-graph-mailer. L'implémentation se fera en phase 4 après validation et sans identifiant dans Git.

Actions demandées à l'administrateur Microsoft 365 :
1. Confirmer le tenant, la licence/boîte d'expédition, le droit d'envoyer avec cette identité, les adresses From/Reply-To.
2. Créer une application Microsoft Entra réservée à Velay Consentement, avec un secret ou certificat géré et une date de rotation.
3. Définir l'autorisation d'envoi minimale et surtout la portée **uniquement** de la boîte dédiée ; éviter le mélange d'une permission Mail.Send globale tenant et d'un RBAC restreint qui pourrait étendre l'accès.
4. Tester un envoi accepté depuis la bonne boîte **et l'impossibilité** d'envoyer en tant qu'un autre collaborateur.
5. Contrôler SPF, DKIM, DMARC, la délivrabilité réelle, les éléments envoyés, les rebonds et les limites de débit.
6. Créer si utile une application et une boîte de tests distinctes pour la préproduction.

Attention : Graph peut répondre **202 Accepted** sans garantir que le destinataire a reçu l'email. L'application doit distinguer « demande acceptée par Graph » de « livré au destinataire » et prévoir le suivi Exchange Message Trace/erreurs, sans marquer abusivement un message comme délivré.

La documentation du README contenant encore un exemple SMTP classique devra être actualisée lors de l'implémentation Graph, et la compatibilité du bridge Symfony avec les permissions retenues devra être testée.

## 5. Protection des données et de l'exploitation

- Application publique uniquement par HTTPS ; accès à /admin authentifié avec protections renforcées adaptées aux techniciens intervenant sur le terrain.
- PostgreSQL sur réseau Docker interne, sans port publié. Secrets distincts par environnement, non versionnés, rotation organisée ; accès SSH par clés et permissions minimales.
- Ne jamais utiliser un dump client réel en préproduction sans procédure RGPD explicite. Préférer fixtures non réidentifiantes.
- Les URL de consentement contiennent un jeton dans le chemin : **ne pas enregistrer ni exporter leurs valeurs complètes** dans les access logs, observabilité ou tickets ; imposer la redaction avant journalisation.
- Aucune sauvegarde externalisée ou politique de rétention n'est aujourd'hui en fonctionnement réel. Le script backup.py existe, mais l'auto-test complet depuis terminal opérateur et la restauration restent à valider.
- Les tâches de purge irréversible sont désactivées par défaut et restent bloquées jusqu'à décision juridique et test opérationnel.
- Les prestataires d'hébergement, stockage et mail doivent être inscrits dans l'inventaire des sous-traitants et le dispositif d'information RGPD.

## 6. Informations à fournir avant achat et configuration

| Information manquante | Qui peut fournir / approuver ? | Bloquant pour |
| --- | --- | --- |
| Hébergeur retenu, région, budget maximum mensuel et facturation société | Stéphane JOURDAIN | Commander VPS / stockage |
| Personne disposant du droit de modifier la zone DNS velay-precision.fr | Direction / prestataire actuel | DNS et HTTPS réels |
| Administrateur du tenant Microsoft 365 et capacité à créer les applications Entra | Prestataire informatique / Microsoft 365 | Envoi Graph |
| Boîte expéditrice approuvée et stratégie de Reply-To | Direction / secrétariat | Envois réels |
| Responsable technique et procédure de gestion des secrets/alertes | Direction | Exploitation |
| Objectifs RPO/RTO, volume de PDF, politique de rotation des backups | Direction / technique / RGPD | Sauvegardes |
| Validations des durées de conservation, mentions d'information et traitement des PDF | Direction / référent juridique | Usage réel des données |

## 7. Décisions requises pour passer de la planification à l'exécution

**Porte A — avant commande/infrastructure :** signer les décisions D4-07 à D4-13 et nommer les responsables DNS, Microsoft 365 et exploitation.

**Porte B — avant émission de mails réels :** approuver D4-14 à D4-17, tester l'envoi depuis la boîte autorisée et limiter les destinataires de test.

**Porte C — avant exposition de la production :** approuver D4-18 à D4-22, recette technique et fonctionnelle complète, validation du dossier RGPD, sauvegardes et retour arrière démontrés.

L'approbation de la **préparation** ne vaut pas autorisation d'achat, de modification du DNS, d'envoi à un client ou de mise en production.
