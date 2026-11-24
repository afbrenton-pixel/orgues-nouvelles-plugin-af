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
| `format` | Choix | `standard` (défaut), `large` ou `playlist`. |
| `image_cote` | Choix | `gauche` (défaut) ou `droite`, pour les grandes vignettes : la photo se place de ce côté, quel que soit le modèle de boucle de la grille (classes `on-image-gauche` / `on-image-droite`). |
| `cadrage` | Choix | `carre`, `4-3` ou `original` (défaut). |
| `masquer_titre` | Oui / non | Cache le titre (l'étiquette reste affichée). |
| `afficher_description` | Oui / non | Affiche le sous-titre et l'extrait. Non par défaut : seuls l'étiquette et le titre sont affichés. |
| `pistes` | Fichier (plusieurs) | Format playlist : les MP3 de la médiathèque, dans l'ordre de lecture (glisser-déposer). Le titre affiché est celui du média dans la médiathèque (s'il est vide ou n'est que le nom du fichier, l'étiquette ID3 du MP3) ; l'artiste et la durée viennent des étiquettes du MP3. |
| `rang` | Nombre | Place voulue dans la grille (1 = en premier). Vide = ordre par date. |

La photo est l'image à la une de WordPress. Les valeurs internes `_on_source_id`, `_on_source_type`, `_on_locked`, `_on_autodraft` et `_on_sans_image` sont gérées par le code, hors Pods.

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

Termes de `etiquette_vignette` (la catégorie d'article « Blog » donne l'étiquette Échos) : Publications, CD, Sorties CD, Vidéos, Échos, Pub, La Revue, Newsletter, Ressources, Photos. Un contenu sans catégorie reconnue reçoit l'étiquette Échos. Les étiquettes Audio, Textes et Partitions viennent du type de ressource.

## Création automatique

À la publication d'un article, d'une ressource, d'un produit ou d'un CD (types filtrables avec `on_vignettes_source_types`), la vignette est créée ou mise à jour.

- **Image :** image à la une, sinon première image du texte (pour une ressource vidéo, l'image à la une est la miniature que `includes/content/video-thumbnail.php` y met à l'enregistrement). Sans image utilisable (absente, ou quasi uniformément noire ou blanche), la vignette reste en **brouillon** avec une pastille rouge dans le menu « Vignettes accueil ». Seules les ressources (petites vignettes sans photo) en sont dispensées.
- **Vignette modifiée à la main :** elle est verrouillée, la mise à jour automatique n'y touche plus.
- **Contenu dépublié :** sa vignette passe en brouillon. **Contenu supprimé :** sa vignette est supprimée.

## Requêtes Elementor

Dans le Loop Grid, section « Requête », champ « ID de la requête » :

| ID | Effet |
| --- | --- |
| `vignettes_rang` | Les vignettes qui ont un rang d'abord (du plus petit au plus grand), puis les autres de la plus récente à la plus ancienne. |
| `vignettes_larges` | Seulement les vignettes dont `format` vaut `large` (par rang puis date). Une grille par grande vignette : la première avec décalage 0, la suivante avec décalage 1. |
| `vignettes_standard` | Toutes les autres vignettes (rang puis date). Le nombre de vignettes et le décalage se règlent dans le Loop Grid. |
| `vignettes_centre` | Ancienne requête, conservée : grandes vignettes aux places 1 et 6 (filtre `on_vignettes_grandes_places`). |

La zone se choisit dans le Loop Grid (filtre sur la taxonomie `zone_vignette`).

## Balises dynamiques Elementor

- **Lien de la vignette** (`on-vignettes-lien`) : champ `lien`, sinon adresse du contenu d'origine. La balise Pods d'URL renvoyait « Array » sur ce champ.

## Lecteur audio et vidéo

`includes/vignettes/player.php` et `assets/js/vignettes-player.js` : une ressource contenant des MP3 se lit au clic sur l'image ou le pictogramme (MP3 enchaînés, barre de lecture) ; une ressource contenant une vidéo Vimeo s'ouvre dans un lecteur intégré (croix ou Échap pour fermer, sans suivi : `dnt=1`).

## Styles

`assets/css/vignettes-accueil.css` ne garde que ce qu'Elementor ne sait pas faire : ordre des colonnes sur tablette et téléphone, cadre des images, lecteur. Polices, couleurs et tailles se règlent dans Elementor.

## Lien vers l'objet source

La fiche d'une vignette affiche, dans la colonne de droite, une boîte « Objet source » : titre, type et statut de l'objet (actualité, ressource, vidéo, magazine…), bouton « Modifier l'objet » et lien « Voir l'objet sur le site ». L'objet est celui d'où la vignette a été créée ; pour une vignette créée à la main, c'est celui que désigne son champ « Lien », s'il pointe vers un contenu du site. Sans objet trouvé, la boîte n'apparaît pas.

## Polylang

Le type de contenu et les étiquettes sont déclarés traduisibles ; les requêtes suivent la langue courante. Les zones ne sont pas traduisibles. Les traductions anglaises de toutes les étiquettes sont créées avec les termes (Ressources → Resources, Vidéos → Videos, Échos → Echoes, Sorties CD → CD releases, La Revue → The Journal ; Publications, CD, Pub, Newsletter et Photos gardent leur nom). Une traduction déjà existante n'est pas modifiée. Les vignettes jumelles anglaises reprennent l'étiquette traduite.

## Remarque : source du Loop Grid

Le type de contenu doit avoir `show_in_nav_menus` = oui (réglage Pods « Afficher dans les menus de navigation »), sinon Elementor Pro ne le propose pas comme source dans le Loop Grid.

Il en va de même des deux taxonomies (`zone_vignette` et `etiquette_vignette`) : sans « Afficher dans les menus de navigation », le champ « Terme » de la requête du Loop Grid ne les trouve pas (« No results found »). Dans le fichier du paquet Pods, les deux ont `show_in_nav_menus` = 1 ; sur un site déjà installé, le réglage se change dans Pods Admin > Modifier la taxonomie > Options avancées.

## Éléments masqués au choix

`includes/vignettes/affichage.php` ajoute à chaque vignette les classes `on-sans-titre` (case « Masquer le titre » cochée) et `on-sans-description` (case « Afficher la description » non cochée). Le CSS cache les widgets des modèles de boucle portant les classes `on-w-titre` et `on-w-description`. Ces classes sont à poser (réglage « Classes CSS » du widget) sur le titre et sur le sous-titre / l'extrait du modèle « standard » ; les grandes vignettes n'ont pas `on-w-description` et gardent leur texte.

## Vignette playlist

Format `playlist` : une vignette avec photo (image à la une), étiquette, titre et une liste de pistes MP3 qu'on édite dans le champ « Pistes » du formulaire (ajout depuis la médiathèque, glisser-déposer pour l'ordre).
- Grille : « ID de la requête » = `vignettes_playlist` (seulement les vignettes de format playlist ; la zone se choisit dans le Loop Grid). Les vignettes playlist n'apparaissent jamais dans les autres grilles (`vignettes_rang`, `vignettes_centre`, `vignettes_larges`, `vignettes_standard`).
- Modèle de boucle : « Vignette accueil – playlist (Pods) », copie du modèle standard (image, étiquette, titre, sous-titre ; la liste de pistes est ajoutée par `assets/js/vignettes-player.js`).
- Lecture : clic sur une piste, sur l'image ou sur le bouton ; lecteur de l'accueil (continue tant que la page reste ouverte), piste en cours surlignée, pistes enchaînées, boutons précédent / suivant.
- Les 5 premières pistes sont visibles, le reste se déplie (« Voir les N pistes »). Le nombre de pistes visibles se change par le filtre `on_vignettes_playlist_visibles`.
- Version anglaise : jumelle Polylang de la vignette, avec sa propre liste de pistes.
- Colonnes fixes (`.on-sticky`) plus hautes que l'écran : `assets/js/vignettes-sticky.js` remonte leur position fixe pour que le bas de la colonne (fin de la playlist) puisse être atteint, puis la colonne reste fixée là.
- Photo de la playlist : taille, alignement et place réglés dans Elementor, dans le modèle de boucle « Vignette accueil – playlist (Pods) » (widget image : largeur personnalisée, alignement du contenant). Rien dans le CSS du plugin.

## Préfixe des noms (CSS, JavaScript, champs)

Tous les noms propres aux vignettes commencent par `on` comme le reste du plugin : classes CSS `on-…`, attributs `data-on-…`, variables JavaScript `ON_MEDIA` et `ON_I18N`, champs `_on_source_id`, `_on_locked`, etc. L'élément en cours de lecture d'une playlist porte la classe `on-current`.

## Page « Importer les vignettes » (reprise des contenus déjà publiés)

Menu « Vignettes accueil » > « Importer les vignettes » (administrateurs seulement). Le plugin ne crée une vignette qu'à l'enregistrement d'un contenu ; cette page crée celles des contenus **déjà publiés** (avec leur jumelle anglaise quand il y en a une), par exemple une seule fois à la mise en production.

1. Choisir les types (actualités, ressources, CD, produits ; les produits sont décochés au départ), une date de départ facultative et, si besoin, un motif de titres à écarter (expression régulière appliquée au titre de tous les types cochés ; vide au départ : rien n'est écarté ; plus pratique avec un seul type coché). Exemples : `Calendrier|Calendar`, `^Test`, `Download|Booklet|in English`.
2. **Simulation** : tableau par type (contenus retenus, ceux qui ont déjà une vignette, ceux à traiter) et les 30 premiers titres. Rien n'est écrit. Le bouton « Créer » reste grisé tant que la simulation n'est pas faite avec les réglages affichés.
3. **Importer les vignettes** : traitement par lots de 5 contenus (au plus 20 secondes par requête) avec barre de progression. À la fin, le cache d'Elementor est vidé.

Détails : la page appelle `on_vignettes_upsert()` pour chaque contenu, **sans réenregistrer le contenu** : sa date de modification ne change pas. On peut relancer sans doublon ; les vignettes modifiées à la main ou à la corbeille ne sont pas touchées (règle de `on_vignettes_upsert()`). La case « ne traiter que les contenus qui n'ont pas encore de vignette » est cochée au départ. Les contenus protégés par mot de passe sont ignorés. Une vignette créée sans image utilisable reste en brouillon avec la pastille rouge du menu. Fichiers : `includes/vignettes/creation-en-masse.php`, `assets/js/vignettes-creation.js`, `tests/VignettesCreationEnMasseTest.php`.
