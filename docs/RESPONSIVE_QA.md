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
docker compose exec -T app php vendor/bin/simple-phpunit
docker compose exec -T app php bin/console lint:twig templates
docker compose exec -T app php bin/console lint:yaml config
docker compose exec -T app php bin/console lint:container
```

Les tests fonctionnels `ResponsivePagesTest` vérifient que l’accueil, la connexion et la confirmation sont accessibles et disposent de la balise viewport ainsi que de leur feuille de style.

## Contrôles visuels à reproduire dans le navigateur

Utiliser l’émulation responsive des outils de développement de Brave/Chrome ou de vrais appareils. Tester au minimum **320, 375, 390, 768, 1024 et 1440 px** de largeur ; vérifier également l’orientation paysage à l’aide d’un appareil ou d’un émulateur.

| Écran | Vérification |
| --- | --- |
| Accueil, connexion, confirmation et lien invalide | Pas de débordement horizontal ; typographie, marges et bouton lisibles |
| Formulaire de consentement | Champs empilés sur mobile, en deux colonnes à partir de 640 px ; choix Oui/Non facilement sélectionnables |
| Liste Clients (`/admin/client`) | Fiches sur mobile ; statut, email et actions rapides accessibles ; filtres et menu de navigation fonctionnels |
| Nouveau client et modification | Champs entièrement visibles, boutons de validation atteignables |
| Formulaire assisté | Identité préremplie lisible ; boutons et case de confirmation sélectionnables sans zoom forcé |
| Suivi / preuves | Décisions et événements affichés sans tableau tronqué sur mobile |
| Import CSV (`/admin/import`) | Exemple de CSV défilable horizontalement dans sa zone, bouton d’import accessible |
| Lien présentiel généré | URL et bouton « Ouvrir » ne dépassent pas la largeur de l’écran |

**Important** : ne pas enregistrer de décision réelle pour tester l’affichage ; utiliser un client de test. Les tests unitaires et Twig ne remplacent pas le contrôle visuel sur un navigateur mobile réel.
