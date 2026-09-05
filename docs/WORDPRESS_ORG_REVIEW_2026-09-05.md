# WordPress.org review — 5 septembre 2026

## Demandes reçues

1. Établir officiellement le lien entre le compte WordPress.org `xaere`, Xaere et la marque ZipQuantum.
2. Remplacer les préfixes trop courts `ZQ_` et `zq_` par un préfixe unique d'au moins quatre caractères.

Le slug demandé reste `zipquantum-smart-links`. Aucun renommage du plugin n'est nécessaire dès lors que Xaere prouve qu'elle possède ZipQuantum.

## Correction préparée

- Classes et constantes : `ZIPQUANTUM_`.
- Fonctions, hooks, actions, nonces, options, métadonnées, tables et handles : `zipquantum_`.
- Noms de fichiers de classes alignés sur le nouveau préfixe.
- Route publique WooCommerce normalisée en `/zipquantum-coupon/` ; sa query variable interne utilise `zipquantum_coupon`.
- Convention de marque figée : uniquement `ZQ` ou `ZIPQUANTUM`, jamais la forme intermédiaire `ZIPQ`.
- Test anti-régression ajouté pour interdire le retour des anciens préfixes ou de la forme `ZIPQ` dans les fichiers distribués.

## Validation locale

- PHPUnit : 3 tests, 8 assertions — OK.
- PHPCS/WPCS : 14 fichiers — OK.
- Syntaxe PHP : OK.
- Audit des préfixes dans les sources et dans le ZIP : OK.
- ZIP final : 19 fichiers, 37 611 octets.
- SHA-256 : `BD358F4EDD2555005D242781BAB50E4465EAE2DACA04864624DBE617FBA08DB9`.
- Plugin Check WordPress.org sur le ZIP final : 0 erreur de préfixage ; 43 avertissements DB attendus pour la queue et la désinstallation.

## Actions externes restantes

1. [x] Adresse du compte WordPress.org remplacée et confirmée : `integrations@zq.tn`.
2. [x] Réception des messages de `plugins@wordpress.org` vérifiée via le transfert vers la boîte surveillée.
3. [x] Plugin Check refait sur le ZIP corrigé exact.
4. [x] ZIP final chargé dans la soumission existante le 5 septembre 2026.
5. [x] Réponse envoyée dans le même fil : propriété confirmée, préfixes corrigés, version mise à jour testée.

La soumission est désormais en attente d'une nouvelle action de la Plugins Team.
