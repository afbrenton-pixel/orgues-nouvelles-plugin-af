# Grille des revues (page « Revues hors abonnement »)

Élément Elementor **« Grille des revues »** (`includes/elementor/widgets/revues-grille.php`, `assets/css/revues-grille.css`, `assets/js/revues-grille.js`).

## Ce que fait le plugin
- Regroupe les produits WooCommerce publiés **par numéro**, du plus récent au plus ancien.
- Affiche pour chaque numéro la couverture et, dessous, des cases à cocher : **Papier** (numérique inclus), **Numérique**, **Cahier de partitions seul**. « Papier » et « Numérique » s'excluent.
- Les **prix viennent de WooCommerce** (prix normal du produit), jamais écrits à la main.
- Un seul bouton **Ajouter au panier** ajoute tous les choix (`?wc-ajax=add_to_cart`) puis ouvre le panier.

## Comment un numéro est reconnu
Par le **début du nom** des produits, suivi du numéro. Réglable dans l'onglet « Produits » de l'élément (une page française et une page anglaise ont chacune leur élément) :

| Produit | Début du nom par défaut | Exemple |
|---|---|---|
| Papier | `Orgues Nouvelles n°` | Orgues Nouvelles n°74 |
| Numérique | `ONED` | ONED74 |
| Cahier de partitions | `CahPart` | CahPart74 |

Un produit « Orgues Nouvelles n°69 (en téléchargement) » compte comme numérique. Le filtre `on_revues_grille_numeros` permet d'ajuster la liste.

## Ce qui se règle dans Elementor
Libellés (Papier, « numérique inclus », Numérique, Cahier seul, bouton, messages), débuts de nom des produits, largeur des colonnes, espaces, polices, couleurs, bouton (couleurs, survol, trait, angles), barre du bas. Les textes par défaut sont traduits en anglais (`languages/`).

## Limite connue
La reconnaissance par le nom est volontairement simple : elle suppose que les produits gardent leurs noms (`ONED…`, `CahPart…`). Si le webmaster préfère une liaison par champ (Pods), seule la fonction `on_revues_grille_numeros()` est à changer.
