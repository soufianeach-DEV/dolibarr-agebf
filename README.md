# Module AgeBF — Dolibarr ERP CRM

> Module custom développé dans le cadre d'un stage chez **Bruxelles Formation**.

**Auteur :** Soufiane Achraa — Stage 2026 — TECHGEST ICCBXL  
**Version :** 6.2  
**Compatibilité :** Dolibarr 19+, PHP 8.0+

---

## Objectif

Cinq fonctionnalités regroupées sous le menu **Helpy** :

1. Calculer automatiquement l'âge des contacts **Fils / Fille** au 1er janvier, déterminer lesquels sont invités à la **fête des enfants** de Bruxelles Formation
2. Suivre les **documents** (composition de ménage) fournis par chaque Tiers
3. Préparer les **paiements à effectuer** (factures fournisseurs des packs ASBL, virements SEPA en un clic)
4. Réaliser le **rapprochement bancaire** des paiements — fournisseurs SEPA via import Belfius (scénario B) et **factures clients** avec filtre Toutes / Clients / Fournisseurs
5. Gérer les **assurances** (Hospi. / Dentaire) par contact, avec sync automatique depuis le champ Assurcard, motif d'archivage (Décédé / Retraité / Démissionné), filtres et tri colonnes

**Règle métier — Fête des enfants :** un enfant est invité s'il a strictement moins de 16 ans au 1er janvier (les 15 ans sont inclus).

---

## Modules Dolibarr requis

Ces modules doivent être activés dans **Configuration → Modules/Applications** avant d'utiliser Helpy.

| Module | Obligatoire pour | Chemin d'activation |
|---|---|---|
| **Tiers** | Toutes les pages | Activé par défaut |
| **Contacts** | Fête des enfants + Assurances | Activé par défaut |
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

## Champs créés automatiquement à l'activation

### Sur les contacts (`llx_socpeople_extrafields`)

| Champ | Type | Description |
|---|---|---|
| `age_1jan` | Int | Âge calculé au 1er janvier |
| `fete_enfants` | Boolean | Coché si l'enfant (Fils/Fille) a < 16 ans |
| `fk_parent` | Int | ID contact parent (informatif) |
| `assurance_hospi` | Boolean | Assurance hospitalisation souscrite |
| `assurance_dentaire` | Boolean | Assurance dentaire souscrite |

### Sur les Tiers (`llx_societe_extrafields`)

| Champ | Type | Description |
|---|---|---|
| `fete_enfants` | Boolean | Coché si au moins un enfant du Tiers est invité |
| `nb_enfants_invites` | Int | Nombre d'enfants < 16 ans liés au Tiers |

### Sur les factures fournisseurs (à ajouter manuellement)

```sql
ALTER TABLE llx_facture_fourn_extrafields
    ADD COLUMN beneficiaire VARCHAR(255) DEFAULT NULL,
    ADD COLUMN communication_structuree VARCHAR(50) DEFAULT NULL;
```

### Table propre au module

| Table | Rôle |
|---|---|
| `llx_agebf_lot_sepa` | Stockage du `MsgId` SEPA par bon de virement — clé de rapprochement infaillible |
| `llx_agebf_tiers_archive` | Motif d'archivage par Tiers (`decede` / `retraite` / `demission` / `autre`) |

---

## Structure du module

```
agebf/
├── agebfindex.php                    # Fête des enfants : liste, stats, filtre, export CSV
├── agebf_documents.php               # Documents : composition de ménage par Tiers
├── agebf_packs.php                   # Paiements à effectuer : factures fournisseurs par pack et statut
├── agebf_compta.php                  # Rapprochement bancaire : virements SEPA + factures clients, import Belfius
├── agebf_assurances.php              # Assurances : Hospi./Dentaire par contact, sync Assurcard
├── core/
│   └── modules/
│       └── modAgeBF.class.php        # Descripteur v6.1 (cron, extrafields, menus)
├── class/
│   └── agebf.class.php               # Logique de calcul + propagation vers Tiers
├── admin/
│   ├── setup.php                     # Page de configuration
│   └── about.php                     # À propos
├── sql/
│   └── dolibarr_allversions.sql      # Script SQL chargé à l'activation
├── langs/
│   └── en_US/
│       └── agebf.lang                # Traductions
└── lib/
    └── agebf.lib.php                 # Fonctions utilitaires
```

---

## Installation

### Méthode 1 — ZIP via l'interface Dolibarr (recommandée)

1. Télécharger **`module_agebf-6.1.zip`** depuis la [page Releases](https://github.com/soufianeach-DEV/dolibarr-agebf/releases)
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

### Mise à jour

Désactivez le module → déposez le nouveau ZIP → réactivez. Les champs extrafields et les menus sont mis à jour automatiquement.

### Après installation — étapes supplémentaires

1. Activer les modules **Fournisseurs** et **Banques et Caisses**
2. Ajouter les colonnes extrafields sur les factures fournisseurs (voir SQL ci-dessus)
3. Configurer les permissions utilisateur (voir tableau Permissions ci-dessus)

---

## Utilisation

### Menu Helpy

Après activation, le menu **Helpy** apparaît dans la barre du haut avec cinq sous-pages :

| Page | Accès | Description |
|---|---|---|
| Fête des enfants | Helpy → Fête des enfants | Liste des enfants invités, stats, export CSV |
| Documents Tiers | Helpy → Documents Tiers | Suivi des compositions de ménage par Tiers |
| Paiements à effectuer | Helpy → Paiements à effectuer | Préparer/suivre les factures fournisseurs, virements SEPA en 1 clic |
| Rapprochement bancaire | Helpy → Rapprochement bancaire | Factures clients + fournisseurs, pointage relevé Belfius (import CSV ou manuel) |
| Assurances | Helpy → Assurances | Assurance Hospi./Dentaire par contact, sync Assurcard, motif archivage, filtres, tri colonnes |

---

### Page Fête des enfants

| Élément | Description |
|---|---|
| Bandeau stats | Total enfants, nombre < 16 ans, cases cochées |
| Filtre | "Invités seulement" / "Tous les enfants" |
| Tableau | Tiers (parent), Nom, Prénom, Genre, Âge, Case fête — tri cliquable |
| Export CSV | Télécharge la liste en `.csv` (UTF-8 + BOM Excel) |

---

### Page Documents Tiers

| Élément | Description |
|---|---|
| Bandeau stats | Total Tiers / Composition fournie / Composition manquante / Aucun document |
| Filtre | 3 boutons : Tous / Avec composition / Sans composition |
| Tableau | Tiers, nb documents, statut composition, lien fiche |
| Indicateurs | **Fournie** (vert) / **Manquante** (orange) / **Aucun document** (rouge) |
| Bouton Voir | Ouvre le fichier dans un popup modal (80 % de l'écran) |
| Renommage admin | Renommage inline de tout fichier (Fournie ou Manquante) |
| Ajout document | Sur les Tiers sans fichier : formulaire d'upload intégré |
| Détection | Fichier contenant "composition", "compostion" ou "ménage" dans son nom |

---

### Page Paiements à effectuer

| Élément | Description |
|---|---|
| Bandeau stats | Total / Soldées / Partielles / Impayées / Attendu vs Reçu vs Reste |
| Filtres | Année, statut paiement, pack, tiers, N° facture, OGM, montant min/max |
| Tableau | Pack (badge couleur) / Tiers / N° facture / OGM / Date / Montant / Payé / Statut / Dernier paiement / Virement |
| Statuts | **Soldée** (3/3) / **Partielle** (1/3 ou 2/3) / **Impayée** (aucun versement) |
| Fil du workflow | Bandeau cliquable : **1. Paiements à effectuer → 2. Rapprochement bancaire** |
| **Bouton Préparer le lot de virements SEPA** | Crée en 1 clic les demandes de virement pour toutes les factures impayées de l'année (API `demande_prelevement`), puis renvoie vers l'écran standard Dolibarr de génération SEPA. **Ne génère pas le fichier** : validation et génération restent un geste humain |
| Tri colonnes | Tri cliquable sur toutes les colonnes (Pack, Tiers, N° facture, Date, Montant, Payé, Statut, Dernier paiement) |
| Export CSV | Liste filtrée en `.csv` (UTF-8 + BOM Excel) |

---

### Page Rapprochement bancaire

Décompose chaque paiement par facture et permet le pointage du relevé Belfius. Couvre à la fois les factures **fournisseurs** (packs SEPA) et **clients**.

| Élément | Description |
|---|---|
| Fil du workflow | Bandeau cliquable : **1. Paiements à effectuer → 2. Rapprochement bancaire** |
| Filtre type | **Toutes / Clients / Fournisseurs** — badge Client/Fourn. en vue "Toutes" |
| Bandeau stats | Nb virements / Rapprochés / Non rapprochés / Montants |
| Filtres | Année, statut rapproché (Tous / Oui / Non), référence SEPA |
| Vue par facture | 1 ligne par facture, ses virements dépliés au clic |
| **Import Belfius (CSV)** | Relevé scénario B — rapprochement par **référence SEPA** (`MsgId = FICHIER : DOL/AAAAMMJJ/CTxx`) ou repli montant + date ; niveau de confiance : Sûr réf. SEPA / Sûr montant+date / Probable / Ambigu / Aucun lot |
| Rapprochement inline | Saisir N° relevé Belfius (ex: `2026/0003`) + cliquer **Rapprocher** |
| Annulation (admin) | Bouton **Ann.** sur les lignes rapprochées, avec confirmation |
| Liens directs | Fiche paiement, écritures bancaires Belfius, fiche facture, fiche Tiers |
| Tri colonnes | Tri cliquable sur toutes les colonnes (Tiers, Facture, Montant attendu, Déjà payé, Restant, Virements, Rapprochement) |
| Export CSV | 1 ligne par facture : Date, Réf SEPA, Relevé, Rapproché, Tiers, Pack, Facture, Montant |

> **Note :** l'import Belfius CSV est réservé aux factures fournisseurs (SEPA). En vue "Clients", ce formulaire est masqué.

---

### Page Assurances

Gestion des assurances complémentaires (hospitalisation et dentaire) par contact.

| Élément | Description |
|---|---|
| Bandeau stats | Nb Tiers, contacts total, nb Hospi., nb Dentaire, nb Assurcard, nb Assurcard sans Hospi. |
| **Barre de filtres** | Recherche nom · Filtre Assurance (Tous/Hospi./Dentaire/Les deux/Aucune) · Filtre Assurcard (Tous/Avec/Sans/Anomalie) · Filtre Statut (Actifs+archivés/Actifs/Archivés) |
| **Tri colonnes** | Clic sur Tiers/Contacts/Hospi./Dentaire → tri ASC/DESC |
| Tableau | 1 ligne par Tiers — contacts dépliables au clic |
| Détail contact | Nom, Prénom, Badge rôle (Employé(e)/Conjoint(e)/Fils/Fille), N° Assurcard, case Hospi., case Dentaire |
| Highlight | Fond jaune sur les contacts ayant un N° Assurcard mais Hospi. non cochée |
| **Bouton Sync Assurcard** | Coche automatiquement `assurance_hospi` pour tous les contacts ayant un N° Assurcard renseigné |
| **Motif d'archivage** | Tiers décédé/retraité/démissionné : badge coloré (⚰/🏖/🚶/📁) + select inline pour définir le motif sans quitter la page |
| **Règle décès** | Tiers décédé (`status=0`) : toujours visible (grisé) ; famille active et modifiable ; contact Employé(e) : cases remplacées par `—` (non éditables) |
| Enregistrement | Bouton global « Enregistrer les assurances » — toutes les cases en une seule requête |
| Déplier/Replier tout | Boutons globaux pour ouvrir ou fermer tous les Tiers en un clic |

> **Archiver un Tiers décédé :** Fiche Tiers → Actions → **Mettre en inactif**, puis sur la page Assurances choisir **⚰ Décédé(e)** dans le select inline. La famille reste active et ses assurances restent éditables.

---

### Rapprochement bancaire — Import Belfius (scénario B)

Le relevé Belfius présente chaque ordre collectif SEPA comme **une seule ligne globale** (montant total, sans détail des bénéficiaires). L'import CSV applique le « scénario B » :

1. Le module lit la communication de chaque ligne et retrouve `FICHIER : DOL/AAAAMMJJ/CTxx` — exactement le `MsgId` du fichier SEPA généré par Dolibarr.
2. Rapprochement en **deux niveaux** avec niveau de confiance :
   - **Sûr (réf. SEPA)** — `CTxx` du relevé = `MsgId` enregistré dans `llx_agebf_lot_sepa` → correspondance exacte, zéro ambiguïté.
   - **Sûr (montant + date)** — repli : un seul lot correspond par montant total *et* date proche.
   - **Probable** — repli : un seul lot par montant mais date éloignée, ou plusieurs lots dont la date en isole un.
   - **Ambigu** — repli : plusieurs lots au même montant et la date ne tranche pas.
   - **Aucun lot** — aucune correspondance.
3. En un clic, tous les virements du lot sélectionné passent en `rappro = 1` avec le N° de relevé Belfius.

> La clé `MsgId` est stockée dans la table propre au module `llx_agebf_lot_sepa`. Aucune table standard de Dolibarr n'est modifiée. Tant que cette table n'est pas alimentée, le rapprochement fonctionne via le repli montant + date.

---

### Automatisation quotidienne (cron âges)

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
