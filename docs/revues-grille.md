# Revues hors abonnement : choix papier / numérique / cahier et un seul bouton d'ajout au panier

La page se compose **dans Elementor** avec des éléments ordinaires et deux éléments du plugin.

| Élément | Où | Rôle |
|---|---|---|
| **Grille de boucle** (Elementor Pro) | page | Source : Produits. Dans « Requête », mettre l'**ID de requête** `revues_numeros` (français) ou `revues_numeros_en` (anglais) : le plugin choisit alors lui-même un produit par numéro, du plus récent au plus ancien, sur une seule page. Colonnes et espaces se règlent dans la grille. |
| **Modèle de boucle** (Elementor) | modèle « Boucle » des produits | Dessin libre d'une carte : image à la une du produit (balise dynamique), titre, et l'élément du plugin ci-dessous. |
| **Choix d'achat du numéro** (plugin) | dans le modèle de boucle | Le titre « Orgues Nouvelles n°N » et les cases à cocher du numéro : Papier (numérique inclus), Numérique, Cahier de partitions seul. Prix lus dans WooCommerce ; « Inclus » à la place de 0 € pour qui a déjà les droits ; ligne Papier grisée « épuisé » si le numéro n'existe plus en papier (champ « Papier épuisé » vide = ligne masquée). Le modèle de boucle n'a donc que l'image à la une (balise dynamique) et cet élément. |
| **Total du choix** (plugin) | page, sous la grille | « 2 articles · 27 € » et les messages. |
| **Bouton** (Elementor ordinaire) | page, à côté du total | À qui l'on donne la classe CSS `on-revues-add` (Avancé > Classes CSS). Un clic ajoute tous les choix au panier puis ouvre le panier. Style, texte, survol : réglages Elementor habituels. |

La barre du bas collante est un conteneur Elementor (Avancé > Effets de mouvement > Sticky > Bas) qui contient le total et le bouton.

## Comment un numéro est reconnu
Par le **début du nom** des produits, suivi du numéro. Réglable dans l'onglet « Produits » de l'élément « Choix d'achat du numéro » (une page française et une page anglaise ont chacune leur modèle) :

| Produit | Début du nom par défaut | Exemple |
|---|---|---|
| Papier | `Orgues Nouvelles n°` | Orgues Nouvelles n°74 |
| Numérique | `ONED` | ONED74 |
| Cahier de partitions | `CahPart` | CahPart74 |

Un produit « Orgues Nouvelles n°69 (en téléchargement) » compte comme numérique. Le filtre `on_revues_grille_numeros` permet d'ajuster la liste. Un numéro n'est affiché qu'une fois : par son produit papier, sinon son numérique, sinon son cahier.

## Limites connues
- La reconnaissance par le nom suppose que les produits gardent leurs noms (`ONED…`, `CahPart…`). Si le webmaster préfère une liaison par champ (Pods), seule la fonction `on_revues_grille_numeros()` est à changer.
- Le prix affiché est le prix normal du produit. Pour un compte qui a déjà les droits, le site rend ces produits gratuits : le prix affiché est alors 0 €.

## Version anglaise
Pas de papier en anglais : créer un second modèle de boucle (image + « Choix d'achat du numéro » avec début de nom papier **vide**, numérique `Orgues-Nouvelles`, cahier `Orgues Nouvelles Music Booklet`, « Papier épuisé » vide, libellés en anglais), une page liée en anglais avec l'ID de requête `revues_numeros_en`, et les textes du « Total du choix » et du bouton en anglais.

## À recréer sur le site en ligne (rien de tout cela n'est dans le code)
1. Le modèle de boucle (français, et anglais).
2. La page : texte d'introduction, grille de boucle, conteneur flottant (position fixe, en bas à droite, ~30 % de large, pleine largeur sur téléphone) avec « Total du choix » et un bouton Elementor de classe CSS `on-revues-add`.
3. Remplacer ou rediriger la page de catégorie `/product-category/magazine/`.

## Remarque technique
La requête de la grille charge des produits WooCommerce, ce qui lance d'autres requêtes WordPress : le filtre de requête a une garde contre la rappel infini (sans elle, la page ne s'affiche plus).
