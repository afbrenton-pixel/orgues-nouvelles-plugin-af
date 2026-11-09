# Gratuité -26 ans : abonnement Étudiant gratuit

Documentation du module `includes/gratuite/`. Branche : `gratuite-26`.
Spécification complète : plan technique du 7 novembre 2026 (Anne-Françoise).

## Principe

- Une personne qui n'a pas encore 27 ans (même règle pour la première demande et pour chaque renouvellement) remplit un
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
| 2 | Action du formulaire Elementor : contrôle d'âge, fiche, justificatif chiffré | **Fait et testé de bout en bout** sur le site de test (7 novembre) |
| 2b | Confirmation de l'adresse e-mail (lien, statut « à confirmer », purge manuelle) | **Code fait** (7 novembre) ; test à faire |
| 3 | Compte, abonnement et commande **à l'acceptation** (le compte n'est plus créé à la demande) | À faire |
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

## Étape 2 : formulaire Elementor et action « Gratuité -26 ans (autorisation) »

Fichiers : `includes/gratuite/` : `settings.php`, `request-rules.php`, `authorization.php`,
`form-action.php`, `class-form-action.php`.

### Le formulaire (dans Elementor, sans code court)

Widget « Formulaire » d'Elementor Pro, dans la page FR et dans la page EN. **Identifiants
des champs** (onglet Avancé de chaque champ), à respecter exactement :

| Identifiant | Type de champ | Remarque |
| --- | --- | --- |
| `prenom`, `nom` | Texte | Obligatoires |
| `email` | E-mail | Obligatoire |
| `date_naissance` | Date | Obligatoire ; format AAAA-MM-JJ (champ date natif) |
| `justificatif` | Envoi de fichier | **1 fichier**, formats pdf, jpg, jpeg, png, 5 Mo ; obligatoire |
| `newsletter` | Case à cocher | Facultatif ; seul l'indicateur est enregistré |
| `pour_autre` | Acceptation | Facultatif : « Je fais cette demande pour une autre personne » (voir « Demande pour une autre personne ») |
| `consentement` | Acceptation | Obligatoire (traitement des données, durée de conservation) |
| `langue` | Champ caché | Valeur `fr` ou `en` (une valeur par page) |

Dans « Actions après soumission », choisir **uniquement** « Gratuité -26 ans (autorisation) »
(et « Redirection » si besoin). Les autres actions (E-mail, Collecter les envois, Webhook,
etc.) sont **retirées automatiquement** : elles copieraient des données personnelles ou
l'adresse du justificatif. Ajouter le champ anti-robot d'Elementor (piège invisible ou reCAPTCHA).
Style : réglages globaux d'Elementor uniquement (règle 14).

### Ce que fait l'action

1. **Avant l'envoi du fichier** (validation) : contrôle de la date de naissance (voir règles),
   présence et format du justificatif, adresse déjà connue, demande déjà en cours, limite
   de 5 demandes par heure et par connexion (seul un hachage de l'adresse IP est gardé 1 h).
   Les erreurs s'affichent sous le champ concerné, en français ou en anglais selon `langue`.
   Si l'une échoue, **aucun fichier n'est enregistré**.
2. **À l'envoi** : création de la fiche (statut « **à confirmer** »), déplacement du justificatif
   dans le stockage privé chiffré, suppression du fichier de la partie publique
   d'Elementor (y compris en cas d'erreur), puis **e-mail de confirmation à la personne**
   (voir étape 2b). Une nouvelle demande de la même adresse remplace (efface) une demande
   non confirmée précédente. L'équipe n'est prévenue qu'après la confirmation.

### Demande pour une autre personne (décidé le 7 novembre, option B)

Le site propose déjà d'offrir un abonnement (extension WooCommerce Subscriptions Gifting, destinataire
`_recipient_user`). La gratuité suit la même logique :

- **Pour soi-même** : la personne connectée est le bénéficiaire ; l'adresse de son compte est toujours
  utilisée. Une adresse différente tapée dans le formulaire est **refusée** avec un message (jamais ignorée
  sans le dire). Une personne non connectée dont l'adresse a déjà un compte doit d'abord se connecter.
- **Pour une autre personne** (case `pour_autre`) : il faut être **connecté** ; l'adresse, la date de naissance,
  le nom et le justificatif sont ceux du **bénéficiaire**. Son adresse doit différer de celle du compte
  du demandeur. Le bénéficiaire peut déjà avoir un compte (comme pour un cadeau). L'e-mail de confirmation
  part chez le bénéficiaire (« une demande a été faite pour vous par un utilisateur de notre site »).
- La fiche garde le bénéficiaire (`client_id`, `email`) et le demandeur (`demandeur_id`) ; la notification à
  l'équipe indique le demandeur. **Étape 3** : l'abonnement est un abonnement cadeau (acheteur = demandeur,
  destinataire = bénéficiaire, compte créé s'il n'existe pas) ; les renouvellements suivent le bénéficiaire.
- L'âge est contrôlé sur la date de naissance du bénéficiaire (décidé le 9 novembre : la première demande et chaque renouvellement sont possibles jusqu'au 27e anniversaire, réglages `age_first` et `age_renew` = 27).
- Texte du formulaire : les champs prénom, nom, e-mail et date de naissance sont ceux de la personne concernée.

### Règles appliquées

- Première demande : moins de **26 ans** à la date de naissance exacte (le jour du 26e anniversaire est refusé).
- Renouvellement (le client connecté a déjà une autorisation acceptée) : moins de **27 ans**.
- Une adresse qui a déjà un compte : la personne doit d'abord se connecter (message), puis refaire sa demande.
- Une demande **confirmée et en attente de décision** pour la même adresse empêche d'en déposer une autre (une demande seulement « à confirmer » est remplacée).
- **Pas encore faits** : création du compte, de l'abonnement et de la commande (étape 3),
  boutons Accepter / Refuser (étape 4), fenêtre des 60 jours avant la fin (étape 5).

### Réglages : provisoirement en dur

`includes/gratuite/settings.php` (fonction `on_gratuite_settings()`) : 26 ans, 27 ans,
60 jours, variations 257 et 30365 (identifiants du site de test), 100 %, libellé des frais,
adresse de notification etudiant@orgues-nouvelles.org, 5 demandes par heure. Décision du
7 novembre : l'opération n'est pas destinée à être permanente, donc pas de page de réglages
pour l'instant ; elle pourra remplacer cette fonction plus tard sans toucher au reste.

### Données enregistrées sur la fiche

Champs Pods : `prenom`, `nom`, `email`, `date_naissance`, `langue`, `type_demande`,
`statut` (`en_attente`), `age_demande`, `client_id` (0 si la personne n'a pas encore de compte),
`newsletter`.

### Tests

`tests/GratuiteRequestRulesTest.php` : âges (veille et jour du 26e anniversaire, renouvellement
à 26 et 27 ans), dates invalides, compte existant, demande en attente, messages FR et EN,
réglages, contrôle du fichier, actions retirées.
Commande : `vendor/bin/phpunit tests/GratuiteRequestRulesTest.php`.

## Étape 2b : confirmation de l'adresse e-mail

Fichiers : `includes/gratuite/confirmation.php` (règles et envoi, testés dans
`tests/GratuiteConfirmationTest.php`) et `includes/gratuite/confirmation-admin.php`
(liste d'administration). Décision du 7 novembre : une adresse fantaisiste ne doit pas
laisser de compte ni de justificatif.

- **Statuts** : `a_confirmer` (à la demande) → `en_attente` (après le clic) → `acceptee` ou `refusee`.
- **E-mail à la personne** (texte brut, français ou anglais selon `langue`) : lien
  `/?on_gratuite_confirm=<n° de fiche>&k=<jeton>` valable **7 jours** (réglage `confirm_days`).
  Il ne mentionne ni ne joint le justificatif.
- **Jeton** : 32 caractères aléatoires, jamais conservés : seule une empreinte (HMAC) est
  enregistrée (`_on_gratuite_token_hash`), effacée à la confirmation. Date d'envoi :
  `_on_gratuite_created` ; date de confirmation : `_on_gratuite_confirmed_at`.
- **Clic** : une page simple confirme (ou dit : déjà confirmée, lien expiré, lien non valable). La fiche passe
  en `en_attente` et **l'équipe est alors notifiée** (adresse `etudiant@…`).
  Un robot de messagerie qui suit le lien confirme l'adresse à la place de la personne :
  accepté (le justificatif reste contrôlé à la main).
- **Liste « Gratuité -26 ans »** : les demandes à confirmer sont **cachées de la liste normale** et visibles sous
  le filtre « À confirmer (n) ». Action de ligne **« Annuler la demande »** (supprime la fiche
  et son justificatif). Lien **« Supprimer les demandes non confirmées de plus de 7 jours (n) »**
  (purge manuelle, avec confirmation).
- **Purge automatique** : désactivée (`auto_purge_unconfirmed` = faux). Si on l'active, une tâche
  quotidienne WordPress (`on_gratuite_purge_unconfirmed_event`) fait la même chose.
  **À vérifier avec le webmaster** : le site utilise-t-il la tâche planifiée de WordPress
  (dépend des visites) ou une vraie tâche du serveur ? La purge des justificatifs à 60 jours
  (étape 1) a la même dépendance.
- **Limite connue** : une personne peut déclencher l'envoi d'e-mails de confirmation à une adresse qui
  n'est pas la sienne (5 demandes par heure et par connexion au maximum).
- **Page du formulaire** : le message de succès doit dire de cliquer sur le lien reçu par e-mail.

### E-mails au format du site (étape 2c, décidé le 7 novembre)

Fichiers : `includes/gratuite/emails.php`, `class-emails.php`, `templates/emails/gratuite-*.php` (et `plain/`).
Même mécanisme que l'e-mail « Demande de justificatif étudiant » du webmaster : classes d'e-mail WooCommerce,
donc logo, couleurs et pied de page du site, version texte brute de secours.

- **`ON_Gratuite_Email_Confirmation`** (au bénéficiaire) et **`ON_Gratuite_Email_Notification`** (à l'équipe, adresse réglable).
  Les deux apparaissent dans **WooCommerce > Réglages > E-mails** : activer/désactiver, objet, titre, texte.
- **Langues** : objet, titre et texte existent en français et en anglais (champs séparés), avec une variante
  de texte pour « demande pour une autre personne ». Espaces réservés : `{first_name}`, `{link}`, `{days}`.
  Les textes par défaut viennent de `on_gratuite_confirmation_texts()`.
- **Repli** : si le système d'e-mails de WooCommerce n'est pas disponible, les e-mails partent en texte brut (comme avant).
- Si l'e-mail de confirmation est **désactivé** dans WooCommerce, la demande échoue avec le message technique
  (personne ne pourrait confirmer) : ne pas le désactiver.
- Toujours : jamais de justificatif joint ni cité ; la notification à l'équipe ne contient pas de lien vers le fichier.
- Les e-mails d'acceptation et de refus (étape 4) suivront le même modèle.

## Étape 3 : compte, abonnement et commande (à l'acceptation)

`includes/gratuite/subscription.php` (création), `subscription-rules.php` (règles pures, testées),
`subscription-admin.php` (boîte « Compte et abonnement » de la fiche).

`on_gratuite_provision( $fiche_id )` ne s'exécute que pour une fiche **« en attente »** (adresse confirmée)
et une seule fois (refus si `abonnement_id` est déjà rempli). Elle crée :

1. **Le compte** du bénéficiaire s'il n'existe pas : `wc_create_new_customer()` avec mot de passe généré par WooCommerce (identifiant unique
   tiré de l'adresse, e-mail « nouveau compte » avec lien pour choisir son mot de passe, personne invitée à compléter ses informations
   à la première connexion), puis prénom/nom ; l'e-mail est écrit dans la langue de la demande. S'il existe déjà (même adresse), il est réutilisé.
2. **La commande parente** : variation FR (257) ou EN (30365) selon la langue de la demande, ligne de frais
   négative « Gratuité -26 ans » (total 0 €), statut demandé `pending`, ce que le module du webmaster
   (`student-proof-status.php`) transforme en **« Attente justificatif »**. La commande est marquée « justificatif requis »
   par le code, sans dépendre de la variation (sur le site de test la variation EN 30365 porte `_demande_justificatif = no` :
   à signaler au webmaster, l'abonnement Étudiant payant en anglais ne demande donc pas de justificatif).
   Aucun e-mail de demande de justificatif n'est envoyé (l'équipe a déjà le justificatif).
3. **L'abonnement** : mêmes lignes, période 1 an, **aucun prochain paiement**, fin à +1 an, statut `pending`
   jusqu'à l'étape 4. Numéros de début/fin et formule : fonction du webmaster `on_initialize_subscription_number_bounds()`.
4. **Demande pour une autre personne** : acheteur = demandeur, destinataire = bénéficiaire via
   `WCS_Gifting::set_recipient_user()` (donnée `_recipient_user`, lue par le code du webmaster). Pour soi-même,
   le client de l'abonnement est le bénéficiaire.

La fiche reçoit `client_id`, `abonnement_id`, `commande_id`, et `_on_gratuite_doublons` : comptes de même
prénom+nom, fiches de même date de naissance (affichés, **jamais bloquants**).

La création est déclenchée par le bouton **Accepter** de la fiche (étape 4).

**Première connexion** : la page native des cadeaux (`/mon-compte/new-recipient-account/`) demande prénom, nom, pays et adresse. Prénom et nom sont **préremplis** par notre code (filtre `wcsg_new_recipient_account_details_fields`) ; le filtre `wcsg_need_shipping_address_details_for_recipient` permettrait de supprimer la demande d'adresse (non fait, à décider).

Vérifié dans le code de WooCommerce Subscriptions 9.0 (7 novembre) : définir un destinataire ne crée pas son compte
(d'où l'appel explicite) ; `is_gifted_subscription` se fonde sur `_recipient_user`.

## Étape 4 : Accepter / Refuser

`decision.php` (actions), `decision-rules.php` (règles et textes, testés), boîte « Compte et abonnement » de la fiche
(`subscription-admin.php`). Seule une demande **« en attente »** (adresse confirmée) peut être décidée.

**Accepter** (`on_gratuite_accept`) : crée compte, abonnement et commande si besoin (étape 3) ; valide le justificatif
avec `on_student_proof_approve()` du webmaster ; paie la commande à 0 € et la termine ; fixe début = aujourd'hui,
fin = +1 an, aucun prochain paiement, recalcule les numéros de début/fin ; **retire la ligne « Gratuité -26 ans »
de l'abonnement** (elle reste sur la commande) ; active l'abonnement ; supprime le justificatif ; fiche « acceptée »
(`date_decision`, `_on_gratuite_decide_par`) ; e-mail d'acceptation. Les e-mails WooCommerce habituels de la commande
(traitement, terminée, nouvelle commande) sont coupés pendant l'opération : l'e-mail d'acceptation les remplace.

**Refuser** (`on_gratuite_refuse`) : motif facultatif (transmis à la personne, gardé dans `_on_gratuite_motif`) ;
supprime le justificatif ; fiche « refusée » ; e-mail de refus. Rien n'est créé. La personne peut redéposer une demande
**tout de suite** (décision du 7 novembre). Si un abonnement et une commande avaient été préparés, ils sont annulés (jamais supprimés).

**E-mails** (WooCommerce > Réglages > E-mails, activés, objet/titre/texte modifiables en français et en anglais) :
« Gratuité -26 ans : demande acceptée » et « … demande refusée ». Espaces réservés : `{first_name}`, `{end_date}`,
`{account_url}`, `{form_url}`, `{contact}`, `{reason}`. Envoyés à la personne bénéficiaire (adresse de la demande).
Dans une demande pour une autre personne, le **demandeur reçoit une copie** (décidé le 7 novembre), avec un **texte court et propre à la copie** (décidé le 9 novembre) : la décision, le nom et l'adresse de la personne, la date de fin de l'abonnement (acceptation) ou le motif (refus). Rien sur la lecture en ligne, « Mon compte » ou le renouvellement, puisque ce n'est pas son abonnement. Textes dans `on_gratuite_copy_texts()` (`decision-rules.php`), fixes (non modifiables dans WooCommerce).

Réglage ajouté : `form_page_id` (49840), page du formulaire citée dans l'e-mail de refus (traduction trouvée par Polylang).

## À vérifier sur le site de test

1. Le serveur du site de test est **Nginx** : le `.htaccess` ne protège pas. Le chiffrement
   rend le fichier inutilisable même si son adresse est trouvée ; vérifier avec un
   fichier réel qu'une lecture directe par le web ne donne qu'un contenu illisible
   (ou un refus), jamais l'image.
2. Définir `ON_GRATUITE_PROOF_KEY` dans `wp-config.php` en production.
3. Elementor ne conserve ni le fichier ni son adresse (voir étape 2) : vérifier le dossier `wp-content/uploads/elementor/forms/` et la page des envois après un essai.

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
| `statut` | choix | `a_confirmer`, `en_attente`, `acceptee` ou `refusee` |
| `age_demande` | nombre | Âge au jour de la demande |
| `client_id` | nombre | Compte du **bénéficiaire** (0 s'il n'en a pas encore) |
| `demandeur_id` | nombre | Compte de la personne qui a rempli le formulaire (0 si non connectée) |
| `pour_autre` | oui/non | Demande faite pour une autre personne |
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
