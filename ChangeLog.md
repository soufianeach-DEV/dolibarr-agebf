# CHANGELOG AGEBF FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 4.3 (2026-06-08)

- **feat** : tri cliquable sur TOUTES les colonnes des deux pages — Paiements à effectuer (Pack, Tiers, N°facture, Date, Montant, Payé, Statut, Dernier paiement) + Rapprochement bancaire (Tiers, Facture, Montant attendu, Déjà payé, Restant, Virements, Rapprochement)
- ZIP : `module_agebf-4.3.zip`

## 4.2 (2026-06-08)

### Corrections et amelioration

- **fix** : erreur MySQL `Unknown column 'ef.beneficiaire' in 'SELECT'` sur la page Paiements a effectuer — SQL desormais defensif (SHOW COLUMNS avant le JOIN sur `llx_facture_fourn_extrafields`)
- **fix** : liens ecritures bancaires Belfius corrigees (`account=1` -> `account=2`) dans agebf_packs (x2) et agebf_compta (x1)
- **feat** : tri cliquable sur les colonnes Pack, Tiers, N° facture, Date facture et Montant dans la page Paiements a effectuer (demande Philip)
- ZIP : `module_agebf-4.2.zip`

## 4.1 (2026-06-04)

### Nouvelle fonctionnalite : preparation du lot de virements SEPA en 1 clic + parcours guide

- **Bouton « Preparer le lot de virements SEPA »** sur la page *Paiements a effectuer*. En un clic, le module cree les demandes de virement (`demande_prelevement`, API standard Dolibarr) pour toutes les factures fournisseurs impayees de l'annee affichee, puis renvoie vers l'ecran standard Dolibarr de generation du fichier SEPA.
- **Point de controle humain conserve** : le bouton *prepare* les demandes mais ne genere PAS le fichier de virement. C'est l'utilisateur qui verifie la liste, choisit le compte emetteur et la date, puis genere le fichier SEPA. Aucun paiement ne part automatiquement.
- **Fil du workflow** ajoute en haut des deux pages : « 1. Paiements a effectuer -> 2. Rapprochement bancaire », l'etape courante en surbrillance et l'autre cliquable. Les deux phases du processus sont desormais reliees explicitement dans l'ecran (et plus seulement via le menu).
- **Aucun fichier ni table standard de Dolibarr n'est modifie** : la fonctionnalite s'appuie integralement sur l'API existante `demande_prelevement()` et sur l'ecran standard `compta/prelevement/create.php`.
- ZIP : `module_agebf-4.1.zip`

## 3.10 (2026-06-04)

### Amelioration : rapprochement Belfius par reference SEPA (cle texte infaillible)

- **Rapprochement par reference SEPA** : la communication de chaque ordre collectif Belfius contient `FICHIER : DOL/AAAAMMJJ/CTxx`, qui est exactement le `<MsgId>` du fichier SEPA genere par Dolibarr. Le module stocke ce MsgId par lot et retrouve donc le bon de virement **au texte exact** (zero ambiguite), au lieu de deviner par montant + date.
- **Matching a deux niveaux** :
  1. **Sur (ref. SEPA)** — le CTxx du releve correspond a un MsgId connu -> correspondance certaine.
  2. **Repli automatique** (lots sans MsgId enregistre) — ancienne logique montant total + date (Sur / Probable / Ambigu).
- **Nouvelle table du module** `llx_agebf_lot_sepa` (fk_bon, msgid, ct_num, entity) creee automatiquement. **Aucune table standard de Dolibarr n'est modifiee** : la fonctionnalite est purement additive et se desactive sans effet sur le coeur.
- Tant que la table n'est pas alimentee (ex. juste apres installation), le rapprochement fonctionne normalement via le repli montant + date.
- ZIP : `module_agebf-3.10.zip`

## 3.9 (2026-06-03)

### Amelioration : import Belfius scenario B + clarification des menus

- **Import du releve Belfius reecrit en scenario B** : chaque ordre collectif Belfius est UNE ligne globale (montant total, sans detail). Le module retrouve le lot SEPA correspondant via la reference `FICHIER : DOL/AAAAMMJJ/CTxx`, puis rapproche en un clic tous les virements du lot. Validation sur releve Belfius reel et sur jeu de test.
- **Correction d'un bug fatal PHP 8** : une variable locale `$conf` (niveau de confiance) ecrasait l'objet de configuration global Dolibarr, provoquant un `count(): null given` dans le footer. Variable renommee en `$confiance`.
- **Menus renommes** pour refleter les deux phases du workflow :
  - « Suivi des packs » -> **« Paiements a effectuer »** (preparer/suivre les versements)
  - « Suivi des paiements » -> **« Rapprochement bancaire »** (pointer le releve Belfius)
- ZIP : `module_agebf-3.9.zip`

## 3.8 (2026-06-03)

### Amelioration : Suivi des paiements SEPA — rapprochement par facture

- Vue **regroupee par facture** : 1 ligne par facture, ses virements deplies au clic (au lieu d'1 ligne par virement)
- **Detail des packs** visible par facture (badges colores)
- **Distinction claire** entre l'etat de paiement (Restant : vert si soldee, rouge sinon) et l'etat de pointage bancaire (Rapprochement)
- **Pointage manuel** inline : saisie du n° de releve + bouton Rapprocher directement sur chaque virement, le detail reste ouvert apres action
- **Import automatique du releve Belfius (CSV)** : analyse du releve, correspondance automatique avec les virements et niveau de confiance (Sur / Probable / Ambigu), validation/rapprochement en un clic
- Reference SEPA cliquable (ouvre l'ecriture bancaire), bouton annuler le pointage (admin)
- Rapprochement base sur `llx_paiementfourn.fk_bank` (au lieu de `llx_bank_url`)
- Scripts de generation de donnees de test : `gen_test_data.php`, `gen_sepa_lots.php` (lots SEPA + XML pain.001), `gen_releve_belfius.php` (releve CSV)
- ZIP : `module_agebf-3.8.zip`

## 3.7 (2026-06-02)

### Nouvelle fonctionnalite : Suivi des paiements SEPA

- Nouvelle page **Suivi des paiements** (menu Helpy → Suivi des paiements)
- Resout le probleme identifie par Philip : dans Dolibarr, un virement SEPA regroupe plusieurs factures mais on ne voyait pas quel pack (lunettes, sport, sante...) etait inclus dans quel virement
- La page part des **virements SEPA** et decompose chacun en detail :
  - Tableau principal : 1 ligne par virement — date, reference SEPA, releve bancaire Belfius (cliquable), statut rapproche, nb factures, montant total
  - **Detail expandable** (bouton triangle) : Tiers + Pack (badge colore) + N° facture + Montant paye pour chaque facture du virement
  - Bandeau stats : nb virements, rapproches/non-rapproches, montants
  - Filtres : annee, statut rapproche (Tous/Oui/Non), recherche par reference SEPA
  - Export CSV : 1 ligne par facture avec tous les champs (Date, Ref SEPA, Releve, Rapproche, Tiers, Pack, Facture, Montant)
  - Liens directs : fiche paiement fournisseur, ecritures bancaires Belfius, fiche facture, fiche Tiers
- ZIP : `module_agebf-3.7.zip`

## 3.6 (2026-06-02)

- Page **Documents** : renommage de fichier desormais disponible pour les admins sur **tous les documents** (Fournie + Manquante), pas seulement les lignes "Manquante"
- Permet a la secretaire d'harmoniser les noms de fichiers meme quand la composition de menage est deja detectee
- ZIP : `module_agebf-3.6.zip`

## 3.5 (2026-05-25)

- Nouvelle page **Suivi des packs** (menu Helpy → Suivi des packs)
  - Liste toutes les factures fournisseurs (packs avantages sociaux) par annee
  - Filtres : annee, statut (Soldee/Partielle/Impayee), pack, tiers, N° facture, communication structuree, montant min/max
  - Badges colores par type de pack
  - Colonne Virement : bouton Preparer virement + lien vers ecritures bancaires
  - Export CSV complet
- ZIP : `module_agebf-3.5.zip`

## 3.2 (2026-05-20)

- Module renomme **Helpy** dans la liste des modules (Configuration → Modules/Applications)
- Menu gauche renomme **Documents Tiers** (plus explicite)
- Indicateurs texte sur la page Documents : **Fournie** / **Manquante** / **Aucun document**
- Filtre Documents corrige : 3 boutons (Tous / Avec composition / Sans composition)
- Visualisation des fichiers directement dans un **popup modal** (80% de l'ecran)
- Renommage de fichier inline pour les admins sur les lignes "Manquante"
- Bouton **Voir** disponible sur tous les Tiers ayant des documents
- ZIP : `module_agebf-3.2.zip`

## 3.1 (2026-05-20)

- Nouvelle page **Documents** (menu Helpy → Documents)
  - Liste tous les Tiers avec le nombre de documents joints
  - Detection de la composition de menage (nom de fichier contenant "compos" ou "menage")
  - Indicateurs visuels OK / Manquant par Tiers
  - Filtre "Sans composition seulement"
  - Lien direct vers la page Documents de chaque Tiers
- Version module mise a jour en 3.1 (refresh general Dolibarr)
- ZIP : `module_agebf-3.1.zip`

## 3.0 (2026-05-18)

- Menu haut renomme **Helpy**
- Icones Font Awesome : fa-child, fa-birthday-cake
- Colonne Parent (Tiers) en premiere position
- Tri alphabetique cliquable sur toutes les colonnes
- ZIP format compatible Dolibarr : module_*-x.y*.zip

## 2.0 (2026-05-12)

### Refonte complète du modèle de données

- Nouveau modèle : **1 Tiers par employé adulte** (27 Tiers) + contacts Fils/Fille liés via `fk_soc`
- Le lien parent→enfant est désormais natif Dolibarr (`fk_soc`) — plus fiable qu'un champ custom `fk_parent`
- Abandon du modèle précédent (1 seul Tiers « Bruxelles Formation » + 58 contacts)

### Nouveaux champs extrafields

- `fete_enfants` (boolean) sur les **contacts** — coché si Fils/Fille < 16 ans
- `fete_enfants` (boolean) sur les **Tiers** — coché si au moins 1 enfant du Tiers qualifie
- `nb_enfants_invites` (int) sur les **Tiers** — nombre d'enfants < 16 ans liés au Tiers

### Changements métier

- Critère d'invitation : âge **strictement < 16** au 1er janvier (les 15 ans sont inclus)
- Poste renommé `'Fils'` / `'Fille'` (remplace `'Enfant'`)
- Calcul de l'âge étendu à **tous les contacts** ayant une date de naissance (pas seulement Fils/Fille)

### Interface

- Page principale : filtre "Invités seulement" / "Tous les enfants"
- **Export CSV** (UTF-8 + BOM Excel) des enfants invités
- Boutons d'action déplacés en **haut de page**
- Colonne "Parent (Tiers)" avec lien direct vers la fiche Tiers
- Colonne "Fonction" affichant `note_public` du Tiers
- Bandeau de statistiques (total, < 16 ans, cases cochées)

### Technique

- Propagation automatique `fete_enfants` et `nb_enfants_invites` vers le Tiers dans le cron
- Suppression de la propagation `fk_parent` (remplacée par `fk_soc`)
- Suppression du menu de navigation principale (moins encombrant)
- Cron quotidien à minuit configurable via Windows Task Scheduler ou crontab Linux

## 1.0 (2026-05-01)

Initial version — calcul âge contacts `poste='Enfant'` au 1er janvier, affichage liste ✔/✘.
