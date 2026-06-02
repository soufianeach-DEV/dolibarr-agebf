<?php
/* Copyright (C) 2026 Bruxelles Formation — Module AgeBF/Helpy */

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
$annee       = (int) GETPOST('annee',       'int');
$s_rapproche = GETPOST('s_rapproche',       'aZ09');
$s_sepa      = trim(GETPOST('s_sepa',       'alphanohtml'));
$export      = GETPOST('export',            'aZ09');
$action      = GETPOST('action',            'aZ09');

if ($annee < 2020 || $annee > 2100) $annee = (int) date('Y');
if (!in_array($s_rapproche, ['tous', 'oui', 'non'])) $s_rapproche = 'tous';

$url_base = DOL_URL_ROOT . '/custom/agebf/agebf_compta.php';
$msg_ok  = '';
$msg_err = '';

if (GETPOST('rapproche_ok', 'int') == 1) {
	$msg_ok = 'Paiement rapproche avec succes — releve : <b>' . dol_escape_htmltag(GETPOST('releve_ok', 'nohtml')) . '</b>';
}
if (GETPOST('derapproche_ok', 'int') == 1) {
	$msg_ok = 'Rapprochement annule.';
}

// ── Action : rapprocher un paiement ──────────────────────────────────────────
if ($action === 'rapprocher' && !empty($_POST['token']) && checkToken()) {
	$pay_id    = (int) GETPOST('pay_id',    'int');
	$num_rel   = trim(GETPOST('num_releve', 'alphanohtml'));

	if ($pay_id > 0) {
		// Trouver l'écriture bancaire liée à ce paiement fournisseur
		$sql_bk = "SELECT b.rowid FROM " . MAIN_DB_PREFIX . "bank b"
		        . " INNER JOIN " . MAIN_DB_PREFIX . "bank_url bu ON bu.fk_bank = b.rowid"
		        . " WHERE bu.url_id = " . $pay_id
		        . " AND bu.type = 'payment_supplier' LIMIT 1";
		$rbk = $db->query($sql_bk);
		if ($rbk && ($obk = $db->fetch_object($rbk))) {
			$bank_id = (int) $obk->rowid;
			$sql_up  = "UPDATE " . MAIN_DB_PREFIX . "bank"
			         . " SET rappro = 1"
			         . ($num_rel !== '' ? ", num_releve = '" . $db->escape($num_rel) . "'" : "")
			         . " WHERE rowid = " . $bank_id;
			if ($db->query($sql_up)) {
				header('Location: ' . $url_base . '?annee=' . $annee . '&s_rapproche=' . urlencode($s_rapproche)
				     . '&s_sepa=' . urlencode($s_sepa) . '&rapproche_ok=1&releve_ok=' . urlencode($num_rel));
				exit;
			} else {
				$msg_err = 'Erreur lors du rapprochement : ' . $db->lasterror();
			}
		} else {
			$msg_err = 'Aucune ecriture bancaire trouvee pour ce paiement. Verifiez que le paiement est bien lie a un compte bancaire dans Dolibarr.';
		}
	}
}

// ── Action : annuler un rapprochement (admin uniquement) ─────────────────────
if ($action === 'derapprocher' && $user->admin && !empty($_POST['token']) && checkToken()) {
	$pay_id = (int) GETPOST('pay_id', 'int');

	if ($pay_id > 0) {
		$sql_bk = "SELECT b.rowid FROM " . MAIN_DB_PREFIX . "bank b"
		        . " INNER JOIN " . MAIN_DB_PREFIX . "bank_url bu ON bu.fk_bank = b.rowid"
		        . " WHERE bu.url_id = " . $pay_id
		        . " AND bu.type = 'payment_supplier' LIMIT 1";
		$rbk = $db->query($sql_bk);
		if ($rbk && ($obk = $db->fetch_object($rbk))) {
			$sql_up = "UPDATE " . MAIN_DB_PREFIX . "bank SET rappro = 0 WHERE rowid = " . (int)$obk->rowid;
			if ($db->query($sql_up)) {
				header('Location: ' . $url_base . '?annee=' . $annee . '&s_rapproche=' . urlencode($s_rapproche)
				     . '&s_sepa=' . urlencode($s_sepa) . '&derapproche_ok=1');
				exit;
			} else {
				$msg_err = 'Erreur lors de l\'annulation : ' . $db->lasterror();
			}
		}
	}
}

// ── Requête principale : paiements fournisseurs ──────────────────────────────
// Pour chaque paiement (pay), on récupère :
//   - la référence SEPA (num_paiement)
//   - le relevé bancaire Belfius (b.num_releve) et son statut rapproché (b.rappro)
//   - le nombre de factures couvertes
$sql = "SELECT
    pay.rowid                                               AS pay_id,
    pay.datep                                               AS date_paiement,
    COALESCE(pay.num_paiement, '')                         AS ref_sepa,
    pay.amount                                              AS montant,
    COALESCE(b.num_releve, '')                             AS num_releve,
    COALESCE(b.rappro, 0)                                  AS rapproche,
    COUNT(DISTINCT pff.fk_facturefourn)                    AS nb_factures
FROM " . MAIN_DB_PREFIX . "paiementfourn pay
LEFT JOIN " . MAIN_DB_PREFIX . "bank_url bu
       ON bu.url_id = pay.rowid AND bu.type = 'payment_supplier'
LEFT JOIN " . MAIN_DB_PREFIX . "bank b
       ON b.rowid = bu.fk_bank
LEFT JOIN " . MAIN_DB_PREFIX . "paiementfourn_facturefourn pff
       ON pff.fk_paiementfourn = pay.rowid
WHERE pay.entity = " . (int)$conf->entity . "
  AND YEAR(pay.datep) = " . (int)$annee;

if ($s_rapproche === 'oui') $sql .= " AND b.rappro = 1";
if ($s_rapproche === 'non') $sql .= " AND (b.rappro = 0 OR b.rappro IS NULL)";
if ($s_sepa !== '')          $sql .= " AND pay.num_paiement LIKE '%" . $db->escape($s_sepa) . "%'";

$sql .= " GROUP BY pay.rowid, pay.datep, pay.num_paiement, pay.amount, b.num_releve, b.rappro";
$sql .= " ORDER BY pay.datep DESC, pay.rowid DESC";

$resql = $db->query($sql);
if (!$resql) {
	if ($export !== 'csv') { llxHeader(); }
	print '<div class="error">' . $db->lasterror() . '</div>';
	if ($export !== 'csv') { llxFooter(); }
	$db->close(); exit;
}

$payments = [];
while ($obj = $db->fetch_object($resql)) {
	$payments[] = $obj;
}
$db->free($resql);

// ── Pour chaque paiement : charger le détail factures/packs ──────────────────
// (une requête par paiement — acceptable car le nombre de virements/an est limité)
foreach ($payments as &$pay) {
	$sql_det = "SELECT
	    s.rowid          AS soc_id,
	    s.nom            AS tiers_nom,
	    p.ref            AS pack_ref,
	    p.label          AS pack_label,
	    f.rowid          AS fac_id,
	    f.ref            AS fac_ref,
	    pff.amount       AS montant_paye,
	    f.total_ttc      AS montant_facture
	FROM " . MAIN_DB_PREFIX . "paiementfourn_facturefourn pff
	JOIN " . MAIN_DB_PREFIX . "facture_fourn f   ON f.rowid = pff.fk_facturefourn
	JOIN " . MAIN_DB_PREFIX . "societe s         ON s.rowid = f.fk_soc
	JOIN " . MAIN_DB_PREFIX . "facture_fourn_det fd ON fd.fk_facture_fourn = f.rowid
	JOIN " . MAIN_DB_PREFIX . "product p         ON p.rowid = fd.fk_product
	WHERE pff.fk_paiementfourn = " . (int)$pay->pay_id . "
	  AND f.entity = " . (int)$conf->entity . "
	ORDER BY s.nom ASC, p.label ASC";

	$rdet = $db->query($sql_det);
	$pay->detail = [];
	if ($rdet) {
		while ($d = $db->fetch_object($rdet)) {
			$pay->detail[] = $d;
		}
		$db->free($rdet);
	}
}
unset($pay);

// ── Stats ─────────────────────────────────────────────────────────────────────
$nb_total      = count($payments);
$nb_rapproche  = 0;
$nb_non_rapp   = 0;
$total_montant = 0;
$total_rapproche = 0;
foreach ($payments as $p) {
	$total_montant += $p->montant;
	if ($p->rapproche) { $nb_rapproche++; $total_rapproche += $p->montant; }
	else                 $nb_non_rapp++;
}

// ── Export CSV ────────────────────────────────────────────────────────────────
if ($export === 'csv') {
	$filename = 'helpy_paiements_' . $annee . '.csv';
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Cache-Control: no-cache');
	echo "\xEF\xBB\xBF";

	echo implode(';', ['Date paiement', 'Ref SEPA', 'Releve bancaire', 'Rapproche',
	                    'Tiers', 'Pack', 'N° facture', 'Montant paye (EUR)']) . "\r\n";

	foreach ($payments as $pay) {
		$date_p = $pay->date_paiement ? date('d/m/Y', strtotime($pay->date_paiement)) : '';
		$rapp   = $pay->rapproche ? 'Oui' : 'Non';
		foreach ($pay->detail as $d) {
			$line = [
				$date_p,
				$pay->ref_sepa,
				$pay->num_releve,
				$rapp,
				$d->tiers_nom,
				$d->pack_label,
				$d->fac_ref,
				number_format((float)$d->montant_paye, 2, ',', ''),
			];
			echo implode(';', array_map(fn($v) => '"' . str_replace('"', '""', (string)$v) . '"', $line)) . "\r\n";
		}
	}
	$db->close(); exit;
}

// ── Couleurs packs (même palette que agebf_packs.php) ────────────────────────
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

// ── HTML ──────────────────────────────────────────────────────────────────────
llxHeader("", "Helpy — Suivi des paiements", '', '', 0, 0, '', '', '', 'mod-agebf page-compta');

print '
<style>
.bf-detail-row { display:none; }
.bf-detail-row.open { display:table-row; }
.bf-toggle-btn {
    cursor:pointer; border:none; background:none; padding:0 4px;
    color:#0d6efd; font-size:1.1em; line-height:1;
    transition: transform 0.15s;
}
.bf-toggle-btn.open { transform: rotate(90deg); }
.bf-detail-inner {
    background:#f8f9fa; border-left:3px solid #0d6efd;
    padding:8px 12px; border-radius:0 4px 4px 0;
}
.bf-pack-badge {
    display:inline-block; padding:1px 7px; border-radius:10px;
    font-size:0.78em; font-weight:bold; color:#fff; white-space:nowrap;
}
</style>
<script>
function bfToggle(id) {
    var rows = document.querySelectorAll(".bf-det-" + id);
    var btn  = document.getElementById("bf-btn-" + id);
    var open = btn.classList.contains("open");
    rows.forEach(function(r) { r.classList.toggle("open", !open); });
    btn.classList.toggle("open", !open);
}
</script>
';

print load_fiche_titre("Suivi des paiements SEPA", '', 'fa-exchange-alt');

if ($msg_ok)  print '<div class="ok">'    . $msg_ok  . '</div>';
if ($msg_err) print '<div class="error">' . $msg_err . '</div>';

// ── Bandeau stats ─────────────────────────────────────────────────────────────
$stats = [
	['label' => 'Virements ' . $annee,  'value' => $nb_total,                                      'color' => '#333'],
	['label' => 'Rapproch&eacute;s',     'value' => $nb_rapproche,                                  'color' => '#28a745'],
	['label' => 'Non rapproch&eacute;s', 'value' => $nb_non_rapp,                                   'color' => '#dc3545'],
	['label' => 'Total pay&eacute;',     'value' => price($total_montant) . '&nbsp;&euro;',         'color' => '#333'],
	['label' => 'Dont rapproch&eacute;', 'value' => price($total_rapproche) . '&nbsp;&euro;',       'color' => '#28a745'],
	['label' => 'Non rapproch&eacute;',  'value' => price($total_montant - $total_rapproche) . '&nbsp;&euro;', 'color' => '#dc3545'],
];
print '<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px">';
foreach ($stats as $st) {
	print '<div style="flex:1;min-width:120px;padding:10px 14px;background:#fff;border:1px solid #ddd;border-radius:6px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.06)">';
	print '<div style="font-size:1.3em;font-weight:bold;color:' . $st['color'] . ';line-height:1.2">' . $st['value'] . '</div>';
	print '<div style="font-size:0.8em;color:#777;margin-top:3px">' . $st['label'] . '</div>';
	print '</div>';
}
print '</div>';

// ── Formulaire filtres ────────────────────────────────────────────────────────
print '<div class="fichecenter">';
print '<form method="GET" action="' . $url_base . '">';
print '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px">';

// Filtre année
print '<label style="font-weight:bold">Ann&eacute;e :</label>';
print '<select name="annee" onchange="this.form.submit()" style="padding:4px 8px;border-radius:3px;border:1px solid #ccc">';
for ($y = (int)date('Y') + 1; $y >= 2024; $y--) {
	print '<option value="' . $y . '"' . ($y === $annee ? ' selected' : '') . '>' . $y . '</option>';
}
print '</select>';

// Filtre rapproché
$rapp_lib = ['tous' => 'Tous', 'oui' => 'Rapproch&eacute;s', 'non' => 'Non rapproch&eacute;s'];
print '<label style="font-weight:bold">Rapproch&eacute; :</label>';
print '<select name="s_rapproche" onchange="this.form.submit()" style="padding:4px 8px;border-radius:3px;border:1px solid #ccc">';
foreach ($rapp_lib as $val => $lib) {
	print '<option value="' . $val . '"' . ($val === $s_rapproche ? ' selected' : '') . '>' . $lib . '</option>';
}
print '</select>';

// Filtre référence SEPA
print '<label style="font-weight:bold">R&eacute;f. SEPA :</label>';
print '<input type="text" name="s_sepa" value="' . dol_escape_htmltag($s_sepa) . '" placeholder="T250901..." '
    . 'style="padding:4px 8px;border-radius:3px;border:1px solid #ccc;width:130px">';
print '<button type="submit" style="padding:4px 12px;background:#0d6efd;color:#fff;border:none;border-radius:3px;cursor:pointer">Filtrer</button>';

// Bouton effacer
if ($s_sepa !== '' || $s_rapproche !== 'tous') {
	print '<a href="' . $url_base . '?annee=' . $annee . '" style="padding:4px 12px;background:#dc3545;color:#fff;border-radius:3px;font-size:0.85em;text-decoration:none">&#x2715; Effacer</a>';
}

// Bouton export CSV
print '<a href="' . $url_base . '?annee=' . $annee . '&s_rapproche=' . urlencode($s_rapproche)
    . '&s_sepa=' . urlencode($s_sepa) . '&export=csv" '
    . 'style="margin-left:auto;padding:4px 14px;background:#28a745;color:#fff;border-radius:3px;font-size:0.85em;text-decoration:none;display:inline-flex;align-items:center;gap:6px">'
    . img_picto('', 'fa-download', '') . ' Export CSV</a>';

print '</div>';
print '</form>';

// ── Tableau ───────────────────────────────────────────────────────────────────
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th style="width:28px"></th>'; // bouton toggle
print '<th>Date paiement</th>';
print '<th>R&eacute;f&eacute;rence SEPA</th>';
print '<th>Relev&eacute; bancaire</th>';
print '<th class="center">Rapproch&eacute;</th>';
print '<th class="center">Factures</th>';
print '<th class="right">Montant</th>';
print '<th class="center">Action</th>';
print '</tr>';

if (empty($payments)) {
	print '<tr class="oddeven"><td colspan="8" class="center opacitymedium" style="padding:20px">Aucun paiement trouv&eacute;.</td></tr>';
}

foreach ($payments as $pay) {
	$pid = (int)$pay->pay_id;

	// Statut rapproché
	if ($pay->rapproche) {
		$rapp_badge = '<span style="color:#28a745;font-weight:bold">' . img_picto('', 'fa-check-circle', '') . ' Oui</span>';
	} else {
		$rapp_badge = '<span style="color:#dc3545;font-weight:bold">' . img_picto('', 'fa-times-circle', '') . ' Non</span>';
	}

	// Relevé bancaire — lien vers les écritures Dolibarr
	$releve_cell = $pay->num_releve !== ''
		? '<a href="' . DOL_URL_ROOT . '/compta/bank/bankentries_list.php?account=1&search_num_releve='
		  . urlencode($pay->num_releve) . '" target="_blank" style="color:#0d6efd">'
		  . dol_escape_htmltag($pay->num_releve) . '</a>'
		: '<span style="color:#aaa">—</span>';

	// Référence SEPA
	$sepa_cell = $pay->ref_sepa !== ''
		? '<span style="font-family:monospace;font-weight:600;font-size:1.05em">' . dol_escape_htmltag($pay->ref_sepa) . '</span>'
		: '<span style="color:#aaa">—</span>';

	// Lien vers la fiche paiement fournisseur
	$pay_url  = DOL_URL_ROOT . '/fourn/paiement/card.php?id=' . $pid;
	$fiche_btn = '<a href="' . $pay_url . '" style="padding:2px 9px;background:#6c757d;color:#fff;border-radius:3px;font-size:0.82em;text-decoration:none;display:inline-flex;align-items:center;gap:5px">'
	           . img_picto('', 'fa-eye', '') . ' Fiche</a>';

	// Bouton Rapprocher (si non rapproché) ou Dé-rapprocher (admin, si rapproché)
	if (!$pay->rapproche) {
		$rapp_form = '<form method="POST" action="' . $url_base . '" style="display:inline-flex;align-items:center;gap:5px;margin-top:4px">'
		           . '<input type="hidden" name="action"       value="rapprocher">'
		           . '<input type="hidden" name="token"        value="' . newToken() . '">'
		           . '<input type="hidden" name="pay_id"       value="' . $pid . '">'
		           . '<input type="hidden" name="annee"        value="' . $annee . '">'
		           . '<input type="hidden" name="s_rapproche"  value="' . dol_escape_htmltag($s_rapproche) . '">'
		           . '<input type="hidden" name="s_sepa"       value="' . dol_escape_htmltag($s_sepa) . '">'
		           . '<input type="text"   name="num_releve" placeholder="Ex: Belfius 8/32" '
		           .        'style="width:130px;padding:2px 6px;font-size:0.82em;border:1px solid #ccc;border-radius:3px" '
		           .        'title="Numero du releve bancaire Belfius">'
		           . '<button type="submit" style="padding:2px 10px;background:#28a745;color:#fff;border:none;border-radius:3px;font-size:0.82em;cursor:pointer;white-space:nowrap">'
		           . img_picto('', 'fa-check', '') . ' Rapprocher</button>'
		           . '</form>';
	} else {
		$rapp_form = '';
		if ($user->admin) {
			$rapp_form = '<form method="POST" action="' . $url_base . '" style="display:inline-flex;align-items:center;margin-top:4px" '
			           . 'onsubmit="return confirm(\'Annuler le rapprochement de ce paiement ?\')">'
			           . '<input type="hidden" name="action"      value="derapprocher">'
			           . '<input type="hidden" name="token"       value="' . newToken() . '">'
			           . '<input type="hidden" name="pay_id"      value="' . $pid . '">'
			           . '<input type="hidden" name="annee"       value="' . $annee . '">'
			           . '<input type="hidden" name="s_rapproche" value="' . dol_escape_htmltag($s_rapproche) . '">'
			           . '<input type="hidden" name="s_sepa"      value="' . dol_escape_htmltag($s_sepa) . '">'
			           . '<button type="submit" style="padding:2px 9px;background:#dc3545;color:#fff;border:none;border-radius:3px;font-size:0.78em;cursor:pointer;white-space:nowrap">'
			           . img_picto('', 'fa-undo', '') . ' Ann.</button>'
			           . '</form>';
		}
	}

	// Couleur de fond selon statut rapproché
	$bg = $pay->rapproche ? '' : ' style="background-color:#fff5f5"';

	print '<tr class="oddeven"' . $bg . '>';
	print '<td style="text-align:center;padding:6px 4px">';
	if (!empty($pay->detail)) {
		print '<button type="button" id="bf-btn-' . $pid . '" class="bf-toggle-btn" onclick="bfToggle(' . $pid . ')" title="Voir le d&eacute;tail">&#9658;</button>';
	}
	print '</td>';
	print '<td>' . dol_print_date($db->jdate($pay->date_paiement), 'day') . '</td>';
	print '<td>' . $sepa_cell . '</td>';
	print '<td>' . $releve_cell . '</td>';
	print '<td class="center">' . $rapp_badge . '</td>';
	print '<td class="center"><span style="font-weight:bold">' . (int)$pay->nb_factures . '</span></td>';
	print '<td class="right" style="font-weight:bold;white-space:nowrap">' . price($pay->montant) . '&nbsp;&euro;</td>';
	print '<td class="center" style="white-space:nowrap">';
	print $fiche_btn;
	print $rapp_form;
	print '</td>';
	print '</tr>';

	// ── Lignes de détail (cachées par défaut) ─────────────────────────────────
	if (!empty($pay->detail)) {
		print '<tr class="bf-detail-row bf-det-' . $pid . '"' . $bg . '>';
		print '<td colspan="8" style="padding:0 16px 12px 36px' . ($pay->rapproche ? '' : ';background-color:#fff5f5') . '">';
		print '<div class="bf-detail-inner">';
		print '<table style="width:100%;border-collapse:collapse;font-size:0.9em">';
		print '<tr style="color:#666;border-bottom:1px solid #ddd">';
		print '<th style="text-align:left;padding:4px 8px;font-weight:600">Tiers</th>';
		print '<th style="text-align:left;padding:4px 8px;font-weight:600">Pack</th>';
		print '<th style="text-align:left;padding:4px 8px;font-weight:600">N&deg; facture</th>';
		print '<th style="text-align:right;padding:4px 8px;font-weight:600">Montant pay&eacute;</th>';
		print '</tr>';

		foreach ($pay->detail as $d) {
			$pack_color = $pack_colors[$d->pack_ref] ?? '#6c757d';
			$pack_badge = '<span class="bf-pack-badge" style="background:' . $pack_color . '">'
			            . dol_escape_htmltag($d->pack_label) . '</span>';

			$fac_url  = DOL_URL_ROOT . '/fourn/facture/card.php?id=' . (int)$d->fac_id;
			$fac_link = '<a href="' . $fac_url . '" style="color:#0d6efd">' . dol_escape_htmltag($d->fac_ref) . '</a>';

			$tiers_link = '<a href="' . DOL_URL_ROOT . '/societe/card.php?socid=' . (int)$d->soc_id . '">'
			            . dol_escape_htmltag($d->tiers_nom) . '</a>';

			print '<tr style="border-bottom:1px solid #eee">';
			print '<td style="padding:5px 8px">' . $tiers_link . '</td>';
			print '<td style="padding:5px 8px">' . $pack_badge . '</td>';
			print '<td style="padding:5px 8px">' . $fac_link . '</td>';
			print '<td style="padding:5px 8px;text-align:right;font-weight:bold;white-space:nowrap">'
			    . price($d->montant_paye) . '&nbsp;&euro;</td>';
			print '</tr>';
		}

		print '</table>';
		print '</div>';
		print '</td></tr>';
	}
}

print '</table>';

// ── Légende ───────────────────────────────────────────────────────────────────
print '<div style="margin-top:14px;font-size:0.85em;color:#666;display:flex;gap:18px;flex-wrap:wrap;align-items:center">';
print '<b>L&eacute;gende :</b>';
print '<span>' . img_picto('', 'fa-check-circle', 'style="color:#28a745"') . ' Rapproch&eacute; = &eacute;criture bancaire valid&eacute;e</span>';
print '<span>' . img_picto('', 'fa-times-circle', 'style="color:#dc3545"') . ' Non rapproch&eacute; = saisir le n&deg; relev&eacute; Belfius puis cliquer <b>Rapprocher</b></span>';
print '<span>&#9658; = voir le d&eacute;tail des packs couverts par le virement</span>';
if ($user->admin) {
    print '<span style="color:#dc3545">Bouton <b>Ann.</b> (admin uniquement) = annuler un rapprochement</span>';
}
print '</div>';

print '</div>'; // fichecenter

llxFooter();
$db->close();
