# Vignettes d'accueil : données Pods

Statut : en cours de conception (branche `vignettes-accueil`). Document de conception complet : voir le document partagé avec le webmaster.

## Contenu défini dans Pods

Fichier d'import : `docs/pods/vignette_accueil-package.json` (Pods > Composants > Import/Export Packages > Import).

| Élément | Nom technique | Rôle |
| --- | --- | --- |
| Type de contenu | `vignette_accueil` | Une vignette de la page d'accueil. Non consultable seule sur le site (`publicly_queryable` = non). |
| Taxonomie | `zone_vignette` | Emplacement sur la page : `centre-actus`, `centre-publications`, `gauche-fixe`, `gauche-ressources`, `droite-fixe`, `droite-pubs`. |
| Taxonomie | `etiquette_vignette` | Le mot de l'étiquette orange (La Revue, Publications, Sorties CD, CD, Blog, Vidéos, Ressources…). |
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

La photo est l'image à la une de WordPress. Les valeurs internes `_onv_source_id`, `_onv_source_type` et `_onv_locked` sont gérées par le code, hors Pods.

## Termes à créer

Les termes des deux taxonomies ne font pas partie du paquet Pods (un paquet ne contient pas les termes). Ils seront créés par le code d'installation et la commande de migration.
