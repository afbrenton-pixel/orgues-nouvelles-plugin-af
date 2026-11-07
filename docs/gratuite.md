# Gratuité -26 ans : abonnement Étudiant gratuit

Documentation du module `includes/gratuite/`. Branche : `gratuite-26`.
Spécification complète : plan technique du 7 novembre 2026 (Anne-Françoise).

## Principe

- Une personne de moins de 26 ans (moins de 27 ans au renouvellement) remplit un
  **formulaire Elementor Pro** avec un justificatif.
- Une **autorisation** est créée (type de contenu Pods) et acceptée ou refusée dans
  le menu « Gratuité -26 ans ».
- L'acceptation donne un abonnement « 1 an / Étudiant » à 0 € (ligne de frais
  de -100 %), via le circuit « Attente justificatif » de `includes/orders/student-proof-status.php`.
- Chaque année : nouvelle demande, nouveau justificatif.
- Aucune modification des fichiers existants, sauf une ligne de chargement dans
  `orgues-nouvelles-plugin.php`.

## État d'avancement

| Étape | Contenu | État |
| --- | --- | --- |
| 1 | Stockage privé et chiffré des justificatifs, affichage admin, suppression | **Fait** |
| 1 | Type de contenu « Autorisation » et ses champs | **Fait** sur le site de test (Pods, sans code) |
| 2 | Action du formulaire Elementor : contrôle d'âge, fiche | À faire |
| 3 | Compte, abonnement et commande | À faire |
| 4 | Accepter / Refuser | À faire |
| 5 | Renouvellement annuel | À faire |

## Étape 1 : stockage privé des justificatifs

Fichier : `includes/gratuite/private-proof-storage.php`.

- Dossier : `wp-content/uploads/on-gratuite-prive/`, contenant un `.htaccess`
  (refus d'accès Apache, sans effet sur Nginx) et un `index.php` vide.
- **Chiffrement (protection principale, indépendante du serveur)** : chaque
  justificatif est chiffré avant d'être écrit (libsodium, `secretbox`, authentifié),
  sous un nom aléatoire de 32 caractères hexadécimaux + `.enc`. Même si le serveur
  distribuait le dossier, le fichier serait illisible sans la clé. Le nom d'origine
  n'est gardé que dans la fiche, pour l'administration.
- **Clé** : dérivée des clés secrètes du site (`wp_salt`). On peut imposer une clé
  dans `wp-config.php` avec `define( 'ON_GRATUITE_PROOF_KEY', '<64 caractères hexadécimaux>' );`
  (recommandé en production). **Si la clé change, les justificatifs déjà stockés
  deviennent illisibles** (sans gravité : ils sont supprimés au bout de 60 jours).
  Un fichier modifié est refusé à la lecture.
- Le contenu du fichier est contrôlé avant chiffrement (type réel, taille maximale 5 Mo).
- Lecture : uniquement par un administrateur connecté (`manage_woocommerce`), par un
  lien à usage contrôlé (nonce), sur l'écran de la fiche ; le fichier est déchiffré
  à la volée et n'est jamais écrit en clair sur le serveur. Chaque lecture est
  enregistrée (date et utilisateur).
- Suppression : à la décision (fonction `on_gratuite_delete_proof`, appelée aux
  étapes 3 et 4), par le lien « Supprimer maintenant », et par une tâche
  quotidienne à **60 jours au plus**. Les fichiers orphelins sont aussi supprimés.
- Aucun justificatif ni lien vers un justificatif n'est mis dans un e-mail.

### Fonctions

| Fonction | Rôle |
| --- | --- |
| `on_gratuite_store_proof( $source, $nom_origine, $post_id )` | Contrôle et range le fichier, retourne les données ou une `WP_Error` |
| `on_gratuite_delete_proof( $post_id )` | Supprime le fichier et les données, garde la date de suppression |
| `on_gratuite_proof_path( $post_id )` | Chemin du fichier chiffré, ou `false` |
| `on_gratuite_read_proof( $post_id )` | Contenu déchiffré, ou `false` (fichier absent, modifié ou clé changée) |
| `on_gratuite_purge_expired_proofs()` | Tâche quotidienne de suppression |

### Filtres

| Filtre | Défaut |
| --- | --- |
| `on_gratuite_post_type` | `on_abon_autorisation` (identifiant du type Pods, 20 caractères au maximum) |
| `on_gratuite_capability` | `manage_woocommerce` |
| `on_gratuite_retention_days` | 60 |
| `on_gratuite_proof_types` | pdf, jpg, jpeg, png |
| `on_gratuite_proof_max_bytes` | 5 Mo |

### Données enregistrées sur la fiche (méta)

`_on_gratuite_justificatif` (nom du fichier), `_on_gratuite_justificatif_nom`
(nom d'origine), `_on_gratuite_justificatif_mime`, `_on_gratuite_justificatif_date`
(horodatage), `_on_gratuite_justificatif_supprime` (horodatage de suppression),
`_on_gratuite_justificatif_acces` (une ligne par lecture : `horodatage|utilisateur`).

## À vérifier sur le site de test

1. Le serveur du site de test est **Nginx** : le `.htaccess` ne protège pas. Le chiffrement
   rend le fichier inutilisable même si son adresse est trouvée ; vérifier avec un
   fichier réel qu'une lecture directe par le web ne donne qu'un contenu illisible
   (ou un refus), jamais l'image.
2. Définir `ON_GRATUITE_PROOF_KEY` dans `wp-config.php` en production.
3. Elementor ne conserve ni le fichier ni son adresse (voir étape 2).

## Pods (hors code)

Le type de contenu et ses champs sont créés avec Pods : ils sont enregistrés dans la
**base de données** du site, pas dans le code. À refaire (ou exporter avec Pods >
Composants > Migrer : packages) sur le vrai site.

Créé le 7 novembre 2026 sur le site de test (pod n° 50542).

**Type de contenu** `on_abon_autorisation` : libellé « Autorisations gratuité », menu
« Gratuité -26 ans », stockage en méta, titre seulement (pas d'éditeur), **non public**
(pas de page publique, pas d'archive, pas de recherche), **API REST désactivée**
(données personnelles). Les droits sont ceux des articles : à restreindre si
d'autres rôles que les administrateurs peuvent accéder à l'administration.

| Champ | Type | Contenu |
| --- | --- | --- |
| `prenom`, `nom` | texte | Identité |
| `email` | e-mail | Adresse de la demande |
| `date_naissance` | date | Sert au contrôle d'âge et aux renouvellements |
| `langue` | choix | `fr` ou `en` |
| `type_demande` | choix | `premiere` ou `renouvellement` |
| `statut` | choix | `en_attente`, `acceptee` ou `refusee` |
| `age_demande` | nombre | Âge au jour de la demande |
| `client_id` | nombre | Compte client |
| `abonnement_id` | nombre | Abonnement créé |
| `commande_id` | nombre | Commande parente ou de renouvellement |
| `abonnement_existant` | oui/non | Signale un abonnement déjà en cours (suivi) |
| `newsletter` | oui/non | Consentement newsletter (indicateur seulement) |
| `date_decision` | date et heure | Acceptation ou refus |
| `note_interne` | paragraphe | Notes d'Anne-Françoise |

Les données du justificatif (`_on_gratuite_justificatif*`) ne sont pas des champs Pods :
elles sont gérées par le code (voir ci-dessus) et ne s'affichent pas en saisie libre.

## Tests

`tests/GratuitePrivateProofStorageTest.php` : rangement chiffré, refus des mauvais formats,
nom aléatoire, relecture à l'identique, fichier modifié refusé, clé changée,
remplacement, suppression, protection contre les chemins piégés, purge à 60 jours. Commande : `vendor/bin/phpunit tests/GratuitePrivateProofStorageTest.php`.
