# Module AgeBF — Dolibarr ERP CRM

> Module custom développé dans le cadre d'un stage chez **Bruxelles Formation**.

**Auteur :** Soufiane Achraa — Stage 2026 — TECHGEST ICCBXL  
**Version :** 3.7  
**Compatibilité :** Dolibarr 19+, PHP 8.0+

---

## Objectif

Trois fonctionnalités regroupées sous le menu **Helpy** :

1. Calculer automatiquement l'âge des contacts **Fils / Fille** au 1er janvier, déterminer lesquels sont invités à la **fête des enfants** de Bruxelles Formation
2. Suivre les **documents** (composition de ménage) fournis par chaque Tiers
3. Suivre les **packs ASBL** (factures fournisseurs, virements, paiements en 3 versements)

**Règle métier — Fête des enfants :** un enfant est invité s'il a strictement moins de 16 ans au 1er janvier (les 15 ans sont inclus).

---

## Modules Dolibarr requis

Ces modules doivent être activés dans **Configuration → Modules/Applications** avant d'utiliser Helpy.

| Module | Obligatoire pour | Chemin d'activation |
|---|---|---|
| **Tiers** | Toutes les pages | Activé par défaut |
| **Contacts** | Fête des enfants | Activé par défaut |
| **Fournisseurs** | Suivi des packs | Configuration → Modules → Achats |
| **Banques et Caisses** | Suivi des packs (écritures) | Configuration → Modules → Finance |
| **Produits / Services** | Suivi des packs (packs) | Configuration → Modules → Produits |
| **GED (Documents)** | Documents Tiers | Configuration → Modules → Outils |
| **Travaux planifiés** | Cron calcul des âges | Configuration → Modules → Outils |

### Permissions utilisateur requises

Dans **Accueil → Utilisateurs → [utilisateur] → Permissions** :

| Section | Permission |
|---|---|
| Fournisseurs | Lire les factures (et paiements) fournisseurs |
| Fournisseurs | Créer les factures fournisseur |
| Banques et caisses | Consulter les comptes financiers |
| Banques et caisses | Créer/modifier montant/supprimer écritures bancaires |

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

### Suivi des packs

1. L'ASBL crée une **facture fournisseur** (`S02605-XXXX`) pour chaque pack souscrit par un employé
2. Le Tiers rembourse l'ASBL en **3 versements** — chaque versement est enregistré dans `llx_paiementfourn`
3. La page affiche en temps réel : montant payé, montant restant, statut (Soldée / Partielle / Impayée)
4. Le bouton **Préparer virement** ouvre la fiche facture fournisseur Dolibarr pour enregistrer un paiement
5. Le lien **Écritures** filtre la liste des écritures bancaires sur la référence de la facture

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
├── agebf_packs.php                   # Suivi des packs : factures fournisseurs par pack et statut
├── agebf_compta.php                  # Suivi des paiements : virements SEPA avec detail packs par Tiers
├── agebf_packs.php                   # Suivi des packs : factures fourn, virements, paiements
├── core/
│   └── modules/
│       └── modAgeBF.class.php        # Descripteur v3.6 (cron, extrafields, menus)
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

1. Télécharger **`module_agebf-3.6.zip`** depuis la [page Releases](https://github.com/soufianeach-DEV/dolibarr-agebf/releases)
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

### Après installation — étapes supplémentaires pour le Suivi des packs

1. Activer les modules **Fournisseurs** et **Banques et Caisses**
2. Ajouter les colonnes extrafields sur les factures fournisseurs (voir SQL ci-dessus)
3. Configurer les permissions utilisateur (voir tableau Permissions ci-dessus)

---

## Utilisation

### Menu Helpy

Après activation, le menu **Helpy** apparaît dans la barre du haut avec trois sous-pages :

| Page | Accès | Description |
|---|---|---|
| Fête des enfants | Helpy → Fête des enfants | Liste des enfants invités, stats, export CSV |
| Documents | Helpy → Documents Tiers | Suivi des compositions de ménage par Tiers |
| Suivi des packs | Helpy → Suivi des packs | Suivi des factures fournisseurs et paiements packs ASBL |

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
| Renommage admin | Sur les lignes "Manquante", un admin peut renommer le fichier inline |
| Ajout document | Sur les Tiers sans aucun fichier : formulaire d'upload intégré dans le tableau |
| Détection | Fichier contenant "composition", "compostion" ou "ménage" dans son nom |
| Sync Dolibarr | Tout ajout / renommage met à jour `llx_ecm_files` (onglet Documents natif) |

### Page Suivi des packs

| Élément | Description |
|---|---|
| Bandeau stats | Total / Soldées / Partielles / Impayées / Montant attendu vs reçu vs reste |
| Filtre | Par année + par statut de paiement |
| Filtres colonne | Pack (dropdown), Tiers/Bénéficiaire, N° facture, OGM, Montant min/max |
| Tableau | Pack (badge couleur) / Tiers + bénéficiaire / N° facture / OGM / Date / Montant / Payé / Statut / Dernier paiement / Virement |
| Statuts | **Soldée** (3/3) / **Partielle** (1/3 ou 2/3) / **Impayée** (aucun versement) |
| Bouton Voir | Ouvre la fiche facture fournisseur dans un modal (80 % de l'écran) |
| Bouton Préparer virement | Ouvre la fiche facture fournisseur Dolibarr pour enregistrer un paiement |
| Lien Écritures | Filtre la liste des écritures bancaires Belfius par référence de facture |
| OGM | Communication structurée belge `+++NNN/NNNN/NNNNN+++` (police monospace, taille 1.2em) |
| Export CSV | Télécharge la liste filtrée en `.csv` (UTF-8 + BOM Excel) |

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
