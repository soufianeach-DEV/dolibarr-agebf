<?php
/* Copyright (C) 2026 Bruxelles Formation — Module AgeBF/Helpy v3.3 */

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
$annee  = (int) GETPOST('annee', 'int');
$statut = GETPOST('statut', 'aZ09');
$search = trim(GETPOST('search', 'alphanohtml'));

if ($annee < 2020 || $annee > 2100) $annee = (int) date('Y');
if (!in_array($statut, ['tous', 'soldee', 'partielle', 'impayee'])) $statut = 'tous';

$url_base = DOL_URL_ROOT . '/custom/agebf/agebf_packs.php';

llxHeader("", "Helpy — Suivi des packs", '', '', 0, 0, '', '', '', 'mod-agebf page-packs');

print load_fiche_titre("Suivi des packs ASBL", '', 'fa-file-invoice-dollar');

// ── Requête principale ────────────────────────────────────────────────────────
$sql = "SELECT
    f.rowid          AS fac_id,
    f.ref            AS fac_ref,
    f.datef          AS date_facture,
    f.date_lim_reglement AS echeance,
    f.total_ttc      AS montant,
    f.paye,
    f.fk_statut,
    s.rowid          AS soc_id,
    s.nom            AS tiers_nom,
    p.label          AS pack_label,
    p.ref            AS pack_ref,
    COALESCE(ef.beneficiaire, '')            AS beneficiaire,
    COALESCE(ef.communication_structuree, '') AS communication_structuree,
    COALESCE(SUM(pf.amount), 0)             AS montant_paye,
    MAX(pay.datep)                           AS derniere_date_paiement,
    MAX(pay.num_paiement)                    AS derniere_ref_sepa,
    MAX(pay.fk_bank)                         AS fk_bank
FROM " . MAIN_DB_PREFIX . "facture f
JOIN " . MAIN_DB_PREFIX . "societe s      ON s.rowid = f.fk_soc
JOIN " . MAIN_DB_PREFIX . "facturedet fd  ON fd.fk_facture = f.rowid
JOIN " . MAIN_DB_PREFIX . "product p      ON p.rowid = fd.fk_product
LEFT JOIN " . MAIN_DB_PREFIX . "facture_extrafields ef ON ef.fk_object = f.rowid
LEFT JOIN " . MAIN_DB_PREFIX . "paiement_facture pf    ON pf.fk_facture = f.rowid
LEFT JOIN " . MAIN_DB_PREFIX . "paiement pay           ON pay.rowid = pf.fk_paiement
WHERE f.entity = " . (int)$conf->entity . "
  AND f.fk_statut >= 1
  AND YEAR(f.datef) = " . (int)$annee;

if ($search !== '') {
	$sql .= " AND (s.nom LIKE '%" . $db->escape($search) . "%'"
	      . "  OR p.label LIKE '%" . $db->escape($search) . "%'"
	      . "  OR f.ref LIKE '%" . $db->escape($search) . "%'"
	      . "  OR ef.beneficiaire LIKE '%" . $db->escape($search) . "%'"
	      . "  OR ef.communication_structuree LIKE '%" . $db->escape($search) . "%')";
}

$sql .= " GROUP BY f.rowid, f.ref, f.datef, f.date_lim_reglement, f.total_ttc, f.paye, f.fk_statut,
                   s.rowid, s.nom, p.label, p.ref, ef.beneficiaire, ef.communication_structuree";
$sql .= " ORDER BY f.datef DESC, s.nom ASC";

$resql = $db->query($sql);
if (!$resql) {
	print '<div class="error">' . $db->lasterror() . '</div>';
	llxFooter(); $db->close(); exit;
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

foreach ($rows as $r) {
	if ($r->paye == 1)                      { $nb_soldee++;    $statut_row = 'soldee'; }
	elseif ($r->montant_paye > 0)           { $nb_partielle++; $statut_row = 'partielle'; }
	else                                    { $nb_impayee++;   $statut_row = 'impayee'; }
	$total_attendu += $r->montant;
	$total_recu    += $r->montant_paye;
}

// ── Filtrage statut (en mémoire, plus simple) ─────────────────────────────────
$rows_filtered = [];
foreach ($rows as $r) {
	if ($r->paye == 1)              $s = 'soldee';
	elseif ($r->montant_paye > 0)  $s = 'partielle';
	else                            $s = 'impayee';
	if ($statut === 'tous' || $statut === $s) {
		$rows_filtered[] = $r;
	}
}

// ── Bandeau stats ─────────────────────────────────────────────────────────────
print '<div class="fichecenter">';
print '<table class="border centpercent tableforfield" style="margin-bottom:12px">';
print '<tr>';
print '  <td class="titlefield center">Factures ' . (int)$annee . '</td>';
print '  <td class="center" style="font-weight:bold">' . $nb_total . '</td>';
print '  <td class="titlefield center" style="color:#28a745">Sold&eacute;es</td>';
print '  <td class="center" style="font-weight:bold;color:#28a745">' . $nb_soldee . '</td>';
print '  <td class="titlefield center" style="color:#fd7e14">Partielles</td>';
print '  <td class="center" style="font-weight:bold;color:#fd7e14">' . $nb_partielle . '</td>';
print '  <td class="titlefield center" style="color:#dc3545">Impay&eacute;es</td>';
print '  <td class="center" style="font-weight:bold;color:#dc3545">' . $nb_impayee . '</td>';
print '  <td class="titlefield center">Attendu</td>';
print '  <td class="center" style="font-weight:bold">' . price($total_attendu) . ' &euro;</td>';
print '  <td class="titlefield center" style="color:#28a745">Reu</td>';
print '  <td class="center" style="font-weight:bold;color:#28a745">' . price($total_recu) . ' &euro;</td>';
print '</tr>';
print '</table>';

// ── Filtres + recherche ───────────────────────────────────────────────────────
print '<form method="GET" action="' . $url_base . '" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px">';

// Filtre année
print '<label style="font-weight:bold">Ann&eacute;e :</label>';
print '<select name="annee" onchange="this.form.submit()" style="padding:4px 8px;border-radius:3px;border:1px solid #ccc">';
for ($y = (int)date('Y') + 1; $y >= 2024; $y--) {
	$sel = ($y === $annee) ? ' selected' : '';
	print "<option value=\"$y\"$sel>$y</option>";
}
print '</select>';

// Filtre statut
$statuts = ['tous' => 'Toutes', 'soldee' => 'Sold&eacute;es', 'partielle' => 'Partielles', 'impayee' => 'Impay&eacute;es'];
print '<label style="font-weight:bold">Statut :</label>';
print '<select name="statut" onchange="this.form.submit()" style="padding:4px 8px;border-radius:3px;border:1px solid #ccc">';
foreach ($statuts as $val => $lib) {
	$sel = ($val === $statut) ? ' selected' : '';
	print "<option value=\"$val\"$sel>$lib</option>";
}
print '</select>';

// Recherche
print '<label style="font-weight:bold">Recherche :</label>';
print '<input type="text" name="search" value="' . dol_escape_htmltag($search) . '" placeholder="Tiers, pack, OGM, b&eacute;n&eacute;ficiaire..." '
    . 'style="padding:4px 8px;border-radius:3px;border:1px solid #ccc;min-width:260px">';
print '<button type="submit" class="butAction" style="margin:0;padding:4px 14px">Filtrer</button>';
if ($search !== '') {
	print '<a href="' . $url_base . '?annee=' . $annee . '&statut=' . $statut . '" class="butActionDelete" style="padding:4px 10px">&#x2715; Effacer</a>';
}
print '</form>';

// ── Tableau ───────────────────────────────────────────────────────────────────
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>Pack</th>';
print '<th>Tiers / B&eacute;n&eacute;ficiaire</th>';
print '<th>N&deg; facture</th>';
print '<th>Communication structur&eacute;e</th>';
print '<th class="center">Date facture</th>';
print '<th class="right">Montant</th>';
print '<th class="right">Pay&eacute;</th>';
print '<th class="center">Statut</th>';
print '<th class="center">Dernier paiement</th>';
print '</tr>';

if (empty($rows_filtered)) {
	print '<tr class="oddeven"><td colspan="9" class="center opacitymedium">Aucune facture trouv&eacute;e pour les crit&egrave;res s&eacute;lectionn&eacute;s.</td></tr>';
}

foreach ($rows_filtered as $r) {

	// Statut de paiement
	if ($r->paye == 1) {
		$statut_label  = '<span style="color:#28a745;font-weight:bold">Sold&eacute;e</span>';
	} elseif ($r->montant_paye > 0) {
		$restant       = $r->montant - $r->montant_paye;
		$statut_label  = '<span style="color:#fd7e14;font-weight:bold">Partielle</span>';
		$statut_label .= '<br><span style="font-size:0.8em;color:#888">Reste ' . price($restant) . ' &euro;</span>';
	} else {
		$statut_label  = '<span style="color:#dc3545;font-weight:bold">Impay&eacute;e</span>';
	}

	// Lien vers la facture
	$lien_fac = '<a href="' . DOL_URL_ROOT . '/compta/facture/card.php?id=' . (int)$r->fac_id . '">'
	          . dol_escape_htmltag($r->fac_ref) . '</a>';

	// Date de paiement + lien relevé bancaire
	if ($r->derniere_date_paiement) {
		$date_pay = dol_print_date($db->jdate($r->derniere_date_paiement), 'day');
		$lien_pay = $date_pay;
		if ($r->derniere_ref_sepa) {
			$lien_pay .= '<br><a href="' . DOL_URL_ROOT . '/compta/bank/bankentries_list.php?account=1&search_ref='
			           . urlencode($r->derniere_ref_sepa) . '" style="font-size:0.8em;color:#6c757d" title="Voir le mouvement bancaire">'
			           . dol_escape_htmltag($r->derniere_ref_sepa) . '</a>';
		}
	} else {
		$lien_pay = '<span style="color:#aaa">—</span>';
	}

	// Lien tiers
	$lien_tiers = '<a href="' . DOL_URL_ROOT . '/societe/card.php?socid=' . (int)$r->soc_id . '">'
	            . dol_escape_htmltag($r->tiers_nom) . '</a>';
	if ($r->beneficiaire && $r->beneficiaire !== $r->tiers_nom) {
		$lien_tiers .= '<br><span style="font-size:0.85em;color:#555">'
		             . img_picto('', 'fa-user', 'style="font-size:0.9em"') . ' '
		             . dol_escape_htmltag($r->beneficiaire) . '</span>';
	}

	// Communication structurée
	$ogm_cell = $r->communication_structuree
		? '<code style="font-size:0.85em;background:#f8f9fa;padding:2px 6px;border-radius:3px">'
		  . dol_escape_htmltag($r->communication_structuree) . '</code>'
		: '<span style="color:#aaa">—</span>';

	// Badge pack
	$pack_colors = [
		'PACK-MUT' => '#0d6efd',
		'PACK-HOS' => '#6f42c1',
		'PACK-LUN' => '#20c997',
		'PACK-DEN' => '#fd7e14',
	];
	$pack_color = $pack_colors[$r->pack_ref] ?? '#6c757d';
	$pack_badge = '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:0.8em;font-weight:bold;background:' . $pack_color . ';color:#fff">'
	            . dol_escape_htmltag($r->pack_label) . '</span>';

	print '<tr class="oddeven">';
	print '<td>' . $pack_badge . '</td>';
	print '<td>' . $lien_tiers . '</td>';
	print '<td>' . $lien_fac . '</td>';
	print '<td>' . $ogm_cell . '</td>';
	print '<td class="center">' . dol_print_date($db->jdate($r->date_facture), 'day') . '</td>';
	print '<td class="right" style="font-weight:bold">' . price($r->montant) . ' &euro;</td>';

	if ($r->montant_paye > 0) {
		print '<td class="right" style="color:#28a745;font-weight:bold">' . price($r->montant_paye) . ' &euro;</td>';
	} else {
		print '<td class="right" style="color:#aaa">0 &euro;</td>';
	}

	print '<td class="center">' . $statut_label . '</td>';
	print '<td class="center">' . $lien_pay . '</td>';
	print '</tr>';
}

print '</table>';

// ── Légende ───────────────────────────────────────────────────────────────────
print '<div style="margin-top:16px;font-size:0.88em;color:#666;display:flex;gap:20px;flex-wrap:wrap">';
print '<span><b>L&eacute;gende :</b></span>';
print '<span style="color:#28a745;font-weight:bold">Sold&eacute;e</span> — 3/3 versements re&ccedil;us';
print '<span style="color:#fd7e14;font-weight:bold">Partielle</span> — paiement en cours (1/3 ou 2/3)';
print '<span style="color:#dc3545;font-weight:bold">Impay&eacute;e</span> — aucun versement enregistr&eacute;';
print '<span>La r&eacute;f&eacute;rence SEPA renvoie vers le mouvement bancaire Dolibarr correspondant.</span>';
print '</div>';

print '</div>'; // fichecenter

llxFooter();
$db->close();
