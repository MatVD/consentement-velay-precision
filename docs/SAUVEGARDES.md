# Sauvegarde chiffrée et restauration isolée — Velay Consentement

**Phase 3 — outillage disponible, mise en exploitation conditionnée au choix d'un stockage externe et d'un secret.**

## Ce qui est protégé

- `pg_dump --format=custom` : schéma et données PostgreSQL (`velay`).
- Archive `tar` privée du volume `evidence_data` : justificatifs PDF.
- Manifeste versionné contenant les tailles et empreintes SHA-256 de ces deux ensembles.
- Le tout chiffré et **authentifié** avec libsodium (XChaCha20-Poly1305 `secretstream`) ; clé dérivée du secret par **Argon2id**. Un fichier `.vbackup` représente les deux jeux de données.

Le manifeste atteste les données dans l'archive ; il ne rend pas un PDF probant au sens juridique. Toute clé/secret perdus rendent le fichier irrécupérable.

## Préparation de l'exploitant (à valider avant l'usage réel)

1. Définir une destination sauvegarde hors dépôt et hors disque serveur, à accès limité. Dossier existant `chmod 700` ; destination `*.vbackup` non accessible par HTTP et sauvegarde de la clé **séparée** des archives.
2. Définir une phrase secrète aléatoire suffisamment longue (minimum technique 24 octets, 32+ recommandés), stockée dans un gestionnaire de secrets accessible au restaurateur désigné. **Ne jamais la placer dans `.env`, le dépôt Git, l'historique shell ou dans une ligne de commande.**
3. Définir la fréquence, les durées de rétention et l'externalisation (non automatisées). Restaurer régulièrement une copie en environnement isolé. Faire valider les durées et le traitement des copies supprimées au titre du RGPD.
4. **Suspendre les écritures** pendant l'instantané PostgreSQL + PDF (fenêtre de maintenance / accès au formulaire temporairement bloqué). Les volumes ne sont pas gelés atomiquement par Docker Compose.

L'outil utilise Python 3.10+ sur le poste de maintenance et le PHP/libsodium du conteneur `app` ; il appelle Docker Compose depuis le dossier racine du projet.

## Commandes (exemples à adapter, aucune destination n'est configurée par défaut)

Charger le secret depuis un fichier restreint **extérieur au dépôt**, sans l'inscrire en clair dans les commandes enregistrées :

```bash
export VELAY_BACKUP_SECRET="$(cat /chemin/prive/cle-velay-backup)"
```

Créer une archive chiffrée, lors d'une fenêtre de maintenance où aucun formulaire ne peut être envoyé :

```bash
python3 scripts/backup.py create --database velay --output-dir /chemin/securise/externe --confirm-maintenance
```

Contrôler le déchiffrement et les empreintes **sans écrire en base** :

```bash
python3 scripts/backup.py verify /chemin/securise/externe/velay-AAAAMMJJTHHMMSSZ-xxxx.vbackup
```

Tester la restauration **uniquement sur une installation isolée et autorisée**. Pour une archive de données réelles, `--confirm-sensitive-data` est exigé pour rappeler la duplication temporaire de données sensibles. La commande crée une base éphémère au nom aléatoire `velay_phase3_restore_...`, restaure PostgreSQL, compare les PDF de l'archive aux empreintes en base et détruit **uniquement la base qu'elle vient de créer**. Ne jamais exécuter sur le cluster de production avec de vraies données, mais sur un clone/test avec des accès limités :

```bash
python3 scripts/backup.py restore-test /chemin/securise/externe/velay-AAAAMMJJTHHMMSSZ-xxxx.vbackup --confirm-sensitive-data
```

Un test entièrement **fictif**, sans copie de client réel, est prévu :

```bash
python3 scripts/backup.py selftest
```

Il crée une base de recette aléatoire, un seul PDF fictif, la sauvegarde chiffrée, une vérification et une restauration éphémère. Il détruit ses ressources de test après contrôle.

**Précaution :** la restauration est une opération de maintenance ; chaque intervention doit être journalisée et la procédure de démarrage après restauration doit aussi être testée en préproduction. L'archive sauvegarde contient des données personnelles.

## États réellement vérifiés à ce jour

- Tests unitaires libsodium : aller-retour binaire, altération, troncature, mauvaise clé et secret trop court.
- Tests Python : manifeste, SHA-256, chemins sûrs, noms de bases autorisés et destination hors dépôt.
- PostgreSQL 18 : `pg_dump` custom et `pg_restore` dans **deux bases strictement fictives**, avec restitution de la table de justificatifs, puis suppression contrôlée des deux bases et du fichier temporaire.
- Le **test intégral `selftest` n'a pas pu être exécuté via le pont local Hermes** : sa sandbox refuse à un processus Docker Compose lancé depuis Python la lecture de `.env`. Les appels Docker directs et les tests PHP sont passés. Il reste donc à effectuer `selftest` depuis un terminal opérateur autorisé, puis une restauration de préproduction, avant de qualifier la procédure de pleinement validée.
- Aucun planificateur, stockage hors site, secret d'exploitation ni test sur des données réelles n'a été configuré.

## Limites explicites

- **Pas de sauvegarde cohérente pendant des écritures simultanées** : ne pas supprimer `--confirm-maintenance` pour la production.
- La présence de fichiers PDF orphelins n'est pas actuellement bloquante ; vérifier périodiquement les deux sens de correspondance base / volume.
- Sauvegarde de la base et du volume **uniquement** : la version de l'application, `composer.lock`, les migrations et la configuration non secrète sont à conserver par Git ; les secrets serveur doivent être gérés séparément.
- Si la restauration échoue, ne jamais tenter de remplacer directement la base `velay`. Isoler, diagnostiquer et recommencer dans un environnement de test.
