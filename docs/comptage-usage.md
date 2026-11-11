# Comptage des lectures et des téléchargements

Branche : `comptage-usage`. Spécification : « Spécification : comptage des lectures et téléchargements » (artifact Claude, 7 novembre 2026, mise à jour le 10 novembre).

## Principe

- Chaque **lecture** et chaque **téléchargement** d'un numéro est compté, par client, par produit et par type.
- Un téléchargement ne provoque plus aucun **e-mail** de commande. La première fois, une commande à 0 € est quand même créée (« commande silencieuse ») parce que le filigrane du PDF (PDF Ink Lite) est posé à partir des données d'une commande. Aux téléchargements suivants, la même commande est réutilisée.
- Une lecture ne crée aucune commande.
- Le droit d'accès ne change pas : il vient de `product-free-for-plans.php` (le produit est gratuit pour le client). Aucun test du type d'abonnement dans ce code.

## Où est le code

Tout ce qui a été ajouté dans le code existant est encadré par `=== COMPTAGE-USAGE (début) ===` et `=== COMPTAGE-USAGE (fin) ===`.

| Fichier | Rôle |
| --- | --- |
| `includes/usage/table.php` | table `{préfixe}on_usage`, créée automatiquement quand la version de structure change |
| `includes/usage/count.php` | `on_usage_record($user_id, $product_id, $type)` : ajoute 1 au compteur |
| `includes/usage/rest-api.php` | API en lecture seule `GET /wp-json/on/v1/usage` |
| `includes/usage/dynamic-tags.php` | balises Elementor « Adresse de lecture » et « Adresse de téléchargement » |
| `includes/usage/download-message.php`, `assets/js/download-message.js`, `assets/css/download-message.css` | message après téléchargement (classe CSS `on-dl-message`) |
| `includes/products/direct-download-free.php` | commande silencieuse réutilisée, coupure des e-mails, appel du compteur (blocs encadrés) |
| `includes/products/read-online.php` (branche `lecture-en-ligne`) | doit appeler `on_usage_record(…, 'read')` à l'ouverture du PDF |

## La table `on_usage`

Une ligne par `(user_id, product_id, type)` ; un usage de plus fait `count + 1` et met à jour `last_at`. Dates en UTC.

| Colonne | Contenu |
| --- | --- |
| `user_id` | le client |
| `product_id` | le produit WooCommerce |
| `numero` | champ Pods `numero` du produit |
| `magazine` | identifiant (slug) du contenu `magazine` lié au produit |
| `type` | `read` ou `download` |
| `count` | nombre d'usages |
| `first_at`, `last_at` | première et dernière date |

Règles : un téléchargement compte à chaque envoi (une demande répétée ajoute 1) ; une lecture compte à l'ouverture du PDF, pas à chaque morceau chargé ; un refus (401, 403, 404) ne compte rien.

## L'API

`GET /wp-json/on/v1/usage`, administrateurs seulement, aucune route d'écriture.

Paramètres : `user_id` ou `email`, `numero`, `magazine`, `type` (`read`, `download`), `since` (date, UTC), `page`, `per_page` (100 par défaut, 500 au maximum).

Réponse : `total`, `page`, `per_page`, `items` (liste de `user_id`, `email`, `product_id`, `numero`, `magazine`, `type`, `count`, `first_at`, `last_at`).

Un outil externe utilise un « mot de passe d'application » WordPress, en HTTPS.

## Les boutons dans Elementor

Dans le champ « lien » d'un bouton Elementor ordinaire, choisir la balise dynamique « Adresse de lecture » ou « Adresse de téléchargement ».

Le produit est retrouvé automatiquement : même `numero` que la page, dans la langue de la page (Polylang), téléchargeable, gratuit pour le client connecté. Si la page propose plusieurs produits (magazine, cahier de musique), le champ « Le nom du produit contient » de la balise choisit le bon (par exemple `CahPart`). Aucun produit gratuit pour le client : la balise est vide.

À noter : l'adresse de téléchargement contient un jeton de sécurité (nonce) qui expire. Si Elementor garde la page en cache, le jeton peut périmer : à surveiller sur le site de test.

## Le message après téléchargement

Un conteneur Elementor avec la classe CSS `on-dl-message` (onglet Avancé > Classes CSS) :

- il est masqué sur le site tant que le client n'a pas cliqué sur un lien de téléchargement, puis il apparaît ;
- il reste visible dans l'éditeur Elementor ;
- le texte, les couleurs et la langue se règlent dans Elementor (un message par langue grâce à Polylang).

## Les commandes silencieuses

- Marqueur : meta `_on_silent_free_download` = `1`, posée avant le passage en « Terminée ».
- Les e-mails WooCommerce de ces commandes sont supprimés par `on_silent_order_email_enabled` (filtres `woocommerce_email_enabled_*`).
- La recherche d'une commande à réutiliser (`on_find_reusable_free_download`) retient les commandes terminées, à 0 €, payées par « other », qui contiennent le produit et dont les droits de téléchargement ne sont ni épuisés ni expirés. Sinon, une nouvelle commande est créée comme avant.

## Ce qui ne change pas

Produits payants, abonnements, règles d'accès (`on_liste_numeros`), « Mon compte > Mes magazines », et les commandes payantes (e-mails habituels).
