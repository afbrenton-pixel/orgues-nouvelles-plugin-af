# Lecture en ligne des PDF

Le lecteur en ligne (PDF.js, widget HTML des modèles Elementor « Page Partition », « Page PartitionEN » et « Magazine ») charge le PDF par une adresse du site qui **contrôle les droits de la personne connectée**, au lieu d'un chemin écrit en dur dans `/wp-content/uploads`.

## Règle d'accès (la même que partout ailleurs)

Un produit téléchargeable (catégorie `telechargements`, slugs `cahpartNN` pour les cahiers de partitions, `onedNN` pour les magazines numériques) a deux champs Pods : `numero` et `plans`.
`includes/products/product-free-for-plans.php` met son prix à 0 si le numéro figure dans `on_liste_numeros($plans)` pour la personne connectée. C'est cette même règle qui sert pour « Mon compte > Mes magazines », les boutons Elementor (`restrict-magazines.php`) et les téléchargements gratuits.

**Lecture en ligne autorisée ⇔ personne connectée ET produit téléchargeable ET prix = 0 pour elle.** Aucune règle nouvelle.

## Adresse

```
/?on_action=read_pdf&product=cahpart73      (slug)
/?on_action=read_pdf&product_id=47598       (identifiant)
```

| Cas | Réponse |
|---|---|
| non connecté | 401 |
| produit introuvable | 404 |
| produit non gratuit pour cette personne | 403 |
| pas de fichier, fichier distant ou non PDF | 404 |
| autorisé | le PDF en `inline`, avec prise en charge des requêtes `Range` (nécessaire à PDF.js), `Cache-Control: private`, `X-Robots-Tag: noindex` |

Pas de nonce : la requête ne modifie rien et un nonce serait mis en cache avec la page (cache d'éléments Elementor) puis expirerait.

Le fichier servi est le **premier fichier téléchargeable du produit** : c'est le PDF du produit WooCommerce qui fait foi (pour le n°73, vérifier qu'il s'agit bien du PDF avec les liens MP3).

## Filtre

`on_read_online_can_access( bool $can, WC_Product $product, int $user_id )` permet d'ajuster la règle (par exemple pour un accès éditorial).

## À faire ensuite (non inclus)

- Compter les lectures (commande gratuite « Lecture en ligne », au plus une par heure et par numéro, sans e-mail).
- Bloquer l'accès direct aux PDF dans `/wp-content/uploads` (règle serveur) ou les placer dans le dossier protégé de WooCommerce.
