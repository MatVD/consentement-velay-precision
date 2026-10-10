# Validation de la livraison (10 octobre 2026)

## Contrôles effectués pendant la génération

- 27 fichiers PHP : **syntaxe vérifiée** avec PHP CLI 8.4.24.
- 13 fichiers YAML : **syntaxe vérifiée** avec PyYAML.
- `composer.json` : **JSON valide**.
- Entités : Client, User, ConsentRequest, ConsentDecision, AuditEvent, EvidenceFile présentes.
- Test PHP isolé effectué pour les transitions de statut et la construction d'un hash de décision.
- Dockerfile, Compose, migrations, contrôleurs, formulaires, emails de test et templates présents.

## Contrôles impossibles ici

Cet environnement ne dispose pas du moteur Docker et n'a pas d'accès réseau à Composer. Il a donc été impossible de :

- construire l'image FrankenPHP/PHP 8.5 ;
- résoudre les dépendances Composer et générer un `composer.lock` ;
- démarrer Symfony et PostgreSQL 18 ;
- exécuter les migrations Doctrine ;
- lancer les tests Twig, PHPUnit et la recette bout en bout.

**Le projet est une base de code à démarrer et valider localement, pas une mise en production déjà certifiée.** Les commandes sont décrites dans `README.md` et la checklist dans `docs/RECETTE.md`.
