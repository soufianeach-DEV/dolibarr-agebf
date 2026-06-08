<?php
/* Copyright (C) 2026 Bruxelles Formation — Module AgeBF/Helpy v3.5 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) { $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php"; }
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

// ── Paramètres ────────────────────────────────────────────────────────────────
$annee  = (int) GETPOST('annee',  'int');
$statut = GETPOST('statut',  'aZ09');
$s_pack = GETPOST('s_pack',  'aZ09');
$s_tiers = trim(GETPOST('s_tiers', 'alphanohtml'));
$s_fac   = trim(GETPOST('s_fac',   'alphanohtml'));
$s_ogm   = trim(GETPOST('s_ogm',   'alphanohtml'));
$s_mmin  = (float) str_replace(',', '.', GETPOST('s_mmin', 'alphanohtml'));
$s_mmax  = (float) str_replace(',', '.', GETPOST('s_mmax', 'alphanohtml'));
$export  = GETPOST('export', 'aZ09');

if ($annee < 2020 || $annee > 2100) $annee = (int) date('Y');
if (!in_array($statut, ['tous', 'soldee', 'partielle', 'impayee'])) $statut = 'tous';

$url_base = DOL_URL_ROOT . '/custom/agebf/agebf_packs.php';

// ── Action : préparer en lot les demandes de virement SEPA (fournisseur) ───────
// Pour chaque facture fournisseur IMPAYÉE de l'année, on crée une demande de virement
// via l'API STANDARD Dolibarr demande_prelevement() (remplit llx_prelevement_demande),
// puis on renvoie vers l'écran standard « Virements fournisseurs » pour VÉRIFIER et
// GÉNÉRER le fichier SEPA. On ne réécrit AUCUNE génération : rien n'est payé tant que
// l'utilisateur ne génère pas le bon côté Dolibarr (point de contrôle humain conservé).
$action = GETPOST('action', 'aZ09');
if ($action === 'prepare_sepa_batch' && $user->hasRight('fournisseur', 'facture', 'creer')) {
	require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
	$sqlb = "SELECT f.rowid FROM " . MAIN_DB_PREFIX . "facture_fourn f"
	      . " WHERE f.entity = " . (int) $conf->entity
	      . " AND f.fk_statut >= 1 AND f.paye = 0"
	      . " AND YEAR(f.datef) = " . (int) $annee
	      . " ORDER BY f.rowid";
	$rb = $db->query($sqlb);
	$nb_ok = 0; $nb_skip = 0; $nb_err = 0; $errs = [];
	if ($rb) {
		while ($ob = $db->fetch_object($rb)) {
			$fac = new FactureFournisseur($db);
			if ($fac->fetch((int) $ob->rowid) <= 0) { $nb_err++; continue; }
			$r = $fac->demande_prelevement($user, 0, 'bank-transfer', 'supplier_invoice');
			if ($r > 0)        $nb_ok++;
			elseif ($r === 0)  $nb_skip++;                 // demande déjà existante -> ignorée
			else { $nb_err++; if (!empty($fac->error)) $errs[] = $fac->ref . ' : ' . $fac->error; }
		}
		$db->free($rb);
	}
	if ($nb_ok > 0)   setEventMessages($nb_ok . ' demande(s) de virement créée(s). Vérifiez puis générez le fichier SEPA ci-dessous.', null, 'mesgs');
	if ($nb_skip > 0) setEventMessages($nb_skip . ' facture(s) avaient déjà une demande en attente (ignorées).', null, 'warnings');
	if ($nb_err > 0)  setEventMessages($nb_err . ' erreur(s)' . (count($errs) ? ' : ' . implode(' ; ', array_slice($errs, 0, 5)) : ''), null, 'errors');
	if ($nb_ok === 0 && $nb_skip === 0 && $nb_err === 0) setEventMessages('Aucune facture impayée à préparer pour ' . $annee . '.', null, 'warnings');
	// Renvoi vers l'écran standard de génération du fichier SEPA fournisseur
	header('Location: ' . DOL_URL_ROOT . '/compta/prelevement/create.php?type=bank-transfer');
	exit;
}

// ── Tri cliquable ─────────────────────────────────────────────────────────────
$sortfield    = GETPOST('sortfield', 'aZ09');
$sortorder    = GETPOST('sortorder', 'aZ09');
$allowed_sort = ['f.datef', 's.nom', 'f.ref', 'f.total_ttc', 'p.label'];
if (!in_array($sortfield, $allowed_sort))               $sortfield = 's.nom';
if (!in_array(strtoupper($sortorder), ['ASC', 'DESC'])) $sortorder = 'ASC';

function packs_sort_link(string $label, string $field, string $sf, string $so, string $base, int $annee, string $statut): string {
	$new_order = ($sf === $field && $so === 'ASC') ? 'DESC' : 'ASC';
	$arrow = ($sf === $field) ? ($so === 'ASC' ? ' ▲' : ' ▼') : '';
	$url = $base . '?annee=' . $annee . '&statut=' . urlencode($statut)
	     . '&sortfield=' . urlencode($field) . '&sortorder=' . $new_order;
	return '<a href="' . $url . '" style="color:inherit;text-decoration:none">' . $label . $arrow . '</a>';
}

// ── Vérification colonnes extrafields facture_fourn (défensif) ────────────────
$chk_ben = $db->query("SHOW COLUMNS FROM " . MAIN_DB_PREFIX . "facture_fourn_extrafields LIKE 'beneficiaire'");
$has_ben = ($chk_ben && $db->num_rows($chk_ben) > 0);
$chk_ogm = $db->query("SHOW COLUMNS FROM " . MAIN_DB_PREFIX . "facture_fourn_extrafields LIKE 'communication_structuree'");
$has_ogm = ($chk_ogm && $db->num_rows($chk_ogm) > 0);
$join_ef  = ($has_ben || $has_ogm)
	? "LEFT JOIN " . MAIN_DB_PREFIX . "facture_fourn_extrafields ef ON ef.fk_object = f.rowid"
	: "";
$sel_ben  = $has_ben ? "COALESCE(ef.beneficiaire, '')"             : "''";
$sel_ogm  = $has_ogm ? "COALESCE(ef.communication_structuree, '')" : "''";
$grp_xtra = ($has_ben ? ", ef.beneficiaire" : "") . ($has_ogm ? ", ef.communication_structuree" : "");
$sec_sort = ($sortfield !== 'f.datef') ? ", f.datef DESC" : "";

// ── Requête principale ────────────────────────────────────────────────────────
$sql = "SELECT
    f.rowid          AS fac_id,
    f.ref            AS fac_ref,
    f.datef          AS date_facture,
    f.total_ttc      AS montant,
    f.paye,
    s.rowid          AS soc_id,
    s.nom            AS tiers_nom,
    p.label          AS pack_label,
    p.ref            AS pack_ref,
    " . $sel_ben . " AS beneficiaire,
    " . $sel_ogm . " AS communication_structuree,
    COALESCE(SUM(pf.amount), 0)              AS montant_paye,
    MAX(pay.datep)                            AS derniere_date_paiement,
    MAX(pay.num_paiement)                     AS derniere_ref_sepa
FROM " . MAIN_DB_PREFIX . "facture_fourn f
JOIN " . MAIN_DB_PREFIX . "societe s           ON s.rowid = f.fk_soc
JOIN " . MAIN_DB_PREFIX . "facture_fourn_det fd ON fd.fk_facture_fourn = f.rowid
JOIN " . MAIN_DB_PREFIX . "product p            ON p.rowid = fd.fk_product
" . $join_ef . "
LEFT JOIN " . MAIN_DB_PREFIX . "paiementfourn_facturefourn pf       ON pf.fk_facturefourn = f.rowid
LEFT JOIN " . MAIN_DB_PREFIX . "paiementfourn pay                   ON pay.rowid = pf.fk_paiementfourn
WHERE f.entity = " . (int)$conf->entity . "
  AND f.fk_statut >= 1
  AND YEAR(f.datef) = " . (int)$annee;

if ($s_pack  !== '')  $sql .= " AND p.ref = '" . $db->escape($s_pack) . "'";
if ($s_tiers !== '') {
	if ($has_ben) {
		$sql .= " AND (s.nom LIKE '%" . $db->escape($s_tiers) . "%' OR ef.beneficiaire LIKE '%" . $db->escape($s_tiers) . "%')";
	} else {
		$sql .= " AND s.nom LIKE '%" . $db->escape($s_tiers) . "%'";
	}
}
if ($s_fac !== '')             $sql .= " AND f.ref LIKE '%" . $db->escape($s_fac) . "%'";
if ($s_ogm !== '' && $has_ogm) $sql .= " AND ef.communication_structuree LIKE '%" . $db->escape($s_ogm) . "%'";
if ($s_mmin > 0)               $sql .= " AND f.total_ttc >= " . (float)$s_mmin;
if ($s_mmax > 0)               $sql .= " AND f.total_ttc <= " . (float)$s_mmax;

$sql .= " GROUP BY f.rowid, f.ref, f.datef, f.total_ttc, f.paye,
                   s.rowid, s.nom, p.label, p.ref" . $grp_xtra;
$sql .= " ORDER BY " . $sortfield . " " . $sortorder . $sec_sort;

$resql = $db->query($sql);
if (!$resql) {
	if ($export !== 'csv') { llxHeader(); }
	print '<div class="error">' . $db->lasterror() . '</div>';
	if ($export !== 'csv') { llxFooter(); }
	$db->close(); exit;
}

$rows = [];
while ($obj = $db->fetch_object($resql)) {
	$rows[] = $obj;
}
$db->free($resql);

// ── Statuts calculés ─────────────────────────────────────────────────────────
$nb_total = count($rows);
$nb_soldee = $nb_partielle = $nb_impayee = 0;
$total_attendu = $total_recu = 0;
$rows_filtered = [];

foreach ($rows as $r) {
	if ($r->paye == 1)             $s_row = 'soldee';
	elseif ($r->montant_paye > 0) $s_row = 'partielle';
	else                           $s_row = 'impayee';

	if ($s_row === 'soldee')    $nb_soldee++;
	elseif ($s_row === 'partielle') $nb_partielle++;
	else                            $nb_impayee++;

	$total_attendu += $r->montant;
	$total_recu    += $r->montant_paye;

	if ($statut === 'tous' || $statut === $s_row) {
		$rows_filtered[] = $r;
	}
}

// ── Liste des packs disponibles pour le filtre colonne ────────────────────────
$packs_dispo = [];
$rp = $db->query("SELECT DISTINCT p.ref, p.label FROM " . MAIN_DB_PREFIX . "product p
    JOIN " . MAIN_DB_PREFIX . "facture_fourn_det fd ON fd.fk_product = p.rowid
    JOIN " . MAIN_DB_PREFIX . "facture_fourn f ON f.rowid = fd.fk_facture_fourn AND YEAR(f.datef)=" . (int)$annee . "
    ORDER BY p.label");
if ($rp) { while ($o = $db->fetch_object($rp)) $packs_dispo[$o->ref] = $o->label; $db->free($rp); }

// ── Export CSV ────────────────────────────────────────────────────────────────
if ($export === 'csv') {
	$filename = 'helpy_packs_' . $annee . ($statut !== 'tous' ? '_' . $statut : '') . '.csv';
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Cache-Control: no-cache');
	echo "\xEF\xBB\xBF"; // BOM UTF-8 pour Excel

	$cols = ['Pack', 'Tiers', 'Beneficiaire', 'N° facture', 'Communication structuree',
	         'Date facture', 'Montant (EUR)', 'Paye (EUR)', 'Statut', 'Dernier paiement', 'Ref SEPA'];
	echo implode(';', $cols) . "\r\n";

	foreach ($rows_filtered as $r) {
		if ($r->paye == 1)             $st = 'Soldée';
		elseif ($r->montant_paye > 0) $st = 'Partielle';
		else                           $st = 'Impayée';

		$date_fac = $r->date_facture ? date('d/m/Y', strtotime($r->date_facture)) : '';
		$date_pay = $r->derniere_date_paiement ? date('d/m/Y', strtotime($r->derniere_date_paiement)) : '';

		$line = [
			$r->pack_label,
			$r->tiers_nom,
			$r->beneficiaire,
			$r->fac_ref,
			$r->communication_structuree,
			$date_fac,
			number_format((float)$r->montant,      2, ',', ''),
			number_format((float)$r->montant_paye, 2, ',', ''),
			$st,
			$date_pay,
			$r->derniere_ref_sepa,
		];
		echo implode(';', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $line)) . "\r\n";
	}
	$db->close(); exit;
}

// ── URL de base de l'export (reprend tous les filtres actifs) ─────────────────
function csv_url(string $base, int $annee, string $statut, string $s_pack, string $s_tiers,
                  string $s_fac, string $s_ogm, float $s_mmin, float $s_mmax): string {
	$p = ['annee' => $annee, 'statut' => $statut, 'export' => 'csv'];
	if ($s_pack)  $p['s_pack']  = $s_pack;
	if ($s_tiers) $p['s_tiers'] = $s_tiers;
	if ($s_fac)   $p['s_fac']   = $s_fac;
	if ($s_ogm)   $p['s_ogm']   = $s_ogm;
	if ($s_mmin)  $p['s_mmin']  = $s_mmin;
	if ($s_mmax)  $p['s_mmax']  = $s_mmax;
	return $base . '?' . http_build_query($p);
}

// ── HTML ──────────────────────────────────────────────────────────────────────
llxHeader("", "Helpy — Paiements à effectuer", '', '', 0, 0, '', '', '', 'mod-agebf page-packs');

// ── Modal visualisation facture ───────────────────────────────────────────────
print '
<style>
#bf-modal-overlay {
    display:none; position:fixed; inset:0; background:rgba(0,0,0,.55);
    z-index:9999; align-items:center; justify-content:center;
}
#bf-modal-overlay.open { display:flex; }
#bf-modal-box {
    background:#fff; border-radius:6px; box-shadow:0 8px 32px rgba(0,0,0,.4);
    width:80vw; height:80vh; display:flex; flex-direction:column; overflow:hidden;
}
#bf-modal-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:10px 16px; background:#f5f5f5; border-bottom:1px solid #ddd;
    font-weight:bold; font-size:.95em; color:#333; flex-shrink:0;
}
#bf-modal-header a { font-size:.8em; font-weight:normal; color:#6c757d; text-decoration:none; margin-right:12px; }
#bf-modal-header a:hover { text-decoration:underline; }
#bf-modal-close { cursor:pointer; font-size:1.4em; color:#888; border:none; background:none; padding:0 4px; }
#bf-modal-close:hover { color:#333; }
#bf-modal-iframe { flex:1; border:none; width:100%; }
.bf-btn-voir {
    display:inline-flex; align-items:center; padding:2px 9px;
    background:#6c757d; color:#fff !important; border-radius:3px;
    font-size:0.82em; text-decoration:none; white-space:nowrap; cursor:pointer;
    border:none; vertical-align:middle;
}
.bf-btn-voir:hover { background:#545b62; }
</style>

<div id="bf-modal-overlay">
  <div id="bf-modal-box">
    <div id="bf-modal-header">
      <span id="bf-modal-title">Facture</span>
      <div>
        <a id="bf-modal-newtab" href="#" target="_blank">&#x2197; Ouvrir dans un onglet</a>
        <button id="bf-modal-close" onclick="bfCloseModal()">&times;</button>
      </div>
    </div>
    <iframe id="bf-modal-iframe" src="about:blank"></iframe>
  </div>
</div>
<script>
function bfOpenModal(url, titre) {
    document.getElementById("bf-modal-iframe").src = url;
    document.getElementById("bf-modal-title").textContent = titre;
    document.getElementById("bf-modal-newtab").href = url;
    document.getElementById("bf-modal-overlay").classList.add("open");
}
function bfCloseModal() {
    document.getElementById("bf-modal-overlay").classList.remove("open");
    document.getElementById("bf-modal-iframe").src = "about:blank";
}
document.getElementById("bf-modal-overlay").addEventListener("click", function(e) {
    if (e.target === this) bfCloseModal();
});
document.addEventListener("keydown", function(e) { if (e.key === "Escape") bfCloseModal(); });
</script>
';

print load_fiche_titre("Paiements à effectuer — factures par pack", '', 'fa-file-invoice-dollar');

// ── Fil du workflow (étape 1 / 2) ─────────────────────────────────────────────
$url_compta = DOL_URL_ROOT . '/custom/agebf/agebf_compta.php';
print '<div style="display:flex;align-items:center;gap:8px;margin:2px 0 14px 0;font-size:0.88em">';
print '<span style="background:#0e6fb5;color:#fff;border-radius:14px;padding:4px 12px;font-weight:bold">1. Paiements à effectuer</span>';
print '<span style="color:#999">&rarr;</span>';
print '<a href="' . $url_compta . '" style="background:#eef3f8;color:#0e6fb5;border-radius:14px;padding:4px 12px;text-decoration:none;border:1px solid #bcd4ea">2. Rapprochement bancaire</a>';
print '</div>';

// ── Bandeau stats (cartes flex) ───────────────────────────────────────────────
$stats = [
    ['label' => 'Factures ' . $annee,  'value' => $nb_total,                              'color' => '#333'],
    ['label' => 'Sold&eacute;es',       'value' => $nb_soldee,                             'color' => '#28a745'],
    ['label' => 'Partielles',           'value' => $nb_partielle,                          'color' => '#fd7e14'],
    ['label' => 'Impay&eacute;es',      'value' => $nb_impayee,                            'color' => '#dc3545'],
    ['label' => 'Attendu',              'value' => price($total_attendu) . '&nbsp;&euro;', 'color' => '#333'],
    ['label' => 'Re&ccedil;u',          'value' => price($total_recu)    . '&nbsp;&euro;', 'color' => '#28a745'],
    ['label' => 'Reste',                'value' => price($total_attendu - $total_recu) . '&nbsp;&euro;', 'color' => '#dc3545'],
];

print '<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px">';
foreach ($stats as $st) {
    print '<div style="flex:1;min-width:110px;padding:10px 14px;background:#fff;border:1px solid #ddd;border-radius:6px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.06)">';
    print '<div style="font-size:1.35em;font-weight:bold;color:' . $st['color'] . ';line-height:1.2">' . $st['value'] . '</div>';
    print '<div style="font-size:0.8em;color:#777;margin-top:3px">' . $st['label'] . '</div>';
    print '</div>';
}
print '</div>';

// ── Bouton : préparer le lot de virements SEPA ────────────────────────────────
if ($user->hasRight('fournisseur', 'facture', 'creer')) {
    $msg_confirm = 'Créer les demandes de virement pour toutes les factures impayées de '
        . $annee . ' ?\n\nAucun paiement n\\\'est effectué à cette étape : '
        . 'vous vérifierez la liste puis générerez vous-même le fichier SEPA à l\\\'écran suivant.';
    print '<div style="margin:4px 0 14px 0">';
    print '<form method="POST" action="' . $url_base . '" style="display:inline"'
        . ' onsubmit="return confirm(\'' . $msg_confirm . '\');">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="prepare_sepa_batch">';
    print '<input type="hidden" name="annee" value="' . dol_escape_htmltag((string) $annee) . '">';
    print '<button type="submit" style="background:#0e6fb5;color:#fff;border:none;border-radius:5px;'
        . 'padding:9px 16px;font-size:0.9em;font-weight:bold;cursor:pointer;display:inline-flex;'
        . 'align-items:center;gap:7px;box-shadow:0 1px 3px rgba(0,0,0,.12)">'
        . img_picto('', 'fa-university', 'class="fa-fw"') . ' Préparer le lot de virements SEPA (' . $annee . ')'
        . '</button>';
    print '</form>';
    print '<span style="margin-left:12px;font-size:0.8em;color:#777;vertical-align:middle">'
        . 'Crée les demandes de virement pour les factures impayées de l\'année, puis ouvre l\'écran standard Dolibarr de génération du fichier SEPA.'
        . '</span>';
    print '</div>';
}

// ── Formulaire principal (filtres globaux + colonne) ──────────────────────────
print '<div class="fichecenter">';
print '<form method="GET" action="' . $url_base . '" id="bf-filter-form">';
print '<input type="hidden" name="sortfield" value="' . dol_escape_htmltag($sortfield) . '">';
print '<input type="hidden" name="sortorder" value="' . dol_escape_htmltag($sortorder) . '">';

// Ligne filtres globaux
print '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px">';

print '<label style="font-weight:bold">Ann&eacute;e :</label>';
print '<select name="annee" onchange="this.form.submit()" style="padding:4px 8px;border-radius:3px;border:1px solid #ccc">';
for ($y = (int)date('Y') + 1; $y >= 2024; $y--) {
    print '<option value="' . $y . '"' . ($y === $annee ? ' selected' : '') . '>' . $y . '</option>';
}
print '</select>';

$statuts_lib = ['tous' => 'Toutes', 'soldee' => 'Sold&eacute;es', 'partielle' => 'Partielles', 'impayee' => 'Impay&eacute;es'];
print '<label style="font-weight:bold">Statut :</label>';
print '<select name="statut" onchange="this.form.submit()" style="padding:4px 8px;border-radius:3px;border:1px solid #ccc">';
foreach ($statuts_lib as $val => $lib) {
    print '<option value="' . $val . '"' . ($val === $statut ? ' selected' : '') . '>' . $lib . '</option>';
}
print '</select>';

// Bouton effacer tous les filtres
$has_filters = ($s_pack || $s_tiers || $s_fac || $s_ogm || $s_mmin || $s_mmax || $statut !== 'tous');
if ($has_filters) {
    print '<a href="' . $url_base . '?annee=' . $annee . '" style="padding:4px 12px;background:#dc3545;color:#fff;border-radius:3px;font-size:0.85em;text-decoration:none">&#x2715; Effacer les filtres</a>';
}

// Bouton export CSV
$csv_href = csv_url($url_base, $annee, $statut, $s_pack, $s_tiers, $s_fac, $s_ogm, $s_mmin, $s_mmax);
print '<a href="' . $csv_href . '" style="margin-left:auto;padding:4px 14px;background:#28a745;color:#fff;border-radius:3px;font-size:0.85em;text-decoration:none;display:inline-flex;align-items:center;gap:6px">'
    . img_picto('', 'fa-download', '') . ' Export CSV</a>';

print '</div>'; // fin ligne filtres globaux

// ── Tableau avec filtres par colonne ──────────────────────────────────────────
$input_style = 'width:100%;padding:2px 5px;font-size:0.82em;border:1px solid #ccc;border-radius:3px;box-sizing:border-box';

print '<table class="noborder centpercent">';

// En-têtes (tri cliquable sur Pack, Tiers, N° facture, Date, Montant)
print '<tr class="liste_titre">';
print '<th style="min-width:140px">' . packs_sort_link('Pack',                            'p.label',     $sortfield, $sortorder, $url_base, $annee, $statut) . '</th>';
print '<th style="min-width:150px">' . packs_sort_link('Tiers / B&eacute;n&eacute;ficiaire', 's.nom',   $sortfield, $sortorder, $url_base, $annee, $statut) . '</th>';
print '<th style="min-width:110px">' . packs_sort_link('N&deg; facture',                  'f.ref',       $sortfield, $sortorder, $url_base, $annee, $statut) . '</th>';
print '<th style="min-width:140px">Communication structur&eacute;e</th>';
print '<th class="center" style="min-width:90px">' . packs_sort_link('Date facture', 'f.datef',     $sortfield, $sortorder, $url_base, $annee, $statut) . '</th>';
print '<th class="right"  style="min-width:80px">' . packs_sort_link('Montant',      'f.total_ttc', $sortfield, $sortorder, $url_base, $annee, $statut) . '</th>';
print '<th class="right"  style="min-width:80px">Pay&eacute;</th>';
print '<th class="center" style="min-width:90px">Statut</th>';
print '<th class="center" style="min-width:120px">Dernier paiement</th>';
print '<th class="center" style="min-width:120px">Virement</th>';
print '</tr>';

// Ligne de filtres par colonne
print '<tr style="background:#f8f9fa;border-bottom:1px solid #dee2e6">';

// Filtre Pack (dropdown)
print '<td style="padding:4px 6px">';
print '<select name="s_pack" onchange="this.form.submit()" style="' . $input_style . '">';
print '<option value="">— Tous —</option>';
foreach ($packs_dispo as $ref => $label) {
    print '<option value="' . dol_escape_htmltag($ref) . '"' . ($s_pack === $ref ? ' selected' : '') . '>' . dol_escape_htmltag($label) . '</option>';
}
print '</select></td>';

// Filtre Tiers/Bénéficiaire
print '<td style="padding:4px 6px"><input type="text" name="s_tiers" value="' . dol_escape_htmltag($s_tiers) . '" placeholder="Nom..." style="' . $input_style . '"></td>';

// Filtre N° facture
print '<td style="padding:4px 6px"><input type="text" name="s_fac" value="' . dol_escape_htmltag($s_fac) . '" placeholder="FA2026-..." style="' . $input_style . '"></td>';

// Filtre OGM
print '<td style="padding:4px 6px"><input type="text" name="s_ogm" value="' . dol_escape_htmltag($s_ogm) . '" placeholder="+++..." style="' . $input_style . '"></td>';

// Date (pas de filtre colonne, vide)
print '<td style="padding:4px 6px"></td>';

// Filtre montant min/max
print '<td style="padding:4px 6px">';
print '<input type="number" name="s_mmin" value="' . ($s_mmin > 0 ? $s_mmin : '') . '" placeholder="min" style="' . $input_style . ';margin-bottom:2px">';
print '<input type="number" name="s_mmax" value="' . ($s_mmax > 0 ? $s_mmax : '') . '" placeholder="max" style="' . $input_style . '">';
print '</td>';

// Payé (pas de filtre direct, vide)
print '<td></td>';

// Statut déjà dans le filtre global
print '<td></td>';

// Bouton submit + vide pour virement
print '<td style="padding:4px 6px;text-align:center">';
print '<button type="submit" style="padding:3px 10px;background:#0d6efd;color:#fff;border:none;border-radius:3px;font-size:0.82em;cursor:pointer">Filtrer</button>';
print '</td>';
print '<td></td>';
print '</tr>';

// ── Lignes de données ─────────────────────────────────────────────────────────
if (empty($rows_filtered)) {
    print '<tr class="oddeven"><td colspan="10" class="center opacitymedium" style="padding:20px">Aucune facture trouv&eacute;e.</td></tr>';
}

$pack_colors = [
    'PACK-SANTE'      => '#0d6efd',
    'PACK-LUN'        => '#20c997',
    'PACK-NAIS'       => '#e83e8c',
    'PACK-SPORT'      => '#fd7e14',
    'HOSPI-18-20'     => '#6f42c1',
    'HOSPI-21-24'     => '#7952b3',
    'HOSPI-BEAU'      => '#8540c4',
    'HOSPI-BEAU-18'   => '#9b59b6',
    'HOSPI-BEAU-1820' => '#8540c4',
    'HOSPI-BEAU-ADU'  => '#6f42c1',
    'HOSPI-ENF-18'    => '#5a2d8c',
    'HOSPI-ENF-1920'  => '#7952b3',
    'HOSPI-AGT-2164'  => '#4a235a',
    'IND-FUN'         => '#343a40',
    'DEPART-PEN'      => '#6c757d',
];

foreach ($rows_filtered as $r) {

    // Badge pack
    $pack_color = $pack_colors[$r->pack_ref] ?? '#6c757d';
    $pack_badge = '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:0.78em;font-weight:bold;background:' . $pack_color . ';color:#fff;white-space:nowrap">'
                . dol_escape_htmltag($r->pack_label) . '</span>';

    // Tiers + bénéficiaire
    $lien_tiers = '<a href="' . DOL_URL_ROOT . '/societe/card.php?socid=' . (int)$r->soc_id . '">'
                . dol_escape_htmltag($r->tiers_nom) . '</a>';
    if ($r->beneficiaire && $r->beneficiaire !== $r->tiers_nom) {
        $lien_tiers .= '<br><span style="font-size:0.82em;color:#555">'
                    . img_picto('', 'fa-user', 'style="font-size:0.85em"') . ' '
                    . dol_escape_htmltag($r->beneficiaire) . '</span>';
    }

    // N° facture + bouton Voir (modal)
    $fac_url     = DOL_URL_ROOT . '/fourn/facture/card.php?id=' . (int)$r->fac_id;
    $fac_ref_esc = dol_escape_htmltag($r->fac_ref);
    $lien_fac    = '<a href="' . $fac_url . '">' . $fac_ref_esc . '</a>'
                 . ' <button type="button" class="bf-btn-voir" onclick="bfOpenModal('
                 . htmlspecialchars(json_encode($fac_url), ENT_QUOTES) . ','
                 . htmlspecialchars(json_encode($r->fac_ref), ENT_QUOTES) . ')">'
                 . img_picto('', 'fa-eye', '') . ' Voir</button>';

    // Communication structurée — texte brut
    $ogm_cell = $r->communication_structuree !== ''
        ? dol_escape_htmltag($r->communication_structuree)
        : '<span style="color:#aaa">—</span>';

    // Statut
    if ($r->paye == 1) {
        $statut_label = '<span style="color:#28a745;font-weight:bold">Sold&eacute;e</span>';
    } elseif ($r->montant_paye > 0) {
        $restant      = $r->montant - $r->montant_paye;
        $statut_label = '<span style="color:#fd7e14;font-weight:bold">Partielle</span>'
                      . '<br><span style="font-size:0.78em;color:#888">Reste ' . price($restant) . '&nbsp;&euro;</span>';
    } else {
        $statut_label = '<span style="color:#dc3545;font-weight:bold">Impay&eacute;e</span>';
    }

    // Dernier paiement
    if ($r->derniere_date_paiement) {
        $date_pay = dol_print_date($db->jdate($r->derniere_date_paiement), 'day');
        $lien_pay = $date_pay;
        if ($r->derniere_ref_sepa) {
            $lien_pay .= '<br><a href="' . DOL_URL_ROOT . '/compta/bank/bankentries_list.php?account=2&search_ref='
                       . urlencode($r->derniere_ref_sepa) . '" style="font-size:0.8em;color:#6c757d" title="Voir mouvement bancaire">'
                       . dol_escape_htmltag($r->derniere_ref_sepa) . '</a>';
        }
    } else {
        $lien_pay = '<span style="color:#aaa">—</span>';
    }

    // Bouton Préparer virement bancaire + lien vérification écriture
    $vir_url    = DOL_URL_ROOT . '/fourn/facture/card.php?id=' . (int)$r->fac_id . '#paiement';
    $ecr_url    = DOL_URL_ROOT . '/compta/bank/bankentries_list.php?account=2&search_description='
                . urlencode($r->fac_ref);
    $ecr_link   = '<br><a href="' . $ecr_url . '" target="_blank"'
                . ' style="font-size:0.78em;color:#6c757d;text-decoration:none;white-space:nowrap"'
                . ' title="Voir les &eacute;critures bancaires correspondantes">'
                . img_picto('', 'fa-search', 'style="font-size:0.85em"') . ' &Eacute;critures</a>';
    if ($r->paye == 1) {
        $vir_btn = '<span style="color:#28a745;font-size:0.82em">' . img_picto('', 'fa-check-circle', '') . ' Sold&eacute;e</span>'
                 . $ecr_link;
    } else {
        $vir_btn = '<a href="' . $vir_url . '" style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;'
                 . 'background:#17a2b8;color:#fff;border-radius:3px;font-size:0.82em;text-decoration:none;white-space:nowrap">'
                 . img_picto('', 'fa-university', '') . ' Pr&eacute;parer virement</a>'
                 . $ecr_link;
    }

    print '<tr class="oddeven">';
    print '<td>' . $pack_badge . '</td>';
    print '<td>' . $lien_tiers . '</td>';
    print '<td style="white-space:nowrap">' . $lien_fac . '</td>';
    print '<td style="font-family:monospace;font-size:1.2em;letter-spacing:0.05em;font-weight:600">' . $ogm_cell . '</td>';
    print '<td class="center">' . dol_print_date($db->jdate($r->date_facture), 'day') . '</td>';
    print '<td class="right" style="font-weight:bold;white-space:nowrap">' . price($r->montant) . '&nbsp;&euro;</td>';

    if ($r->montant_paye > 0) {
        print '<td class="right" style="color:#28a745;font-weight:bold;white-space:nowrap">' . price($r->montant_paye) . '&nbsp;&euro;</td>';
    } else {
        print '<td class="right" style="color:#bbb">0&nbsp;&euro;</td>';
    }

    print '<td class="center">' . $statut_label . '</td>';
    print '<td class="center">' . $lien_pay . '</td>';
    print '<td class="center">' . $vir_btn . '</td>';
    print '</tr>';
}

print '</table>';
print '</form>';

// ── Légende ───────────────────────────────────────────────────────────────────
print '<div style="margin-top:14px;font-size:0.85em;color:#666;display:flex;gap:18px;flex-wrap:wrap;align-items:center">';
print '<b>L&eacute;gende :</b>';
print '<span style="color:#28a745;font-weight:bold">Sold&eacute;e</span> 3/3 versements';
print '<span style="color:#fd7e14;font-weight:bold">Partielle</span> 1/3 ou 2/3';
print '<span style="color:#dc3545;font-weight:bold">Impay&eacute;e</span> aucun versement';
print '<span>R&eacute;f. SEPA &rarr; mouvement bancaire Dolibarr</span>';
print '</div>';

print '</div>'; // fichecenter

llxFooter();
$db->close();
