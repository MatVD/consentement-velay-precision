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

## Audit correctif — retour utilisateur du 11 octobre 2026

### Défaut avéré : libellés superposés dans les fiches Clients

La capture sur petit écran montre les libellés « Email d'invitation », « Statut », « Date » et « Actions rapides » superposés aux contenus.

**Origine vérifiée :** le CSS responsive fourni avec **EasyAdmin 5** applique `position: absolute` et `inline-size: 35%` aux `td::before` et `padding-inline-start: 35%` aux cellules de ses datagrids. Notre propre présentation de la liste Clients en fiches omettait de neutraliser ces styles.

**Correctif :** dans `public/assets/admin.css`, sous 768 px et **uniquement sur `.ea-index-Client`**, cellules et pseudo-libellés reviennent dans le flux normal (position statique, largeur automatique, retrait gauche nul). Les libellés sont placés **au-dessus** des valeurs ; les séparateurs et hauteurs des cartes sont simplifiés.

Les actions **Email**, **Présentiel** et **Accompagner** disposent maintenant de vrais libellés HTML, masqués sur desktop et visibles sous 768 px, plutôt que de texte généré avec `::after`. Les zones tactiles principales restent d'au moins 44 px.

### Passe complémentaire sur les autres écrans

| Zone | Risque examiné et adaptation | Vérification automatique |
| --- | --- | --- |
| Clients / filtres / tri | Cartes, boutons, libellés, menu d'actions ; le filtre s'ouvre aussi dans Brave | HTML, CSS, tests d'actions |
| Création / modification client | Champs et groupes « Informations du client » / « Représentant », textes longs | Viewport + CSS + formulaire |
| Saisie assistée | Champs prénom/nom, radios, confirmation, boutons de validation | Route et formulaire |
| Suivi / preuves | Libellés des tableaux pouvant se resserrer et liens PDF longs ; présentation verticale adaptée sous 768 px | Tables et liens par tests HTML |
| Import CSV | Exemple long dans une zone défilante, bouton et rapport des rejets | Route et CSS de défilement |
| En-tête de l'admin | Titre long sur écran étroit ; rétrécissement et ellipsis, police réduite sous 400 px | Structure et règles CSS |
| Accueil / connexion / merci / lien invalide | Mise en page mobile-first indépendante du datagrid | Viewport, CSS public et codes HTTP |
| Formulaire public de consentement | Grille d'identité et groupe Oui/Non | Test avec invitation fictive en base isolée |

**Automatisé :** tests PHPUnit du CSS et des parcours ; lint Twig/YAML, conteneur Symfony et schéma Doctrine. La suite reste non destructive pour les données client de l'application : les scénarios persistants sont exécutés dans la base de test `velay_phase1_test`.

**Limite de vérification :** l'outil Brave disponible dans cette session permet de lire le DOM et d'utiliser les contrôles, mais pas de redimensionner la fenêtre pour calculer les collisions exactes à chaque largeur. Aucun succès visuel pixel-par-pixel n'est revendiqué sur les tailles suivantes.

### Contrôle visuel à faire après actualisation sans cache

- [ ] **320 px** : en-tête tronqué proprement ; « Ajouter un client » visible ; chaque ligne Clients = carte à libellés au-dessus des valeurs, trois actions accessibles.
- [ ] **375 et 390 px** : ni libellé superposé ni défilement horizontal global ; actions rapides sur une ou deux lignes sans chevauchement.
- [ ] **768 px** : bascule vers le tableau habituel, sans cellules décalées ; filtres et tri utilisables.
- [ ] **1024 et 1440 px** : tableau desktop inchangé ; icônes rapides compactes, infobulles accessibles.
- [ ] **Création / édition** : nom commercial et champs du représentant sans débordement ; validation visible.
- [ ] **Saisie assistée** : boutons et textes lisibles, y compris en paysage.
- [ ] **Suivi / preuves** : tableaux en fiches sur mobile, liens « Télécharger le PDF » lisibles.
- [ ] **Import CSV** : l'exemple défile dans sa propre zone, sans élargir toute la page.
- [ ] **Formulaires publics** : champs sans zoom iOS forcé, choix Oui/Non non tronqués.
- [ ] **Rotation / zoom à 200 %** : pas d'éléments obligatoires masqués.

Sur Brave/Chrome, actualiser sans cache (par exemple Cmd + Maj + R sur macOS) avant de reprendre une capture : `assets/admin.css` conserve la même URL.
