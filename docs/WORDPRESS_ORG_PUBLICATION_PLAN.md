# Plan de publication WordPress.org — ZipQuantum Smart Links 1.0.0

Dernière vérification : 2 septembre 2026

## État actuel

- [x] Compte propriétaire WordPress.org `xaere` créé et profil configuré.
- [x] Identité Xaere et logo XR configurés sur WordPress.org/Gravatar.
- [x] Plugin GPLv2-or-later initialisé sous le slug `zipquantum-smart-links`.
- [x] `readme.txt`, disclosure du service externe, Terms et Privacy préparés.
- [x] Icônes, bannières et captures WordPress.org préparées.
- [x] ZIP de production propre généré : `dist/zipquantum-smart-links.zip`.
- [x] ZIP final : 37 207 octets, SHA-256 `AFC16CD203B64D0685FED38CEC72453DCDC0150533D30CD215EEB9B226BB6281`.
- [x] PHPUnit plugin : 2 tests, 6 assertions.
- [x] WPCS/PHPCS : 14 fichiers validés.
- [x] Matrice CI préparée pour PHP 7.4, 8.0, 8.2 et 8.3.
- [x] Suite Laravel complète : 244 tests, 1 470 assertions, avec une dépréciation non bloquante.
- [x] Tests OAuth/intégration WordPress : 5 scénarios, 96 assertions.
- [x] API d'intégration déployée en production avec sauvegarde et rollback.
- [x] Parcours réel validé : plugin → inscription Free → consentement OAuth → PKCE/handoff → connexion.
- [x] Création réelle d'un Smart Link managed et de son QR depuis WordPress 7.1.
- [x] Compte, tokens, installation, lien et contenu temporaires supprimés après le test.
- [x] ZIP 1.0.0 soumis à WordPress.org le 2 septembre 2026 depuis le compte propriétaire `xaere`.
- [x] Scan automatisé WordPress.org : `Pass`.
- [x] Slug définitif demandé et attribué avant revue : `zipquantum-smart-links`.
- [x] Statut WordPress.org : `Awaiting Review`.

## Bloqueurs avant soumission

### 1. API WordPress

- [x] Sauvegarder la base et les migrations avant déploiement.
- [x] Déployer les migrations `integration_*`.
- [x] Déployer le client OAuth public WordPress.
- [x] Déployer le callback central `/integrations/wordpress/callback`.
- [x] Déployer le handoff, PKCE, rotation et révocation des tokens.
- [x] Déployer les endpoints :
  - `GET /api/v1/integration/context`
  - `POST /api/v1/integration-links/sync`
  - `GET /api/v1/integration-links`
- [x] Activer `integrations_access` pour Free, Starter et Pro.
- [x] Vérifier que MCP et les clés API historiques restent compatibles.
- [ ] Reproduire le déploiement sur un environnement staging séparé si celui-ci devient disponible.

### 2. Valider le parcours réel sur staging

- [x] Connexion OAuth depuis un vrai site WordPress local sous WordPress 7.1.
- [x] Création d'un compte Free depuis la fenêtre ouverte par le plugin.
- [x] Retour automatique au consentement après l'inscription.
- [x] Vérification du callback central et du polling sans `code_verifier`.
- [x] Création d'un Smart Link `managed`.
- [ ] Rattachement d'un Smart Link `attached` en lecture seule.
- [ ] Synchronisation URL, titre, description et image.
- [x] Affichage du lien, du bouton de copie, du QR et de son téléchargement.
- [x] Affichage du compteur de clics.
- [ ] Traitement bulk, progression, reprise et erreurs explicites.
- [ ] Vérification des comportements 401, 409, 422, 429, réseau et 5xx.
- [ ] Vérification de `Move existing installation`.
- [ ] Vérification de `Create a new installation` et de la quarantaine locale.
- [ ] Vérification de `Reconnect ZipQuantum`.
- [x] Vérification qu'une déconnexion/suppression de compte ne supprime pas automatiquement le Smart Link.

### 3. Exécuter les contrôles WordPress.org

- [x] Installer et exécuter Plugin Check avec les contrôles statiques et runtime.
- [x] Corriger toutes les erreurs bloquantes et examiner chaque avertissement.
- [ ] Valider `readme.txt` avec le validateur WordPress.org.
- [ ] Vérifier les licences de tous les fichiers et actifs inclus.
- [ ] Effectuer une dernière revue sécurité : capacités, nonces, validation, assainissement et échappement.
- [x] Vérifier qu'aucun log, outil de développement, test ou secret n'entre dans le ZIP.

### 4. Métadonnées de compatibilité

- [x] Conserver `Tested up to: 7.1`, version stable depuis le 19 août 2026.
- [x] Ajouter WordPress 7.1 à la matrice bloquante tout en conservant 6.9.7 et 7.0.4.
- [x] Revalider les en-têtes du fichier principal et de `readme.txt`.

### 5. Initialiser Git et la CI du plugin

- [x] Vérifier l'arbre de travail sans supprimer les travaux existants.
- [x] Créer le premier commit du dépôt `zipquantum-wordpress`.
- [x] Créer le dépôt Git public officiel sous l'organisation `ZipQuantum` (Xaere reste owner WordPress.org).
- [x] Ajouter le remote et pousser la branche principale.
- [x] Faire passer la CI complète.

Matrice bloquante :

- WordPress 6.9.7, 7.0.4 et 7.1.
- WooCommerce 10.9.latest et 11.0.1.
- PHP 7.4, 8.0, 8.2 et 8.3.

Matrice non bloquante :

- WooCommerce 11.1 RC2 puis stable.
- PHP 8.4 et 8.5.

### 6. Réaliser les trois bêtas

- [ ] Site WordPress seul.
- [ ] Site WordPress avec WooCommerce.
- [ ] Hébergement contraint avec WP-Cron irrégulier ou désactivé.
- [ ] Documenter les versions, résultats et anomalies de chaque site.
- [ ] Corriger uniquement les défauts du périmètre 1.0.0.

### 7. Déployer l'API en production

- [x] Sauvegarder la production et préparer le rollback.
- [x] Déployer le backend et les migrations.
- [x] Synchroniser et vérifier le catalogue des plans.
- [x] Vérifier les routes publiques et protégées.
- [x] Vérifier OAuth, handoff, PKCE, refresh, revoke et protection clone/move.
- [x] Confirmer que les routes attendues ne retournent plus HTTP 404.
- [x] Effectuer un test contrôlé depuis WordPress avec l'API de production.

### 8. Finaliser le compte propriétaire Xaere

- [ ] Remplacer l'adresse générique actuelle par une adresse officielle `@xaere.io` régulièrement surveillée.
- [ ] Ajouter `plugins@wordpress.org` à la liste des expéditeurs autorisés.
- [ ] Confirmer que Xaere reste owner du plugin.
- [ ] Préparer les comptes humains séparés qui deviendront committers/support representatives après approbation.

## Soumission WordPress.org

- [x] Reconstruire le ZIP depuis une source Git propre.
- [x] Réinstaller le ZIP final sur WordPress 7.1.
- [x] Refaire Plugin Check sur ce ZIP exact : aucune erreur, avertissements DB intentionnels seulement.
- [x] Calculer et conserver son SHA-256.
- [x] Soumettre le ZIP depuis le compte `xaere` sur `https://wordpress.org/plugins/developers/add/`.
- [x] Vérifier le slug proposé et le corriger de `zipquantum-smart-links-qr-codes` vers `zipquantum-smart-links` avant la revue.
- [x] E-mail officiel « Successful Plugin Submission » reçu et conservé le 2 septembre 2026.
- [ ] Suivre la file de review et conserver les prochains e-mails de la Plugin Review Team.

## Après la soumission

- [ ] Traiter uniquement les remarques de la Plugin Review Team.
- [ ] Répondre dans le fil d'e-mail de review existant.
- [ ] Ne pas ajouter de fonctionnalité hors périmètre pendant la review.

## Après approbation

- [ ] Configurer le dépôt SVN WordPress.org.
- [ ] Publier le code validé dans `trunk/`.
- [ ] Publier `tags/1.0.0/`.
- [ ] Publier icônes, bannières et captures dans `assets/`.
- [ ] Vérifier la page publique, l'installation depuis WordPress.org et les checksums.
- [ ] Ajouter les comptes humains comme committers/support representatives.
- [ ] Publier le tag Git `1.0.0` correspondant exactement au contenu SVN.

## Périmètre gelé

Toute nouvelle idée reste dans `BACKLOG >= 1.1`, notamment Gutenberg block, Elementor, liens de commandes WooCommerce, campagnes automatiques, UTM builder avancé, QR personnalisés, Shopify, webhooks et Multisite réseau.

Toute modification du périmètre 1.0.0 nécessite un amendement explicite de la spécification gelée.
