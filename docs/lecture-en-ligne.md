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

- **Adresse du PDF** : champ avec balise dynamique. Choisir la balise « Adresse de lecture » pour un magazine ou un cahier de partitions (le produit du champ Pods « Téléchargement » de la page, publié, téléchargeable, gratuit pour la personne connectée ou déjà acheté ; `on_usage_page_product`), ou saisir l'adresse de n'importe quel PDF (une ressource, un article : pas de comptage dans ce cas). Champ vide : l'élément prend le produit du champ « Téléchargement » de la page. **Titre par défaut** : cahier, magazine ou document. Plus de règle sur le nom du produit.
- Le PDF est lu par `/?on_action=read_pdf&product_id=…` : l'adresse réelle du fichier n'apparaît jamais, la lecture est comptée.
- **Ouverture en pop-in** : l'élément n'affiche qu'un bouton et ne réserve aucune place dans la page. Au clic, le lecteur s'ouvre sur toute la page (sans passer en plein écran du navigateur) ; il est déplacé dans `<body>`, donc la colonne où l'on place l'élément n'a aucune importance. Fermeture : bouton « Fermer » ou touche Échap (qui ferme d'abord la liste des pistes audio si elle est ouverte) ; la lecture audio est alors mise en pause. Le bouton « Plein écran » reste disponible.
- Sans droit de lecture, l'élément n'affiche rien (le bandeau « Abonnés Magazines » reste géré à part).
- Les textes sont écrits en français avec le système de traduction de WordPress (domaine `orgues-nouvelles`) ; l’anglais est dans `languages/orgues-nouvelles-en_GB.po`. Les enregistrements sont repérés par les liens `.mp3` du PDF.
- Pas d'extrait pour les visiteurs sans droit dans cette première version.
- Fichiers : `includes/elementor/widgets/online-reader.php`, `assets/js/online-reader.js`, `assets/css/online-reader.css` (PDF.js 3.11.174 depuis cdnjs, chargé seulement quand l'élément est sur la page).

## Affichage des pages (1 ou 2 pages) et zoom

Réglé dans l'élément Elementor, section « Affichage des pages » :
- **Pages affichées**, par type d'écran (ordinateur, tablette, téléphone) : *Automatique* (2 pages si la zone du lecteur est assez large), *1 page* ou *2 pages côte à côte*. Par défaut : ordinateur automatique, tablette et téléphone 1 page.
- **Largeur minimale pour 2 pages** (mode automatique) : 860 px par défaut.
- Les seuils tablette et téléphone sont ceux d'Elementor (`breakpoints`), transmis au script dans l'attribut `data-pages`.
- **Zoom** : si deux pages sont affichées (et que le mode n'est pas « 2 pages »), le premier clic sur « + » passe à une page (la page de gauche) et applique le premier niveau de zoom ; « − » au zoom minimal revient aux deux pages. En mode « 2 pages », « + » agrandit sans changer de mode ; en mode « 1 page », « − » ne remet jamais deux pages.

## Boutons d'un numéro selon la situation du visiteur

Pour poser les mêmes boutons dans les modèles de page d'un numéro (magazine ou cahier), le réglage « Abonnés Magazines » (onglet Avancé de tout élément Elementor, liste « Visibilité aux magazines ») propose quatre choix : visiteur non connecté ; connecté sans droit de lire ; connecté avec droit de lire (abonné, acheteur, numéro gratuit) ; PDF téléchargeable gratuitement. Le droit est celui de la lecture en ligne. Dans l'éditeur, tout reste visible.

Un choix « ne peut pas lire (non connecté ou sans droit) » sert à proposer l'extrait PDF du numéro (champ « pdf_sommaire » de la page) avec un second élément Lecteur PDF en ligne, de type « document », dont l'adresse du PDF est ce champ : l'extrait se lit sans compte ni droit.

Un cinquième choix, « sans droit, et version papier en vente », sert au bouton d'achat du papier : le produit papier est un produit publié, ni virtuel ni téléchargeable, dont le champ « Magazine » désigne la page du numéro (ce champ donne aussi l'accès en ligne après l'achat). Balise « Fiche de la version papier ».

Le conteneur du bloc porte la classe CSS « on-actions » : le plugin donne alors la même largeur, hauteur et police à tous ses boutons.

La balise « Extrait PDF du numéro » donne l'adresse (du site courant) du fichier du champ « pdf_sommaire », à mettre dans l'adresse du PDF de ce second lecteur.

Les balises « Page du CD du numéro » et « Page du cahier de partitions du numéro » donnent l'adresse des pages reliées par les champs « page_cd » et « page_partitions », pour rendre cliquables l'image et le titre du CD et du cahier.

Les balises « Image du CD du numéro » et « Image du cahier de partitions du numéro » donnent l'image à la une de ces deux pages, pour que les images de la fiche suivent le numéro affiché.

Deux balises dynamiques s'ajoutent aux adresses de lecture et de téléchargement : « Fiche du numéro (boutique) » (produit du champ Téléchargement) et « Adresse de connexion (retour sur cette page) ».

Un acheteur d'un numéro payant n'a pas de bouton « Télécharger » : le téléchargement gratuit refuse les produits payants. Il télécharge depuis « Mon compte ».
