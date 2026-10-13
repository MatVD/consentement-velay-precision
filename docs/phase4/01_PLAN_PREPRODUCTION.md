# Phase 4 — Plan d'implémentation et de préproduction

Date de cadrage : 13 octobre 2026
Statut : **plan documenté uniquement**. Aucune infrastructure déployée ou modifiée.
Base technique inspectée : Symfony 7.4, PHP 8.5, FrankenPHP/Caddy, PostgreSQL 18, Docker Compose, Symfony Mailer.
Références croisées : 00_DECISIONS_INFRASTRUCTURE.md, 02_RECETTE_GO_NO_GO.md, ../SAUVEGARDES.md, ../CYCLE_VIE_CLIENTS.md.

## Principes de travail

- Toutes les futures évolutions de code seront réalisées sur **main**, conformément au choix utilisateur. Vérifier git status et les changements d'autres agents avant chaque étape. Un commit cohérent par sous-ensemble ; **pas de push** sans demande.
- La présente demande autorise la conception du plan, **pas** l'achat d'un VPS, la modification DNS, la création d'identifiants Microsoft, le déploiement, une migration ou l'envoi de vrais emails.
- Deux environnements **physiquement et logiquement séparés** recommandés ; aucune copie de données personnelles réelles dans l'environnement de recette.
- Chaque sous-phase peut se terminer en autonomie, avec tests et compte rendu. Arrêter avant chaque opération externe qui demande une décision ou des accès.
- Les composants métier actuels sont conservés : déclarations Oui/Non, archivage, téléchargements PDF et habilitations.

## Plan par sous-phases

| Étape | Objet | Dépendance / autorisation | Livrable et commit ciblé | Validation |
| --- | --- | --- | --- | --- |
| 4.0 | Figer les décisions d'architecture, budget, responsables et propriété des comptes | Direction, responsable DNS, admin Microsoft 365 | Registre 00 ; choix signés | Porte A |
| 4.1 | Préparer le code « production-ready » sans accès Internet public | D4-07 à D4-13, configuration validée | Composer, Docker/Compose, secrets, logs, health, README | Tests locaux |
| 4.2 | Provisionner préproduction isolée | Accord explicite de création du VPS, DNS de préproduction | VPS dédié, HTTPS, volumes, base et fixtures | Contrôle réseau et TLS |
| 4.3 | Intégrer Microsoft Graph et tester le mail contrôlé | Application Entra, boîte, RBAC et destinataires tests approuvés | Bridge Symfony Mailer, transport, erreurs, traçabilité | Envoi/rejet contrôlés |
| 4.4 | Activer les sauvegardes externes, vérification et monitoring | Stockage, secrets et politiques approuvés | Routine chiffrée, tests de restauration, alertes | Reprise documentée |
| 4.5 | Effectuer recette de préproduction et corriger | Environnement stabilisé, fixtures et comptes tests | Procès-verbal de recette + anomalies | Porte de recette |
| 4.6 | Préparer le **plan** de production sans l'exécuter | Porte C complète | Checklist de bascule, sauvegarde, rollback, communications | GO / NO-GO écrit |
| 4.7 | Déploiement public éventuel (hors de la présente autorisation) | **Ordre explicite de Stéphane / demande utilisateur** | Mise en service indépendante et traçable | Validation finale |

### 4.0 — Cadrage (en cours)

- [ ] Acter le fournisseur, la localisation des données, le niveau de support et le budget TTC/HT total des deux VPS + stockage/monitoring.
- [ ] Confirmer la zone DNS gérée pour velay-precision.fr et le responsable autorisé.
- [ ] Confirmer si l'entreprise a un tenant Microsoft 365/Exchange Online administrable, une boîte dédiée et un interlocuteur autorisé à restreindre Graph Mail.Send.
- [ ] Nommer un responsable des sauvegardes et de la réponse aux incidents.
- [ ] Approuver l'hébergement UE, les sous-traitants et les termes de confidentialité/RGPD.

**Sortie :** décisions signées, pas d'achats implicites.

### 4.1 — Durcissement logiciel local (développement sur main uniquement après accord)

**État code constaté :**
- Dockerfile : image de base dunglas/frankenphp:1-php8.5-trixie ; dépendances gérées par composer.lock.
- Compose : postgres:18 et build local ; volumes persistants pg_data, evidence_data, caddy_data, caddy_config ; PostgreSQL n'expose pas de port vers l'hôte.
- compose.prod.yaml ouvre 80/tcp, 443/tcp et 443/udp ; Caddyfile utilise SERVER_NAME et le certificat automatique.
- config/packages/prod/framework.yaml force le cookie de session Secure.
- Symfony Mailer utilise MAILER_DSN, MAIL_FROM et PUBLIC_BASE_URL. Le README décrit encore SMTP classique : **à actualiser pour Microsoft Graph**, pas à appliquer tel quel.
- Pas de contrôles de santé HTTP applicatifs dédiés, de pin d'image par digest, ni de supervision externalisée constatés dans les fichiers inspectés.

**Travaux prévus :**
- [ ] Geler et documenter les images **par version immuable / digest**, plan de mises à jour Symfony, PHP, PostgreSQL, Docker et système.
- [ ] Ajouter contrôle de configuration production *fail-closed* : APP_ENV=prod, APP_DEBUG=0, APP_SECRET sécurisé, DATABASE_URL isolée, PUBLIC_BASE_URL exact, SERVER_NAME exact, MAIL_FROM cohérent, MAILER_DSN non nul avant l'envoi, secrets hors du dépôt.
- [ ] Préparer deux configurations Compose sans réutiliser réseau, volumes, secrets ou certificat entre préprod et prod.
- [ ] Ajouter un endpoint de santé minimal (sans données clients et sans divulgation de la base) et readiness interne DB ; limiter sa divulgation publique.
- [ ] Vérifier en-têtes HTTP, TLS, cookies, CSRF, throttling, et absence de fuites d'URL /c/<jeton> dans les logs de Caddy, Docker et outils de supervision. Définir la rétention des logs.
- [ ] Tester une installation depuis zéro, migrations dans l'ordre et chemins de restauration, sans perdre ni exposer de PDF.
- [ ] Auditer les autorisations de fichiers, le compte des conteneurs, l'accès au socket Docker et la limitation des ressources.
- [ ] Contrôler les dépendances vulnérables et secrets accidentellement versionnés ; ne jamais afficher ou committer un vrai token.

**Vérifications :** tests PHPUnit/intégration sur la base de test isolée, Twig/YAML, schéma/migrations Doctrine, Docker Compose config --quiet, parcours HTTP simulés, tests de sécurité.

### 4.2 — Infrastructure de préproduction

Une fois le choix d'hébergement approuvé :
- [ ] Créer VPS de préproduction au nom de la société, Linux LTS patché, SSH par clé, firewall, droits nominaux et horloge NTP.
- [ ] Installer Docker / Compose avec versions documentées et accès admin restreint.
- [ ] Configurer exclusivement la préproduction : base et volume PDF ne contenant que des fixtures synthétiques, secrets distincts, SMTP désactivé par défaut.
- [ ] Prévoir preprod-consentement.velay-precision.fr **après** validation DNS et installer TLS via Caddy. Accès protégé par VPN/identity-aware proxy ou ACL hors des scénarios contrôlés.
- [ ] Vérifier l'accès nécessaire aux testeurs sans contourner la protection de /admin ni exposer les liens de recette à des clients.
- [ ] Vérifier journaux, certificat, DNS, IPv4/IPv6, ports, sauvegarde et mesures de ressources.

**Vérifications :** hostname, certificat valide, redirection HTTP→HTTPS, inaccessible depuis des réseaux non autorisés, services et DB sains, rollback de test.

### 4.3 — Microsoft Graph / Exchange Online

- [ ] Valider le compte expéditeur, le Reply-To et l'administrateur Microsoft 365.
- [ ] Ajouter le bridge symfony/microsoft-graph-mailer **avec sa version compatible Symfony 7.4**, conserver Symfony Mailer comme abstraction.
- [ ] Paramétrer un MAILER_DSN de type microsoftgraph+api (tenant et application distincts par environnement). Secrets injectés à l'exécution, jamais dans .env.example ni README.
- [ ] Approuver la permission d'envoi minimale et restreindre réellement l'application à la boîte prévue via les règles Exchange Online applicables.
- [ ] Tester le refus d'envoi depuis une deuxième boîte, en plus du succès depuis la boîte autorisée.
- [ ] Garder une allowlist de destinataires en préproduction ; pas de campagne ni d'envoi vers des clients.
- [ ] Vérifier authentification SPF/DKIM/DMARC du domaine expéditeur sans modifier brutalement les enregistrements existants.
- [ ] Distinguer accepté par Graph (HTTP 202), réellement distribué par Exchange, erreur de transport, bounce ; documenter traitement des échecs et limites de débit.

**Vérifications :** tests unitaires avec transport mock, tests fonctionnels contrôlés, trace d'un message dans Exchange et vérification SPF/DKIM/DMARC sur message reçu.

### 4.4 — Sauvegarde, reprise et supervision

- [ ] Choisir un stockage externe à la machine (par exemple Object Storage compatible S3, contrat et localisation approuvés), accès limité et chiffrement activé.
- [ ] Exécuter **python3 scripts/backup.py selftest** depuis un terminal de maintenance autorisé avec des données fictives. Cette étape avait échoué via les restrictions locales Hermes et **reste à démontrer**.
- [ ] Tester sauvegarde et restauration isolée de PostgreSQL + volume PDF, vérification des empreintes, journal sans secrets, et procédures de rotation des clés.
- [ ] Déterminer RPO/RTO avec Stéphane et mesurer effectivement la restauration ; ne pas confondre snapshot VPS et sauvegarde externalisée.
- [ ] Contrôler que les fichiers supprimés selon l'option A de conservation ne sont pas réintroduits sans décision via une ancienne sauvegarde ; valider les délais de rotation.
- [ ] Ajouter alertes : indisponibilité HTTPS, erreur DB, échec d'envoi Graph, espace disque, certificat, absence de sauvegarde valide, file evidence_purge_queue non vide, absence de supervision.
- [ ] Documenter la procédure d'incident, la personne responsable, les accès « bris de glace » et les canaux d'alerte.

**Vérifications :** restauration répétable sur un environnement jetable, preuves d'alertes reçues, document de temps de reprise mesuré.

### 4.5 — Recette de préproduction

- [ ] Parcours complet invitation email → clic public → décision Oui/Non → PDF → historique.
- [ ] Parcours sur site : formulaire assisté, liens individuels et droits technicien.
- [ ] Refus ⇒ indication de la procédure papier, sans signature électronique fictive.
- [ ] Archives : clôture manuelle, historique anonymisé, simulation de purge, aucun effacement réel sans autorisation.
- [ ] Tests de sécurité : lien inconnu/expiré/consommé, CSRF, permissions, brute force, rate limiting, téléchargement d'un justificatif altéré.
- [ ] Responsive réel mobile/tablette/desktop (320, 375, 390, 768, 1024, 1440 px), clic/clavier, contraste, formulaire et panneau « … ».
- [ ] Échanges email vers boîtes de test uniquement, authentification SPF/DKIM/DMARC et délivrabilité.
- [ ] Montée en charge légère, consommation disque PDF et récupération après arrêt contrôlé.
- [ ] Vérifier respect des politiques RGPD validées, notices de traitement et statut des tâches de purge.

**Sortie :** tableau de recette 02, captures non sensibles, rapports de tests, correctifs et version Git identifiée.

### 4.6 — Plan de bascule (GO / NO-GO)

Définir, **sans exécuter** :
1. Fenêtre de maintenance et responsable présent.
2. Version de déploiement immuable, sauvegarde préalable et confirmation de l'intégrité des données.
3. Ordre d'opérations : provisionnement, variables/secrets, restauration/initialisation DB vide, migrations, smoke tests et contrôles TLS/Graph.
4. Bascule DNS (uniquement avec approbation de l'administrateur de zone).
5. Monitoring intensif au lancement ; communications internes.
6. Stratégie de retour arrière : rollback de l'image uniquement si la migration DB est compatible ; autrement restauration isolée/contrôlée, sans écrire sur les données réelles tant qu'une reprise n'est pas validée.
7. Accord GO écrit distinct de l'autorisation de préproduction.

**Interdits sans nouveau feu vert :** achat, création de comptes administrateurs réels, modifications DNS, secrets Microsoft, migration sur serveur de production, ouverture d'un domaine public, envoi de vrais clients, activation de la purge irréversible.

## Méthode Git / livrables

Chaque future étape est un petit ensemble de commits sur main (contrats de configuration, mail Graph, sauvegardes, monitoring, tests). Ne pas créer de branche selon l'instruction utilisateur. Si un autre agent travaille sur le même dossier, vérifier l'état Git avant chaque commit. Aucune opération de push automatique.

Le plan n'est pas un déploiement ; il sert à séparer la prise de décision et le code à relire.

## Sources techniques vérifiées au 13 octobre 2026

- Symfony Mailer / bridge Graph : https://symfony.com/doc/current/mailer.html
- Exchange Online RBAC for Applications : https://learn.microsoft.com/en-us/exchange/permissions-exo/application-rbac
- Microsoft Graph sendMail (202 ≠ distribué) : https://learn.microsoft.com/en-us/graph/api/user-sendmail?view=graph-rest-1.0
- Microsoft 365 DKIM : https://learn.microsoft.com/en-us/defender-office-365/email-authentication-dkim-configure
- Microsoft 365 DMARC : https://learn.microsoft.com/en-us/defender-office-365/email-authentication-dmarc-configure
- Caddy Automatic HTTPS : https://caddyserver.com/docs/automatic-https
