# Module AgeBF — Dolibarr ERP CRM

> Module custom développé dans le cadre d'un stage chez **Bruxelles Formation**.

**Auteur :** Soufiane Achraa — Stage 2026 — TECHGEST ICCBXL  
**Version :** 4.1  
**Compatibilité :** Dolibarr 19+, PHP 8.0+

---

## Objectif

Quatre fonctionnalités regroupées sous le menu **Helpy** :

1. Calculer automatiquement l'âge des contacts **Fils / Fille** au 1er janvier, déterminer lesquels sont invités à la **fête des enfants** de Bruxelles Formation
2. Suivre les **documents** (composition de ménage) fournis par chaque Tiers
3. Préparer les **paiements à effectuer** (factures fournisseurs des packs ASBL, virements, paiements en 3 versements)
4. Réaliser le **rapprochement bancaire** des paiements SEPA : décomposer chaque virement par facture (Tiers + Pack + Montant) et pointer le relevé Belfius (import CSV scénario B ou pointage manuel)

**Règle métier — Fête des enfants :** un enfant est invité s'il a strictement moins de 16 ans au 1er janvier (les 15 ans sont inclus).

---

## Modules Dolibarr requis

Ces modules doivent être activés dans **Configuration → Modules/Applications** avant d'utiliser Helpy.

| Module | Obligatoire pour | Chemin d'activation |
|---|---|---|
| **Tiers** | Toutes les pages | Activé par défaut |
| **Contacts** | Fête des enfants | Activé par défaut |
| **Fournisseurs** | Paiements à effectuer | Configuration → Modules → Achats |
| **Banques et Caisses** | Paiements à effectuer + Rapprochement bancaire | Configuration → Modules → Finance |
| **Produits / Services** | Paiements à effectuer (packs) | Configuration → Modules → Produits |
| **GED (Documents)** | Documents Tiers | Configuration → Modules → Outils |
| **Travaux planifiés** | Cron calcul des âges | Configuration → Modules → Outils |

### Permissions utilisateur requises

Dans **Accueil → Utilisateurs → [utilisateur] → Permissions** :

| Section | Permission |
|---|---|
| Fournisseurs | Lire les factures (et paiements) fournisseurs |
| Fournisseurs | Créer les factures fournisseur |
| Banques et caisses | Consulter les comptes financiers |
| Banques et caisses | Créer/modifier montant/supprimer écritures bancaires (nécessaire pour le rapprochement) |

---

## Modèle de données

Le module repose sur le modèle natif Dolibarr :

- **1 Tiers par employé adulte** (ex. : "Dupont Marie") — contient les champs `fete_enfants` et `nb_enfants_invites`
- **Contacts Fils/Fille** liés au Tiers du parent via le champ standard `fk_soc`
- **Factures fournisseurs** (`llx_facture_fourn`) liées au Tiers — une facture par pack souscrit
- **Paiements fournisseurs** (`llx_paiementfourn`) — jusqu'à 3 versements par facture

---

## Fonctionnement

### Fête des enfants

1. Le cron calcule chaque jour l'âge de tous les contacts ayant une date de naissance
2. Les contacts avec `poste = 'Fils'` ou `poste = 'Fille'` et âge < 16 reçoivent la case **"Fête des enfants" cochée**
3. Le Tiers parent reçoit automatiquement `fete_enfants = 1` et `nb_enfants_invites = N`

### Paiements à effectuer

1. L'ASBL crée une **facture fournisseur** (`S02605-XXXX`) pour chaque pack souscrit par un employé
2. Le Tiers rembourse l'ASBL en **3 versements** — chaque versement est enregistré dans `llx_paiementfourn`
3. La page affiche en temps réel : montant payé, montant restant, statut (Soldée / Partielle / Impayée)
4. Le bouton **Préparer virement** ouvre la fiche facture fournisseur Dolibarr pour enregistrer un paiement
5. Le lien **Écritures** filtre la liste des écritures bancaires sur la référence de la facture

### Rapprochement bancaire — import Belfius (scénario B)

Le relevé Belfius présente chaque ordre collectif SEPA comme **une seule ligne globale** (montant total, sans détail des bénéficiaires). L'import CSV applique ce « scénario B » :

1. Le module lit la communication de chaque ligne et y retrouve la référence du lot `FICHIER : DOL/AAAAMMJJ/CTxx` — qui est exactement le `<MsgId>` du fichier SEPA généré par Dolibarr.
2. Il rapproche la ligne au lot SEPA correspondant en **deux niveaux** et affiche le **niveau de confiance** :
   - **Sûr (réf. SEPA)** — le `CTxx` du relevé correspond à un `MsgId` enregistré dans la table `llx_agebf_lot_sepa` → correspondance **exacte au texte**, aucune ambiguïté possible.
   - **Sûr (montant + date)** — repli : un seul lot correspond par montant total *et* date proche.
   - **Probable** — repli : un seul lot par montant mais date éloignée, ou plusieurs lots dont la date en isole un.
   - **Ambigu** — repli : plusieurs lots au même montant et la date ne tranche pas.
   - **Aucun lot** — aucune correspondance.
3. En un clic, tous les virements du lot sélectionné passent en `rappro = 1` avec le N° de relevé Belfius.

> La référence SEPA (`MsgId`) est stockée dans une table **propre au module** (`llx_agebf_lot_sepa`), créée automatiquement. Aucune table standard de Dolibarr n'est modifiée. Tant que cette table n'est pas alimentée, le rapprochement fonctionne via le repli montant + date.

---

## Champs créés automatiquement à l'activation

| Champ | Table | Type | Description |
|---|---|---|---|
| `age_1jan` | contacts | Int | Âge calculé au 1er janvier |
| `fete_enfants` | contacts | Boolean | Coché si l'enfant (Fils/Fille) a < 16 ans |
| `fk_parent` | contacts | Int | ID contact parent (champ informatif) |
| `fete_enfants` | tiers | Boolean | Coché si au moins un enfant du Tiers est invité |
| `nb_enfants_invites` | tiers | Int | Nombre d'enfants < 16 ans liés au Tiers |

### Champs extrafields sur les factures fournisseurs

Ces colonnes sont à ajouter manuellement (ou via le seed de données de test) :

```sql
ALTER TABLE llx_facture_fourn_extrafields
    ADD COLUMN beneficiaire VARCHAR(255) DEFAULT NULL,
    ADD COLUMN communication_structuree VARCHAR(50) DEFAULT NULL;
```

---

## Structure du module

```
agebf/
├── agebfindex.php                    # Fete des enfants : liste, stats, filtre, export CSV
├── agebf_documents.php               # Documents : composition de menage par Tiers
├── agebf_packs.php                   # Paiements a effectuer : factures fournisseurs par pack et statut
├── agebf_compta.php                  # Rapprochement bancaire : virements SEPA, detail packs, import Belfius
├── core/
│   └── modules/
│       └── modAgeBF.class.php        # Descripteur v4.1 (cron, extrafields, menus)
├── class/
│   └── agebf.class.php               # Logique de calcul + propagation vers Tiers
├── admin/
│   ├── setup.php                     # Page de configuration
│   └── about.php                     # A propos
├── sql/
│   └── dolibarr_allversions.sql      # Script SQL charge a l'activation
├── langs/
│   └── en_US/
│       └── agebf.lang                # Traductions
└── lib/
    └── agebf.lib.php                 # Fonctions utilitaires
```

---

## Installation

### Méthode 1 — ZIP via l'interface Dolibarr (recommandée)

1. Télécharger **`module_agebf-4.1.zip`** depuis la [page Releases](https://github.com/soufianeach-DEV/dolibarr-agebf/releases)
2. Dans Dolibarr : **Configuration → Modules/Applications**
3. Cliquer sur l'onglet **"Déployer/Installer un module externe"**
4. Choisir le fichier ZIP → **Envoyer le fichier**
5. Le module apparaît dans la liste → **Activer**

> Le fichier ZIP respecte la convention Dolibarr : `module_*-x.y*.zip`

### Méthode 2 — Installation manuelle

```
htdocs/custom/agebf/     ← coller ici le dossier du repo
```

Puis activer via **Configuration → Modules/Applications → Rechercher "AgeBF" → Activer**.

### À l'activation

Les 5 champs extrafields sont créés automatiquement, ainsi que le cron dans **Outils → Travaux planifiés** et les menus **Helpy**.

> **Mise à jour :** désactivez puis réactivez le module pour appliquer la nouvelle version (menus, champs).

### Après installation — étapes supplémentaires pour les Paiements à effectuer

1. Activer les modules **Fournisseurs** et **Banques et Caisses**
2. Ajouter les colonnes extrafields sur les factures fournisseurs (voir SQL ci-dessus)
3. Configurer les permissions utilisateur (voir tableau Permissions ci-dessus)

---

## Utilisation

### Menu Helpy

Après activation, le menu **Helpy** apparaît dans la barre du haut avec quatre sous-pages :

| Page | Accès | Description |
|---|---|---|
| Fête des enfants | Helpy → Fête des enfants | Liste des enfants invités, stats, export CSV |
| Documents Tiers | Helpy → Documents Tiers | Suivi des compositions de ménage par Tiers |
| Paiements à effectuer | Helpy → Paiements à effectuer | Préparer/suivre les factures fournisseurs et versements des packs ASBL |
| Rapprochement bancaire | Helpy → Rapprochement bancaire | Virements SEPA décomposés par facture + pointage du relevé Belfius (import CSV ou manuel) |

### Page Fête des enfants

| Élément | Description |
|---|---|
| Bandeau stats | Total enfants, nombre < 16 ans, cases cochées |
| Filtre | "Invités seulement" / "Tous les enfants" |
| Tableau | Tiers (parent), Nom, Prénom, Genre, Âge, Case fête — tri cliquable |
| Export CSV | Télécharge la liste en `.csv` (UTF-8 + BOM Excel, nom dynamique par année) |

### Page Documents Tiers

| Élément | Description |
|---|---|
| Bandeau stats | Total Tiers / Composition fournie / Composition manquante / Aucun document |
| Filtre | 3 boutons : Tous / Avec composition / Sans composition |
| Tableau | Tiers, nb documents, statut composition, lien fiche |
| Indicateurs | **Fournie** (vert) / **Manquante** (orange) / **Aucun document** (rouge) |
| Bouton Voir | Ouvre le fichier dans un popup modal (80 % de l'écran) |
| Renommage admin | Un admin peut renommer tout fichier inline — sur les lignes **Fournie** (harmonisation) et **Manquante** (correction détection) |
| Ajout document | Sur les Tiers sans aucun fichier : formulaire d'upload intégré dans le tableau |
| Détection | Fichier contenant "composition", "compostion" ou "ménage" dans son nom |
| Sync Dolibarr | Tout ajout / renommage met à jour `llx_ecm_files` (onglet Documents natif) |

### Page Paiements à effectuer

| Élément | Description |
|---|---|
| Bandeau stats | Total / Soldées / Partielles / Impayées / Montant attendu vs reçu vs reste |
| Filtre | Par année + par statut de paiement |
| Filtres colonne | Pack (dropdown), Tiers/Bénéficiaire, N° facture, OGM, Montant min/max |
| Tableau | Pack (badge couleur) / Tiers + bénéficiaire / N° facture / OGM / Date / Montant / Payé / Statut / Dernier paiement / Virement |
| Statuts | **Soldée** (3/3) / **Partielle** (1/3 ou 2/3) / **Impayée** (aucun versement) |
| Fil du workflow | Bandeau cliquable en haut : **1. Paiements à effectuer → 2. Rapprochement bancaire** (étape courante en surbrillance) |
| **Bouton Préparer le lot de virements SEPA** | Crée en 1 clic les demandes de virement (API standard `demande_prelevement`) pour toutes les factures impayées de l'année, puis renvoie vers l'écran standard Dolibarr de génération du fichier SEPA. **Ne génère pas le fichier** : la validation et la génération restent un geste humain |
| Bouton Voir | Ouvre la fiche facture fournisseur dans un modal (80 % de l'écran) |
| Bouton Préparer virement | Ouvre la fiche facture fournisseur Dolibarr pour enregistrer un paiement |
| Lien Écritures | Filtre la liste des écritures bancaires Belfius par référence de facture |
| OGM | Communication structurée belge `+++NNN/NNNN/NNNNN+++` (police monospace, taille 1.2em) |
| Export CSV | Télécharge la liste filtrée en `.csv` (UTF-8 + BOM Excel) |

### Page Rapprochement bancaire

Résout le problème SEPA : un virement bancaire regroupe plusieurs factures mais Dolibarr natif n'affiche pas le détail des packs inclus. Cette page décompose chaque virement par facture et permet le pointage du relevé Belfius directement depuis Helpy.

| Élément | Description |
|---|---|
| Fil du workflow | Bandeau cliquable en haut : **1. Paiements à effectuer → 2. Rapprochement bancaire** (étape courante en surbrillance) |
| Bandeau stats | Nb virements / Rapprochés / Non rapprochés / Montants total, rapproché, non rapproché |
| Filtres | Année, statut rapproché (Tous / Oui / Non), recherche par référence SEPA |
| Vue regroupée par facture | 1 ligne par facture, ses virements dépliés au clic — relevé Belfius (cliquable), rapproché ✅/❌, montant |
| Détail expandable ▶ | Tiers + Pack (badge coloré) + N° facture + Montant payé pour chaque virement |
| **Import Belfius (CSV)** | Charge le relevé scénario B, retrouve le lot par **référence SEPA** (`MsgId` = `FICHIER : DOL/AAAAMMJJ/CTxx`) ou, à défaut, par montant + date ; affiche le niveau de confiance (Sûr réf. SEPA / Sûr montant+date / Probable / Ambigu / Aucun lot), rapproche tout le lot en un clic |
| **Rapprochement inline** | Sur chaque virement non rapproché : saisir le N° relevé Belfius (ex: `2026/0003`) + cliquer **Rapprocher** → `llx_bank.rappro = 1` |
| Annulation (admin) | Bouton **Ann.** sur les lignes déjà rapprochées — accessible aux admins uniquement, avec confirmation |
| Liens directs | Fiche paiement fournisseur, écritures bancaires Belfius, fiche facture, fiche Tiers |
| Export CSV | 1 ligne par facture : Date, Réf SEPA, Relevé, Rapproché, Tiers, Pack, N° facture, Montant payé |

> **Rapprochement** : met à jour `llx_bank.rappro = 1` et `llx_bank.num_releve` directement en base. Équivalent à la fonction native Dolibarr (Banques/Caisses → Rapprochement) mais intégré dans Helpy.

### Automatisation quotidienne

**Windows — Task Scheduler**

```bat
schtasks /Create /TN "DolibarrAgeBF" /TR "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\dolibarr\htdocs\cron\run.php\"" /SC DAILY /ST 00:00
```

**Linux / Serveur**

```bash
0 0 * * * php /var/www/html/dolibarr/htdocs/cron/run.php >> /var/log/dolibarr_cron.log 2>&1
```

---

## Licence

- Code source : **GPLv3**
- Documentation : **GFDL**
