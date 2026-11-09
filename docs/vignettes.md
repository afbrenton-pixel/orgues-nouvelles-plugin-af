# Vignettes d'accueil

Statut : prêt pour relecture (branche `vignettes-accueil`). Document de conception complet : voir le document partagé avec le webmaster.

Une « vignette » est une carte de la page d'accueil (image, étiquette, titre, extrait). Les vignettes sont créées automatiquement à partir des contenus du site, rangées par **zone** (emplacement sur la page) et affichées par des **Loop Grid Elementor Pro**. Le plugin fournit les données, les requêtes et deux balises dynamiques ; la mise en page (colonnes, polices, couleurs, nombre de vignettes) se règle dans Elementor.

## Prérequis

- Pods (type de contenu, champs et taxonomies), Elementor Pro (Loop Grid).
- Polylang (facultatif) : les vignettes et les étiquettes sont alors traduisibles.

## Contenu défini dans Pods

Fichier d'import : `docs/pods/vignette_accueil-package.json` (Pods > Composants > Import/Export Packages > Import).

| Élément | Nom technique | Rôle |
| --- | --- | --- |
| Type de contenu | `vignette_accueil` | Une vignette de la page d'accueil. Non consultable seule sur le site (`publicly_queryable` = non). |
| Taxonomie | `zone_vignette` | Emplacement sur la page (voir « Zones »). Non traduisible : une seule zone sert aux deux langues. |
| Taxonomie | `etiquette_vignette` | Le mot de l'étiquette orange. Traduisible avec Polylang. |
| Taxonomie existante | `type_de_ressource` | À rattacher aussi à `vignette_accueil` (case à cocher dans la taxonomie, non incluse dans l'import pour ne pas écraser sa définition). |

## Champs de `vignette_accueil`

| Champ | Type Pods | Valeurs |
| --- | --- | --- |
| `sous_titre` | Texte | Ligne sous le titre (facultatif). |
| `lien` | Site web | Lien de la vignette. Vide = lien vers le contenu d'origine. |
| `format` | Choix | `standard` (défaut) ou `large`. |
| `image_cote` | Choix | `gauche` (défaut) ou `droite`, pour les grandes vignettes. |
| `cadrage` | Choix | `carre`, `4-3` ou `original` (défaut). |
| `masquer_titre` | Oui / non | Cache l'étiquette et le titre. |
| `rang` | Nombre | Place voulue dans la grille (1 = en premier). Vide = ordre par date. |
| `numero` | Nombre | Numéro du magazine (ressources du dernier numéro). |

La photo est l'image à la une de WordPress. Les valeurs internes `_onv_source_id`, `_onv_source_type`, `_onv_locked`, `_onv_autodraft` et `_onv_sans_image` sont gérées par le code, hors Pods.

## Zones

Termes de `zone_vignette`, créés par le code (une fois, sans jamais modifier un terme existant) :

| Slug | Libellé | Contenu |
| --- | --- | --- |
| `centre-actus` | Centre – Actus | Articles et autres contenus. |
| `centre-publications` | Centre – Publications | Produits et CD. |
| `centre-videos` | Centre – Vidéos | Toute ressource contenant une vidéo Vimeo. |
| `gauche-fixe` | Gauche – fixe en haut | Newsletter. |
| `gauche-ressources` | Gauche – Ressources | Ressources audio, textes et partitions. |
| `droite-fixe` | Droite – fixe en haut | Encart fixe de la colonne de droite. |
| `droite-pubs` | Droite – Pubs | Colonne de droite sous l'encart fixe. |

## Étiquettes

Termes de `etiquette_vignette` : Publications, CD, Sorties CD, Vidéos, Échos (anglais : Echoes), Blog, Pub, La Revue, Actus, Newsletter, Ressources, Photos. Les étiquettes Audio, Textes et Partitions viennent du type de ressource.

## Création automatique

À la publication d'un article, d'une ressource, d'un produit ou d'un CD (types filtrables avec `on_vignettes_source_types`), la vignette est créée ou mise à jour.

- **Image :** image à la une, sinon première image du texte ; pour une vidéo Vimeo, la miniature est le premier choix. Sans image utilisable (absente, ou quasi uniformément noire ou blanche), la vignette reste en **brouillon** avec une pastille rouge dans le menu « Vignettes accueil ». Seules les ressources (petites vignettes sans photo) en sont dispensées.
- **Vignette modifiée à la main :** elle est verrouillée, la mise à jour automatique n'y touche plus.
- **Contenu dépublié :** sa vignette passe en brouillon. **Contenu supprimé :** sa vignette est supprimée.

## Requêtes Elementor

Dans le Loop Grid, section « Requête », champ « ID de la requête » :

| ID | Effet |
| --- | --- |
| `vignettes_rang` | Les vignettes qui ont un rang d'abord (du plus petit au plus grand), puis les autres de la plus récente à la plus ancienne. |
| `vignettes_larges` | Seulement les vignettes dont `format` vaut `large` (par rang puis date). Une grille par grande vignette : la première avec décalage 0, la suivante avec décalage 1. |
| `vignettes_standard` | Toutes les autres vignettes (rang puis date). Le nombre de vignettes et le décalage se règlent dans le Loop Grid. |
| `vignettes_numero` | Seulement les vignettes dont `numero` est le plus élevé de la zone choisie (ressources du dernier numéro). |
| `vignettes_centre` | Ancienne requête, conservée : grandes vignettes aux places 1 et 6 (filtre `on_vignettes_grandes_places`). |

La zone se choisit dans le Loop Grid (filtre sur la taxonomie `zone_vignette`).

## Balises dynamiques Elementor

- **Numéro des ressources de l'accueil** (`on-vignettes-numero`) : numéro le plus élevé des vignettes publiées de la zone `gauche-ressources`, avec un texte avant (par défaut « Ressources N° »). Pour le titre de la colonne de gauche.
- **Lien de la vignette** (`on-vignettes-lien`) : champ `lien`, sinon adresse du contenu d'origine. La balise Pods d'URL renvoyait « Array » sur ce champ.

## Lecteur audio et vidéo

`includes/vignettes/player.php` et `assets/js/vignettes-player.js` : une ressource contenant des MP3 se lit au clic sur l'image ou le pictogramme (MP3 enchaînés, barre de lecture) ; une ressource contenant une vidéo Vimeo s'ouvre dans un lecteur intégré (croix ou Échap pour fermer, sans suivi : `dnt=1`).

## Styles

`assets/css/vignettes-accueil.css` ne garde que ce qu'Elementor ne sait pas faire : ordre des colonnes sur tablette et téléphone, cadre des images, lecteur. Polices, couleurs et tailles se règlent dans Elementor.

## Polylang

Le type de contenu et les étiquettes sont déclarés traduisibles ; les requêtes suivent la langue courante. Les zones ne sont pas traduisibles. La traduction anglaise de l'étiquette « Échos » est créée avec les termes.

## Migration (outil temporaire)

« Vignettes accueil > Migration » reprend les vignettes de l'ancien plugin de test (`on_vignette`, champs `_onv_*`) : une simulation (rien n'est écrit) puis la migration. Les anciennes vignettes ne sont jamais modifiées ni supprimées. Sans objet sur un site qui n'a pas cet ancien plugin.

## Remarque : source du Loop Grid

Le type de contenu doit avoir `show_in_nav_menus` = oui (réglage Pods « Afficher dans les menus de navigation »), sinon Elementor Pro ne le propose pas comme source dans le Loop Grid.
