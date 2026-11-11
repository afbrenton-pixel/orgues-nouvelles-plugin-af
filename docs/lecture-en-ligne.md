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

## Comptage et protection de l'adresse réelle du PDF

- À l'ouverture du PDF (la requête qui débute au début du fichier, pas les morceaux suivants ni les requêtes HEAD), le lecteur appelle `on_usage_record($user, $produit, 'read')` si le compteur est présent (branche `comptage-usage`, voir `docs/comptage-usage.md`). Sans le compteur, rien n'est compté et rien ne casse.
- Le nom du fichier envoyé dans l'en-tête `Content-Disposition` est l'identifiant du produit (par exemple `cahpart73.pdf`), jamais le vrai nom du fichier : WooCommerce renomme au hasard les PDF envoyés depuis la page du produit, et ce nom aléatoire ne doit pas sortir.

## Élément Elementor « Lecteur PDF en ligne »

Remplace le bloc HTML provisoire des modèles. À glisser dans le modèle de page (catégorie « Général »).

- **Document à lire** : cahier de partitions ou magazine. Le produit est retrouvé tout seul : même numéro (champ `numero`) que la page, même langue (Polylang), téléchargeable, gratuit pour la personne connectée (`on_usage_page_product`). Le champ « Le nom du produit contient » est facultatif (par défaut `CahPart` ou `ONED`).
- Le PDF est lu par `/?on_action=read_pdf&product_id=…` : l'adresse réelle du fichier n'apparaît jamais, la lecture est comptée.
- Sans droit de lecture, l'élément n'affiche rien (le bandeau « Abonnés Magazines » reste géré à part).
- Textes français / anglais selon la langue de la page. Les enregistrements sont repérés par les liens `.mp3` du PDF.
- Pas d'extrait pour les visiteurs sans droit dans cette première version.
- Fichiers : `includes/elementor/widgets/online-reader.php`, `assets/js/online-reader.js`, `assets/css/online-reader.css` (PDF.js 3.11.174 depuis cdnjs, chargé seulement quand l'élément est sur la page).
