# Vérification responsive — Velay Consentement

## Portée

Le site est conçu **mobile-first** sur les pages publiques (`public/assets/app.css`), avec adaptations EasyAdmin (`public/assets/admin.css`) chargées par `DashboardController`.

- **Formulaires publics** : accueil, connexion, consentement individuel, lien invalide et confirmation. Les champs et boutons utilisent toute la largeur disponible sur mobile, puis les champs prénom/nom passent sur deux colonnes à partir de 640 px.
- **Administration** : liste des clients présentée sous forme de fiches sous 768 px ; actions rapides visibles sur mobile sans dépendre des infobulles ; navigation EasyAdmin conservée.
- **Autres écrans administrateur** : ajout/modification de client, saisie présentielle assistée, import CSV, suivi et justificatifs.
- **Contenus longs** : noms clients et URL pouvant être longs, exemples CSV défilables, suivi des décisions et historique réorganisés sous forme de fiches sous 768 px.
- **Accessibilité** : zoom autorisé, contrôles au clavier avec focus visible, cibles tactiles d’au moins 44 px sur mobile pour les principales actions.

## Contrôles automatiques

Depuis le répertoire du projet :

```sh
docker compose -f compose.yaml -f compose.dev.yaml exec -T -e VELAY_RUN_DB_TESTS=1 -e APP_DEBUG=0 app php vendor/bin/simple-phpunit
docker compose -f compose.yaml -f compose.dev.yaml exec -T app php bin/console lint:twig templates
docker compose -f compose.yaml -f compose.dev.yaml exec -T app php bin/console lint:yaml config
docker compose -f compose.yaml -f compose.dev.yaml exec -T app php bin/console lint:container
```

Les tests fonctionnels `ResponsivePagesTest` vérifient que l’accueil, la connexion et la confirmation disposent de la balise viewport et du CSS. Les tests PostgreSQL isolés `AdminWorkflowTest` contrôlent les champs du représentant, les filtres, la présence des actions et les parcours administratifs. **Ces contrôles ne prouvent pas le rendu visuel à chaque largeur.** Pour activer les tests d'intégration, créer d'abord la base séparée `velay_phase1_test` comme indiqué dans `README.md`. Ne pas lancer plusieurs suites en même temps sur cette base.

## Contrôles visuels à reproduire dans le navigateur

Utiliser l’émulation responsive des outils de développement de Brave/Chrome ou de vrais appareils. Tester au minimum **320, 375, 390, 768, 1024 et 1440 px** de largeur ; vérifier également l’orientation paysage à l’aide d’un appareil ou d’un émulateur.

| Écran | Vérification |
| --- | --- |
| Accueil, connexion, confirmation et lien invalide | Pas de débordement horizontal ; typographie, marges et bouton lisibles |
| Formulaire de consentement | Champs empilés sur mobile, en deux colonnes à partir de 640 px ; choix Oui/Non facilement sélectionnables |
| Liste Clients (`/admin/client`) | Fiches sur mobile ; statut, email et actions rapides accessibles ; filtres et menu de navigation fonctionnels |
| Nouveau client et modification | Deux groupes (« Informations du client » et « Représentant (facultatif) »), champs entièrement visibles, boutons de validation atteignables |
| Formulaire assisté | Identité préremplie lisible ; boutons et case de confirmation sélectionnables sans zoom forcé |
| Suivi / preuves | Décisions et événements affichés sans tableau tronqué sur mobile |
| Import CSV (`/admin/import`) | Exemple de CSV défilable horizontalement dans sa zone, bouton d’import et rapport de rejets accessibles (y compris listes d'erreurs) |
| Lien présentiel généré | URL et bouton « Ouvrir » ne dépassent pas la largeur de l’écran |

**État 11 octobre 2026 :** inspection Brave desktop effectuée sur l'administration en français et sur la présence des styles/contrôles. Les largeurs 320/375/390/768/1024/1440 px et la rotation sur appareils réels restent à vérifier manuellement. Ne pas enregistrer de décision réelle pour tester l'affichage ; utiliser un client de test.
