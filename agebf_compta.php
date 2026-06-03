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
$open_fac    = (int) GETPOST('open_fac',     'int');

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
if (GETPOSTISSET('belfius_ok')) {
	$nb_b = (int) GETPOST('belfius_ok', 'int');
	$msg_ok = $nb_b > 0
		? '<b>' . $nb_b . '</b> virement(s) rapproch&eacute;(s) automatiquement depuis le relev&eacute; Belfius.'
		: 'Aucun virement rapproch&eacute; (aucune ligne coch&eacute;e).';
}

// ── Action : rapprocher un paiement ──────────────────────────────────────────
if ($action === 'rapprocher' && !empty($_POST['token'])) {
	$pay_id    = (int) GETPOST('pay_id',    'int');
	$num_rel   = trim(GETPOST('num_releve', 'alphanohtml'));

	if ($pay_id > 0) {
		// Trouver l'écriture bancaire liée à ce paiement fournisseur
		$sql_bk = "SELECT b.rowid FROM " . MAIN_DB_PREFIX . "bank b"
		        . " INNER JOIN " . MAIN_DB_PREFIX . "paiementfourn pay ON pay.fk_bank = b.rowid"
		        . " WHERE pay.rowid = " . $pay_id . " LIMIT 1";
		$rbk = $db->query($sql_bk);
		if ($rbk && ($obk = $db->fetch_object($rbk))) {
			$bank_id = (int) $obk->rowid;
			$sql_up  = "UPDATE " . MAIN_DB_PREFIX . "bank"
			         . " SET rappro = 1"
			         . ($num_rel !== '' ? ", num_releve = '" . $db->escape($num_rel) . "'" : "")
			         . " WHERE rowid = " . $bank_id;
			if ($db->query($sql_up)) {
				header('Location: ' . $url_base . '?annee=' . $annee . '&s_rapproche=' . urlencode($s_rapproche)
				     . '&s_sepa=' . urlencode($s_sepa) . '&open_fac=' . (int) GETPOST('fac_id', 'int')
				     . '&rapproche_ok=1&releve_ok=' . urlencode($num_rel));
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
if ($action === 'derapprocher' && $user->admin && !empty($_POST['token'])) {
	$pay_id = (int) GETPOST('pay_id', 'int');

	if ($pay_id > 0) {
		$sql_bk = "SELECT b.rowid FROM " . MAIN_DB_PREFIX . "bank b"
		        . " INNER JOIN " . MAIN_DB_PREFIX . "paiementfourn pay ON pay.fk_bank = b.rowid"
		        . " WHERE pay.rowid = " . $pay_id . " LIMIT 1";
		$rbk = $db->query($sql_bk);
		if ($rbk && ($obk = $db->fetch_object($rbk))) {
			$sql_up = "UPDATE " . MAIN_DB_PREFIX . "bank SET rappro = 0 WHERE rowid = " . (int)$obk->rowid;
			if ($db->query($sql_up)) {
				header('Location: ' . $url_base . '?annee=' . $annee . '&s_rapproche=' . urlencode($s_rapproche)
				     . '&s_sepa=' . urlencode($s_sepa) . '&open_fac=' . (int) GETPOST('fac_id', 'int')
				     . '&derapproche_ok=1');
				exit;
			} else {
				$msg_err = 'Erreur lors de l\'annulation : ' . $db->lasterror();
			}
		}
	}
}

// ── Action : appliquer les rapprochements validés de l'import Belfius ─────────
if ($action === 'apply_belfius' && !empty($_POST['token'])) {
	$sel_pay = isset($_POST['sel_pay'])    && is_array($_POST['sel_pay'])    ? $_POST['sel_pay']    : [];
	$sel_rel = isset($_POST['sel_releve']) && is_array($_POST['sel_releve']) ? $_POST['sel_releve'] : [];
	$apply   = isset($_POST['apply'])      && is_array($_POST['apply'])      ? $_POST['apply']      : [];
	$nb_ok   = 0;
	foreach ($apply as $idx => $on) {
		$pid = isset($sel_pay[$idx]) ? (int) $sel_pay[$idx] : 0;
		$rel = isset($sel_rel[$idx]) ? trim((string) $sel_rel[$idx]) : '';
		if ($pid <= 0) continue;
		$rb = $db->query("SELECT b.rowid FROM " . MAIN_DB_PREFIX . "bank b"
		    . " INNER JOIN " . MAIN_DB_PREFIX . "paiementfourn pay ON pay.fk_bank = b.rowid"
		    . " WHERE pay.rowid = " . $pid . " LIMIT 1");
		if ($rb && ($ob = $db->fetch_object($rb))) {
			$up = "UPDATE " . MAIN_DB_PREFIX . "bank SET rappro = 1"
			    . ($rel !== '' ? ", num_releve = '" . $db->escape($rel) . "'" : "")
			    . " WHERE rowid = " . (int) $ob->rowid;
			if ($db->query($up)) $nb_ok++;
		}
	}
	header('Location: ' . $url_base . '?annee=' . $annee . '&belfius_ok=' . $nb_ok);
	exit;
}

// ── Helpers : import / parsing du relevé Belfius ──────────────────────────────
function bf_norm($s) {
	$s = mb_strtolower(trim((string) $s), 'UTF-8');
	$s = strtr($s, [
		'à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
		'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c',
	]);
	return preg_replace('/\s+/', ' ', $s);
}
function bf_parse_belfius($path) {
	$raw = @file_get_contents($path);
	if ($raw === false || $raw === '') return null;
	if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
	$lines = preg_split('/\r\n|\r|\n/', $raw);
	$hdr = null; $map = []; $out = [];
	foreach ($lines as $ln) {
		if (trim($ln) === '') continue;
		$cells = str_getcsv($ln, ';', '"');
		if ($hdr === null) {
			$norm   = array_map('bf_norm', $cells);
			$joined = implode('|', $norm);
			if (strpos($joined, 'montant') !== false || strpos($joined, 'bedrag') !== false) {
				$hdr = $norm;
				foreach ($hdr as $i => $h) {
					if (strpos($h, 'comptabilisation') !== false || strpos($h, 'boekingsdatum') !== false) $map['date'] = $i;
					elseif (strpos($h, 'extrait') !== false || strpos($h, 'afschrift') !== false)         $map['extrait'] = $i;
					elseif (strpos($h, 'montant') !== false || strpos($h, 'bedrag') !== false)            $map['montant'] = $i;
					elseif (strpos($h, 'communication') !== false || strpos($h, 'mededeling') !== false)  $map['comm'] = $i;
					elseif ((strpos($h, 'contrepartie') !== false || strpos($h, 'tegenpartij') !== false) && strpos($h, 'compte') === false && strpos($h, 'rekening') === false) $map['name'] = $i;
				}
			}
			continue;
		}
		if (count($cells) < 3 || !isset($map['montant'])) continue;
		$gc = function ($k) use ($cells, $map) { return (isset($map[$k]) && isset($cells[$map[$k]])) ? trim($cells[$map[$k]]) : ''; };
		$mraw = $gc('montant');
		if ($mraw === '' || !preg_match('/\d/', $mraw)) continue;
		$mf  = (float) str_replace(',', '.', str_replace('.', '', $mraw));
		$dd  = $gc('date'); $diso = '';
		if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $dd, $m)) $diso = $m[3] . '-' . $m[2] . '-' . $m[1];
		$out[] = [
			'date_disp' => $dd,
			'date_iso'  => $diso,
			'extrait'   => $gc('extrait'),
			'montant'   => abs($mf),
			'mont_disp' => $mraw,
			'name'      => $gc('name'),
			'comm'      => $gc('comm'),
		];
	}
	return $out;
}

// ── Requête 1 : factures fournisseurs ayant au moins un paiement (année) ─────
$sql = "SELECT DISTINCT
    f.rowid       AS fac_id,
    f.ref         AS fac_ref,
    f.total_ttc   AS total_ttc,
    f.datef       AS datef,
    s.rowid       AS soc_id,
    s.nom         AS tiers_nom
FROM " . MAIN_DB_PREFIX . "facture_fourn f
JOIN " . MAIN_DB_PREFIX . "societe s
       ON s.rowid = f.fk_soc
JOIN " . MAIN_DB_PREFIX . "paiementfourn_facturefourn pff
       ON pff.fk_facturefourn = f.rowid
WHERE f.entity = " . (int)$conf->entity . "
  AND YEAR(f.datef) = " . (int)$annee . "
ORDER BY s.nom ASC, f.ref ASC";

$resql = $db->query($sql);
if (!$resql) {
	if ($export !== 'csv') { llxHeader(); }
	print '<div class="error">' . $db->lasterror() . '</div>';
	if ($export !== 'csv') { llxFooter(); }
	$db->close(); exit;
}

$factures = [];
$fac_ids  = [];
while ($obj = $db->fetch_object($resql)) {
	$obj->payments  = [];
	$obj->packs     = [];
	$obj->deja_paye = 0.0;
	$factures[(int)$obj->fac_id] = $obj;
	$fac_ids[] = (int)$obj->fac_id;
}
$db->free($resql);

// ── Requête 2 : tous les virements (paiements) de ces factures ───────────────
if (!empty($fac_ids)) {
	$in = implode(',', array_map('intval', $fac_ids));

	$sql_pay = "SELECT
	    pff.fk_facturefourn                    AS fac_id,
	    pay.rowid                              AS pay_id,
	    pay.datep                              AS date_paiement,
	    COALESCE(pay.num_paiement, '')        AS ref_sepa,
	    pff.amount                             AS montant_paye,
	    COALESCE(b.num_releve, '')            AS num_releve,
	    COALESCE(b.rowid, 0)                  AS bank_id,
	    COALESCE(b.rappro, 0)                 AS rapproche
	FROM " . MAIN_DB_PREFIX . "paiementfourn_facturefourn pff
	JOIN " . MAIN_DB_PREFIX . "paiementfourn pay
	       ON pay.rowid = pff.fk_paiementfourn
	LEFT JOIN " . MAIN_DB_PREFIX . "bank b
	       ON b.rowid = pay.fk_bank
	WHERE pff.fk_facturefourn IN (" . $in . ")
	ORDER BY pay.datep ASC, pay.rowid ASC";

	$rpay = $db->query($sql_pay);
	if ($rpay) {
		while ($p = $db->fetch_object($rpay)) {
			$fid = (int)$p->fac_id;
			if (isset($factures[$fid])) {
				$factures[$fid]->payments[] = $p;
				$factures[$fid]->deja_paye += (float)$p->montant_paye;
			}
		}
		$db->free($rpay);
	}

	// ── Requête 3 : packs (produits) de ces factures ─────────────────────────
	$sql_pk = "SELECT DISTINCT
	    fd.fk_facture_fourn AS fac_id,
	    p.ref               AS pack_ref,
	    p.label             AS pack_label
	FROM " . MAIN_DB_PREFIX . "facture_fourn_det fd
	JOIN " . MAIN_DB_PREFIX . "product p
	       ON p.rowid = fd.fk_product
	WHERE fd.fk_facture_fourn IN (" . $in . ")";

	$rpk = $db->query($sql_pk);
	if ($rpk) {
		while ($pk = $db->fetch_object($rpk)) {
			$fid = (int)$pk->fac_id;
			if (isset($factures[$fid])) {
				$factures[$fid]->packs[] = $pk;
			}
		}
		$db->free($rpk);
	}
}

// ── Calculs par facture + filtres PHP (rapproché / réf SEPA) ─────────────────
$rows = [];
foreach ($factures as $f) {
	$f->restant       = (float)$f->total_ttc - $f->deja_paye;
	$f->nb_versements = count($f->payments);
	$f->nb_rapproche  = 0;
	foreach ($f->payments as $p) { if ($p->rapproche) $f->nb_rapproche++; }
	$f->nb_non_rapp   = $f->nb_versements - $f->nb_rapproche;

	// Filtre réf SEPA : garder si un virement correspond
	if ($s_sepa !== '') {
		$match = false;
		foreach ($f->payments as $p) {
			if (stripos($p->ref_sepa, $s_sepa) !== false) { $match = true; break; }
		}
		if (!$match) continue;
	}

	// Filtre rapproché
	if ($s_rapproche === 'non' && $f->nb_non_rapp === 0) continue;          // que les factures avec virement(s) non rapproché(s)
	if ($s_rapproche === 'oui' && ($f->nb_non_rapp > 0 || $f->nb_versements === 0)) continue; // que les factures entièrement rapprochées

	$rows[] = $f;
}

// ── Stats ─────────────────────────────────────────────────────────────────────
$nb_factures   = count($rows);
$total_attendu = 0.0;
$total_paye    = 0.0;
$total_restant = 0.0;
$nb_vir_total  = 0;
$nb_vir_rapp   = 0;
foreach ($rows as $f) {
	$total_attendu += (float)$f->total_ttc;
	$total_paye    += $f->deja_paye;
	$total_restant += $f->restant;
	$nb_vir_total  += $f->nb_versements;
	$nb_vir_rapp   += $f->nb_rapproche;
}
$nb_vir_non = $nb_vir_total - $nb_vir_rapp;

// ── Export CSV (1 ligne par virement, avec contexte facture) ─────────────────
if ($export === 'csv') {
	$filename = 'helpy_rapprochement_' . $annee . '.csv';
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Cache-Control: no-cache');
	echo "\xEF\xBB\xBF";

	echo implode(';', ['Tiers', 'N° facture', 'Pack', 'Montant attendu (EUR)', 'Deja paye (EUR)', 'Restant (EUR)',
	                    'Date virement', 'Ref SEPA', 'Montant virement (EUR)', 'Releve bancaire', 'Rapproche']) . "\r\n";

	foreach ($rows as $f) {
		$packs = [];
		foreach ($f->packs as $pk) $packs[] = $pk->pack_label;
		$pack_str = implode(' / ', $packs);

		if (empty($f->payments)) continue;
		foreach ($f->payments as $p) {
			$date_p = $p->date_paiement ? date('d/m/Y', strtotime($p->date_paiement)) : '';
			$line = [
				$f->tiers_nom,
				$f->fac_ref,
				$pack_str,
				number_format((float)$f->total_ttc, 2, ',', ''),
				number_format((float)$f->deja_paye, 2, ',', ''),
				number_format((float)$f->restant, 2, ',', ''),
				$date_p,
				$p->ref_sepa,
				number_format((float)$p->montant_paye, 2, ',', ''),
				$p->num_releve,
				$p->rapproche ? 'Oui' : 'Non',
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
.bf-prog-wrap { background:#e9ecef; border-radius:6px; height:8px; width:90px; overflow:hidden; display:inline-block; vertical-align:middle; }
.bf-prog-bar  { background:#28a745; height:8px; }
</style>
<script>
function bfToggle(id) {
    var rows = document.querySelectorAll(".bf-det-" + id);
    var btn  = document.getElementById("bf-btn-" + id);
    var open = btn.classList.contains("open");
    rows.forEach(function(r) { r.classList.toggle("open", !open); });
    btn.classList.toggle("open", !open);
}
function bfToggleAll(openAll) {
    document.querySelectorAll(".bf-toggle-btn").forEach(function(btn) {
        var id = btn.id.replace("bf-btn-", "");
        document.querySelectorAll(".bf-det-" + id).forEach(function(r) { r.classList.toggle("open", openAll); });
        btn.classList.toggle("open", openAll);
    });
}
' . ($open_fac > 0 ? 'document.addEventListener("DOMContentLoaded", function() { var el = document.getElementById("fac-' . $open_fac . '"); if (el) el.scrollIntoView({block:"center"}); });' : '') . '
</script>
';

print load_fiche_titre("Suivi des paiements SEPA — Rapprochement par facture", '', 'fa-exchange-alt');

if ($msg_ok)  print '<div class="ok">'    . $msg_ok  . '</div>';
if ($msg_err) print '<div class="error">' . $msg_err . '</div>';

// ── Bandeau stats ─────────────────────────────────────────────────────────────
$stats = [
	['label' => 'Factures ' . $annee,    'value' => $nb_factures,                                   'color' => '#333'],
	['label' => 'Total attendu',         'value' => price($total_attendu) . '&nbsp;&euro;',          'color' => '#333'],
	['label' => 'D&eacute;j&agrave; pay&eacute;', 'value' => price($total_paye) . '&nbsp;&euro;',    'color' => '#28a745'],
	['label' => 'Restant',               'value' => price($total_restant) . '&nbsp;&euro;',          'color' => '#dc3545'],
	['label' => 'Virements &agrave; rapprocher', 'value' => $nb_vir_non,                             'color' => '#dc3545'],
	['label' => 'Virements rapproch&eacute;s',   'value' => $nb_vir_rapp,                            'color' => '#28a745'],
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
$rapp_lib = ['tous' => 'Toutes', 'oui' => 'Enti&egrave;rement rapproch&eacute;es', 'non' => 'Avec virement &agrave; rapprocher'];
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

// Boutons déplier / replier tout
print '<button type="button" onclick="bfToggleAll(true)" style="padding:4px 10px;background:#6c757d;color:#fff;border:none;border-radius:3px;font-size:0.85em;cursor:pointer">D&eacute;plier tout</button>';
print '<button type="button" onclick="bfToggleAll(false)" style="padding:4px 10px;background:#6c757d;color:#fff;border:none;border-radius:3px;font-size:0.85em;cursor:pointer">Replier tout</button>';

// Bouton export CSV
print '<a href="' . $url_base . '?annee=' . $annee . '&s_rapproche=' . urlencode($s_rapproche)
    . '&s_sepa=' . urlencode($s_sepa) . '&export=csv" '
    . 'style="margin-left:auto;padding:4px 14px;background:#28a745;color:#fff;border-radius:3px;font-size:0.85em;text-decoration:none;display:inline-flex;align-items:center;gap:6px">'
    . img_picto('', 'fa-download', '') . ' Export CSV</a>';

print '</div>';
print '</form>';

// ── Bouton import relevé Belfius ──────────────────────────────────────────────
print '<form method="POST" action="' . $url_base . '" enctype="multipart/form-data" style="display:flex;align-items:center;gap:8px;margin-bottom:16px;padding:10px 14px;background:#eef4ff;border:1px solid #cfe0ff;border-radius:6px">';
print '<input type="hidden" name="token"  value="' . newToken() . '">';
print '<input type="hidden" name="action" value="import_belfius">';
print '<input type="hidden" name="annee"  value="' . $annee . '">';
print '<label style="font-weight:bold;color:#0d4dad">' . img_picto('', 'fa-university', '') . ' Rapprochement automatique &mdash; importer le relev&eacute; Belfius (CSV) :</label>';
print '<input type="file" name="belfius_file" accept=".csv,text/csv" required style="font-size:0.85em">';
print '<button type="submit" style="padding:5px 14px;background:#0d6efd;color:#fff;border:none;border-radius:3px;cursor:pointer;white-space:nowrap">' . img_picto('', 'fa-upload', '') . ' Analyser le relev&eacute;</button>';
print '</form>';

// ── Écran de validation de l'import Belfius ──────────────────────────────────
if ($action === 'import_belfius') {
	$parsed = null;
	if (isset($_FILES['belfius_file']) && $_FILES['belfius_file']['error'] === UPLOAD_ERR_OK) {
		$parsed = bf_parse_belfius($_FILES['belfius_file']['tmp_name']);
	}
	if (empty($parsed)) {
		print '<div class="error">Impossible de lire le fichier, ou aucune transaction trouv&eacute;e. V&eacute;rifiez que c\'est bien un export CSV Belfius.</div>';
		print '<a href="' . $url_base . '?annee=' . $annee . '" style="padding:5px 14px;background:#6c757d;color:#fff;border-radius:3px;text-decoration:none">&laquo; Retour</a>';
		llxFooter(); $db->close(); exit;
	}

	// Virements non rapprochés (base de correspondance)
	$libres = [];
	foreach ($factures as $f) {
		foreach ($f->payments as $p) {
			if (!$p->rapproche) {
				$libres[] = (object) [
					'pay_id'  => (int) $p->pay_id,
					'montant' => (float) $p->montant_paye,
					'date'    => dol_print_date($db->jdate($p->date_paiement), '%Y-%m-%d'),
					'tiers'   => $f->tiers_nom,
					'fac_ref' => $f->fac_ref,
					'sepa'    => $p->ref_sepa,
				];
			}
		}
	}

	// Matching ligne par ligne
	$matches = []; $cnt = ['sur' => 0, 'probable' => 0, 'ambigu' => 0, 'nomatch' => 0];
	foreach ($parsed as $line) {
		$cands = [];
		$commn = bf_norm($line['comm']);
		foreach ($libres as $v) {
			if (abs($v->montant - $line['montant']) > 0.005) continue;
			$score = 0;
			if ($v->fac_ref !== '' && strpos($commn, bf_norm($v->fac_ref)) !== false) $score += 100;
			if ($v->sepa   !== '' && strpos($commn, bf_norm($v->sepa))    !== false) $score += 100;
			if ($line['date_iso'] !== '' && $line['date_iso'] === $v->date)          $score += 50;
			if ($line['name'] !== '' && bf_norm($line['name']) === bf_norm($v->tiers)) $score += 30;
			$cands[] = ['v' => $v, 'score' => $score];
		}
		usort($cands, function ($a, $b) { return $b['score'] - $a['score']; });
		$conf = 'nomatch'; $best = null;
		if (!empty($cands)) {
			$top = $cands[0]['score']; $ties = 0;
			foreach ($cands as $c) if ($c['score'] === $top) $ties++;
			$best = $cands[0]['v'];
			if      ($top >= 100 && $ties === 1) $conf = 'sur';
			elseif  ($top >= 50  && $ties === 1) $conf = 'probable';
			elseif  (count($cands) === 1)        $conf = 'probable';
			else                                  $conf = 'ambigu';
		}
		$cnt[$conf]++;
		$matches[] = ['line' => $line, 'cands' => $cands, 'conf' => $conf, 'best' => $best];
	}

	$badge = [
		'sur'      => '<span style="color:#28a745;font-weight:bold">' . img_picto('', 'fa-check-circle', '') . ' S&ucirc;r</span>',
		'probable' => '<span style="color:#fd7e14;font-weight:bold">' . img_picto('', 'fa-question-circle', '') . ' Probable</span>',
		'ambigu'   => '<span style="color:#dc3545;font-weight:bold">' . img_picto('', 'fa-exclamation-triangle', '') . ' Ambigu</span>',
		'nomatch'  => '<span style="color:#999">&mdash; Aucun virement</span>',
	];

	print '<div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:14px;margin-bottom:16px">';
	print '<h3 style="margin:0 0 10px">' . img_picto('', 'fa-university', '') . ' V&eacute;rification du relev&eacute; Belfius &mdash; ' . count($matches) . ' transaction(s)</h3>';
	print '<p style="margin:0 0 12px;color:#555">' . $cnt['sur'] . ' s&ucirc;r(s) &middot; ' . $cnt['probable'] . ' probable(s) &middot; <span style="color:#dc3545">' . $cnt['ambigu'] . ' ambigu(s)</span> &middot; ' . $cnt['nomatch'] . ' sans correspondance. '
	    . '<b>Coche les lignes &agrave; rapprocher, ajuste les virements si besoin, puis valide.</b></p>';

	print '<form method="POST" action="' . $url_base . '">';
	print '<input type="hidden" name="token"  value="' . newToken() . '">';
	print '<input type="hidden" name="action" value="apply_belfius">';
	print '<input type="hidden" name="annee"  value="' . $annee . '">';

	print '<table class="noborder" style="width:100%;border-collapse:collapse">';
	print '<tr class="liste_titre">';
	print '<th style="text-align:center"><input type="checkbox" id="bf-chk-all" checked onclick="var a=this.checked;document.querySelectorAll(\'.bf-apply\').forEach(function(c){c.checked=a});"></th>';
	print '<th>Date</th><th>N&deg; extrait</th><th class="right">Montant</th><th>Contrepartie</th><th>Communication</th><th>Confiance</th><th>Virement &agrave; rapprocher</th>';
	print '</tr>';

	foreach ($matches as $idx => $mm) {
		$ln = $mm['line']; $conf = $mm['conf'];
		$rowbg = ($conf === 'ambigu') ? ' style="background:#fff5f5"' : (($conf === 'nomatch') ? ' style="background:#f6f6f6;color:#999"' : '');
		print '<tr class="oddeven"' . $rowbg . '>';

		// checkbox
		print '<td style="text-align:center">';
		if ($mm['best'] !== null) {
			$checked = ($conf === 'sur' || $conf === 'probable') ? ' checked' : '';
			print '<input type="checkbox" class="bf-apply" name="apply[' . $idx . ']" value="1"' . $checked . '>';
		}
		print '</td>';

		print '<td style="white-space:nowrap">' . dol_escape_htmltag($ln['date_disp']) . '</td>';
		print '<td style="white-space:nowrap"><b>' . dol_escape_htmltag($ln['extrait']) . '</b></td>';
		print '<td class="right" style="white-space:nowrap;font-weight:bold">' . dol_escape_htmltag($ln['mont_disp']) . '&nbsp;&euro;</td>';
		print '<td>' . dol_escape_htmltag($ln['name']) . '</td>';
		print '<td style="font-size:0.9em">' . dol_escape_htmltag($ln['comm']) . '</td>';
		print '<td style="white-space:nowrap">' . $badge[$conf] . '</td>';

		// select virement + hidden extrait
		print '<td>';
		print '<input type="hidden" name="sel_releve[' . $idx . ']" value="' . dol_escape_htmltag($ln['extrait']) . '">';
		if (!empty($mm['cands'])) {
			print '<select name="sel_pay[' . $idx . ']" style="padding:3px 6px;border:1px solid #ccc;border-radius:3px;max-width:380px">';
			print '<option value="">&mdash; ne pas rapprocher &mdash;</option>';
			foreach ($mm['cands'] as $c) {
				$v = $c['v'];
				$sel = ($mm['best'] && $v->pay_id === $mm['best']->pay_id) ? ' selected' : '';
				$lbl = $v->fac_ref . ' · ' . $v->tiers . ' · ' . dol_print_date(dol_stringtotime($v->date), 'day') . ' · ' . price($v->montant) . ' €';
				print '<option value="' . $v->pay_id . '"' . $sel . '>' . dol_escape_htmltag($lbl) . '</option>';
			}
			print '</select>';
		} else {
			print '<span style="color:#999">aucun virement non point&eacute; &agrave; ce montant</span>';
		}
		print '</td>';
		print '</tr>';
	}
	print '</table>';

	print '<div style="margin-top:14px;display:flex;gap:10px">';
	print '<button type="submit" style="padding:7px 18px;background:#28a745;color:#fff;border:none;border-radius:3px;cursor:pointer;font-weight:bold">' . img_picto('', 'fa-check', '') . ' Valider et rapprocher les lignes coch&eacute;es</button>';
	print '<a href="' . $url_base . '?annee=' . $annee . '" style="padding:7px 18px;background:#6c757d;color:#fff;border-radius:3px;text-decoration:none">Annuler</a>';
	print '</div>';
	print '</form>';
	print '</div>';

	llxFooter(); $db->close(); exit;
}

// ── Tableau (1 ligne par facture) ─────────────────────────────────────────────
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th style="width:28px"></th>'; // bouton toggle
print '<th>Tiers</th>';
print '<th>Facture / Pack</th>';
print '<th class="right">Montant attendu</th>';
print '<th class="right">D&eacute;j&agrave; pay&eacute;</th>';
print '<th class="right">Restant</th>';
print '<th class="center">Virements</th>';
print '<th class="center">Rapprochement</th>';
print '</tr>';

if (empty($rows)) {
	print '<tr class="oddeven"><td colspan="8" class="center opacitymedium" style="padding:20px">Aucune facture trouv&eacute;e.</td></tr>';
}

foreach ($rows as $f) {
	$fid = (int)$f->fac_id;

	// Statut paiement
	$is_solde = ($f->restant < 0.01);
	if ($is_solde) {
		$statut_paie = '<span style="color:#28a745;font-weight:bold">Sold&eacute;e</span>';
	} else {
		$statut_paie = '<span style="color:#fd7e14;font-weight:bold">Partielle</span>';
	}

	// Statut rapprochement
	if ($f->nb_versements > 0 && $f->nb_non_rapp === 0) {
		$rapp_cell = '<span style="color:#28a745;font-weight:bold">' . img_picto('', 'fa-check-circle', '') . ' ' . $f->nb_rapproche . '/' . $f->nb_versements . '</span>';
	} else {
		$rapp_cell = '<span style="color:#dc3545;font-weight:bold">' . img_picto('', 'fa-times-circle', '') . ' ' . $f->nb_rapproche . '/' . $f->nb_versements . '</span>';
	}

	// Packs badges
	$pack_html = '';
	foreach ($f->packs as $pk) {
		$pack_color = $pack_colors[$pk->pack_ref] ?? '#6c757d';
		$pack_html .= '<span class="bf-pack-badge" style="background:' . $pack_color . ';margin-right:4px">'
		            . dol_escape_htmltag($pk->pack_label) . '</span>';
	}

	// Lien facture + tiers
	$fac_url    = DOL_URL_ROOT . '/fourn/facture/card.php?facid=' . $fid;
	$fac_link   = '<a href="' . $fac_url . '" style="color:#0d6efd;font-weight:600">' . dol_escape_htmltag($f->fac_ref) . '</a>';
	$tiers_link = '<a href="' . DOL_URL_ROOT . '/societe/card.php?socid=' . (int)$f->soc_id . '">' . dol_escape_htmltag($f->tiers_nom) . '</a>';

	// Barre de progression paiement
	$pct = ((float)$f->total_ttc > 0) ? min(100, round($f->deja_paye / (float)$f->total_ttc * 100)) : 0;
	$prog = '<span class="bf-prog-wrap" title="' . $pct . '% pay&eacute;"><span class="bf-prog-bar" style="width:' . $pct . '%"></span></span>';

	// Couleur de fond si virement(s) à rapprocher
	$bg = ($f->nb_non_rapp > 0) ? ' style="background-color:#fff5f5"' : '';

	$is_open  = ($open_fac === $fid);
	$open_cls = $is_open ? ' open' : '';

	print '<tr class="oddeven"' . $bg . ' id="fac-' . $fid . '">';
	print '<td style="text-align:center;padding:6px 4px">';
	if (!empty($f->payments)) {
		print '<button type="button" id="bf-btn-' . $fid . '" class="bf-toggle-btn' . $open_cls . '" onclick="bfToggle(' . $fid . ')" title="Voir les virements">&#9658;</button>';
	}
	print '</td>';
	print '<td>' . $tiers_link . '</td>';
	print '<td>' . $fac_link . '<div style="margin-top:3px">' . $pack_html . '</div></td>';
	print '<td class="right" style="white-space:nowrap">' . price($f->total_ttc) . '&nbsp;&euro;</td>';
	print '<td class="right" style="white-space:nowrap;color:#28a745;font-weight:600">' . price($f->deja_paye) . '&nbsp;&euro;<br>' . $prog . '</td>';
	print '<td class="right" style="white-space:nowrap;font-weight:bold;color:' . ($is_solde ? '#28a745' : '#dc3545') . '">' . price($f->restant) . '&nbsp;&euro;</td>';
	print '<td class="center"><span style="font-weight:bold">' . (int)$f->nb_versements . '</span><div style="font-size:0.8em">' . $statut_paie . '</div></td>';
	print '<td class="center" style="white-space:nowrap">' . $rapp_cell . '</td>';
	print '</tr>';

	// ── Lignes de détail : un virement par ligne, avec action Rapprocher ──────
	if (!empty($f->payments)) {
		print '<tr class="bf-detail-row bf-det-' . $fid . $open_cls . '"' . $bg . '>';
		print '<td colspan="8" style="padding:0 16px 12px 36px' . ($f->nb_non_rapp > 0 ? ';background-color:#fff5f5' : '') . '">';
		print '<div class="bf-detail-inner">';
		print '<table style="width:100%;border-collapse:collapse;font-size:0.9em">';
		print '<tr style="color:#666;border-bottom:1px solid #ddd">';
		print '<th style="text-align:left;padding:4px 8px;font-weight:600">Date</th>';
		print '<th style="text-align:left;padding:4px 8px;font-weight:600">R&eacute;f. SEPA</th>';
		print '<th style="text-align:right;padding:4px 8px;font-weight:600">Montant vers&eacute;</th>';
		print '<th style="text-align:left;padding:4px 8px;font-weight:600">Relev&eacute; bancaire</th>';
		print '<th style="text-align:center;padding:4px 8px;font-weight:600">Rapproch&eacute;</th>';
		print '<th style="text-align:left;padding:4px 8px;font-weight:600">Action</th>';
		print '</tr>';

		foreach ($f->payments as $p) {
			$pid = (int)$p->pay_id;

			// Réf SEPA (cliquable vers l'écriture bancaire du virement)
			if ($p->ref_sepa !== '') {
				$sepa_txt = '<span style="font-family:monospace;font-weight:600">' . dol_escape_htmltag($p->ref_sepa) . '</span>';
				$sepa_cell = ($p->bank_id > 0)
					? '<a href="' . DOL_URL_ROOT . '/compta/bank/line.php?rowid=' . (int)$p->bank_id . '" target="_blank" style="color:#0d6efd;text-decoration:none" title="Voir l\'ecriture bancaire">' . $sepa_txt . '</a>'
					: $sepa_txt;
			} else {
				$sepa_cell = '<span style="color:#aaa">—</span>';
			}

			// Relevé bancaire : n° de relevé Belfius, uniquement si pointé ET saisi (≠ réf SEPA pré-remplie)
			$has_releve  = ($p->rapproche && $p->num_releve !== '' && $p->num_releve !== $p->ref_sepa);
			$releve_cell = $has_releve
				? '<a href="' . DOL_URL_ROOT . '/compta/bank/bankentries_list.php?account=1&search_num_releve='
				  . urlencode($p->num_releve) . '" target="_blank" style="color:#0d6efd">'
				  . dol_escape_htmltag($p->num_releve) . '</a>'
				: '<span style="color:#aaa">—</span>';

			// Badge rapproché
			if ($p->rapproche) {
				$pbadge = '<span style="color:#28a745;font-weight:bold">' . img_picto('', 'fa-check-circle', '') . ' Oui</span>';
			} else {
				$pbadge = '<span style="color:#dc3545;font-weight:bold">' . img_picto('', 'fa-times-circle', '') . ' Non</span>';
			}

			// Lien fiche paiement
			$pay_url   = DOL_URL_ROOT . '/fourn/paiement/card.php?id=' . $pid;
			$fiche_btn = '<a href="' . $pay_url . '" style="padding:2px 8px;background:#6c757d;color:#fff;border-radius:3px;font-size:0.8em;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-right:6px">'
			           . img_picto('', 'fa-eye', '') . ' Fiche</a>';

			// Action rapprocher / dé-rapprocher
			if (!$p->rapproche) {
				$action_html = '<form method="POST" action="' . $url_base . '" style="display:inline-flex;align-items:center;gap:5px">'
				           . '<input type="hidden" name="action"       value="rapprocher">'
				           . '<input type="hidden" name="token"        value="' . newToken() . '">'
				           . '<input type="hidden" name="pay_id"       value="' . $pid . '">'
				           . '<input type="hidden" name="fac_id"       value="' . $fid . '">'
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
				$action_html = '';
				if ($user->admin) {
					$action_html = '<form method="POST" action="' . $url_base . '" style="display:inline-flex;align-items:center" '
					           . 'onsubmit="return confirm(\'Annuler le rapprochement de ce virement ?\')">'
					           . '<input type="hidden" name="action"      value="derapprocher">'
					           . '<input type="hidden" name="token"       value="' . newToken() . '">'
					           . '<input type="hidden" name="pay_id"      value="' . $pid . '">'
					           . '<input type="hidden" name="fac_id"      value="' . $fid . '">'
					           . '<input type="hidden" name="annee"       value="' . $annee . '">'
					           . '<input type="hidden" name="s_rapproche" value="' . dol_escape_htmltag($s_rapproche) . '">'
					           . '<input type="hidden" name="s_sepa"      value="' . dol_escape_htmltag($s_sepa) . '">'
					           . '<button type="submit" style="padding:2px 9px;background:#dc3545;color:#fff;border:none;border-radius:3px;font-size:0.78em;cursor:pointer;white-space:nowrap">'
					           . img_picto('', 'fa-undo', '') . ' Ann.</button>'
					           . '</form>';
				}
			}

			print '<tr style="border-bottom:1px solid #eee">';
			print '<td style="padding:5px 8px;white-space:nowrap">' . dol_print_date($db->jdate($p->date_paiement), 'day') . '</td>';
			print '<td style="padding:5px 8px">' . $sepa_cell . '</td>';
			print '<td style="padding:5px 8px;text-align:right;font-weight:bold;white-space:nowrap">' . price($p->montant_paye) . '&nbsp;&euro;</td>';
			print '<td style="padding:5px 8px">' . $releve_cell . '</td>';
			print '<td style="padding:5px 8px;text-align:center">' . $pbadge . '</td>';
			print '<td style="padding:5px 8px;white-space:nowrap">' . $fiche_btn . $action_html . '</td>';
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
print '<span>Colonne <b>Rapprochement</b> = nombre de virements rapproch&eacute;s / total des virements de la facture</span>';
print '<span>&#9658; = d&eacute;plier les virements de la facture pour les rapprocher un par un</span>';
print '<span>' . img_picto('', 'fa-times-circle', 'style="color:#dc3545"') . ' Non rapproch&eacute; = saisir le n&deg; relev&eacute; Belfius puis cliquer <b>Rapprocher</b></span>';
if ($user->admin) {
    print '<span style="color:#dc3545">Bouton <b>Ann.</b> (admin uniquement) = annuler un rapprochement</span>';
}
print '</div>';

print '</div>'; // fichecenter

llxFooter();
$db->close();
