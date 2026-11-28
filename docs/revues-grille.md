# Revues hors abonnement : choix papier / numérique / cahier et un seul bouton d'ajout au panier

La page se compose **dans Elementor** avec des éléments ordinaires et deux éléments du plugin.

| Élément | Où | Rôle |
|---|---|---|
| **Grille de boucle** (Elementor Pro) | page | Requête : produits de la catégorie « Revues hors abonnement ». Colonnes, espaces, pagination (ou « tout sur une page » en mettant un grand nombre d'éléments par page) se règlent ici. |
| **Modèle de boucle** (Elementor) | modèle « Boucle » des produits | Dessin libre d'une carte : image à la une du produit (balise dynamique), titre, et l'élément du plugin ci-dessous. |
| **Choix d'achat du numéro** (plugin) | dans le modèle de boucle | Les cases à cocher du numéro : Papier (numérique inclus), Numérique, Cahier de partitions seul. Prix lus dans WooCommerce. |
| **Total du choix** (plugin) | page, sous la grille | « 2 articles · 27 € » et les messages. |
| **Bouton** (Elementor ordinaire) | page, à côté du total | À qui l'on donne la classe CSS `onr-add` (Avancé > Classes CSS). Un clic ajoute tous les choix au panier puis ouvre le panier. Style, texte, survol : réglages Elementor habituels. |

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
