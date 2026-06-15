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
$action         = GETPOST('action', 'aZ09');
$show_inactifs  = GETPOST('show_inactifs', 'int') ? 1 : 0;
$url_base       = DOL_URL_ROOT . '/custom/agebf/agebf_assurances.php';
$url_base_si    = $url_base . ($show_inactifs ? '?show_inactifs=1' : '');
$msg_ok   = '';
$msg_err  = '';

// ── Vérification des colonnes extrafields ─────────────────────────────────────
$has_hospi    = false;
$has_dentaire = false;
$has_assurcard = false;
$res_cols = $db->query("SHOW COLUMNS FROM " . MAIN_DB_PREFIX . "socpeople_extrafields");
if ($res_cols) {
	while ($col = $db->fetch_object($res_cols)) {
		if ($col->Field === 'assurance_hospi')   $has_hospi    = true;
		if ($col->Field === 'assurance_dentaire') $has_dentaire = true;
		if ($col->Field === 'assurcard')          $has_assurcard = true;
	}
	$db->free($res_cols);
}

// Table dédiée du module — créée si absente (idempotent)
$db->query("CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "agebf_tiers_archive ("
         . " fk_soc        INT         NOT NULL,"
         . " motif_archive VARCHAR(20) NOT NULL DEFAULT 'autre',"
         . " UNIQUE KEY uk_agebf_ta_soc (fk_soc)"
         . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Action : définir le motif d'archivage d'un Tiers ─────────────────────────
if ($action === 'set_motif' && !empty($_POST['token'])) {
	$soc_id = (int)GETPOST('soc_id', 'int');
	$motif  = GETPOST('motif', 'aZ09');
	if ($soc_id > 0 && in_array($motif, ['decede', 'retraite', 'demission', 'autre'])) {
		$db->query("INSERT INTO " . MAIN_DB_PREFIX . "agebf_tiers_archive (fk_soc, motif_archive)"
		          . " VALUES ($soc_id, '" . $db->escape($motif) . "')"
		          . " ON DUPLICATE KEY UPDATE motif_archive = '" . $db->escape($motif) . "'");
	}
	header('Location: ' . $url_base . '?show_inactifs=1&motif_ok=1');
	exit;
}

// ── Action : synchroniser assurcard → assurance_hospi ─────────────────────────
if ($action === 'sync_assurcard' && !empty($_POST['token']) && $has_assurcard && $has_hospi) {
	$sql_sync = "SELECT ex.fk_object AS cid FROM " . MAIN_DB_PREFIX . "socpeople_extrafields ex"
	           . " WHERE ex.assurcard IS NOT NULL AND ex.assurcard <> ''";
	$r_sync = $db->query($sql_sync);
	$nb_sync = 0;
	if ($r_sync) {
		while ($os = $db->fetch_object($r_sync)) {
			$cid = (int)$os->cid;
			$db->query("INSERT INTO " . MAIN_DB_PREFIX . "socpeople_extrafields (fk_object, assurance_hospi)"
			          . " VALUES ($cid, 1)"
			          . " ON DUPLICATE KEY UPDATE assurance_hospi = 1");
			$nb_sync++;
		}
		$db->free($r_sync);
	}
	header('Location: ' . $url_base_si . (strpos($url_base_si, '?') !== false ? '&' : '?') . 'sync_ok=' . $nb_sync);
	exit;
}

// ── Action : sauvegarder les cases à cocher ───────────────────────────────────
if ($action === 'save' && !empty($_POST['token'])) {
	$all_cids         = isset($_POST['all_contacts']) && is_array($_POST['all_contacts'])
	                    ? array_map('intval', $_POST['all_contacts']) : [];
	$hospi_posted     = isset($_POST['hospi'])    && is_array($_POST['hospi'])    ? $_POST['hospi']    : [];
	$dentaire_posted  = isset($_POST['dentaire']) && is_array($_POST['dentaire']) ? $_POST['dentaire'] : [];

	$nb_saved = 0;
	$parts_hospi    = $has_hospi    ? '' : null;
	$parts_dentaire = $has_dentaire ? '' : null;

	foreach ($all_cids as $cid) {
		$hospi_val    = isset($hospi_posted[$cid])    ? 1 : 0;
		$dentaire_val = isset($dentaire_posted[$cid]) ? 1 : 0;

		$set_parts = [];
		if ($has_hospi)    $set_parts[] = "assurance_hospi = $hospi_val";
		if ($has_dentaire) $set_parts[] = "assurance_dentaire = $dentaire_val";
		if (empty($set_parts)) continue;

		$cols = implode(', ', array_merge(['fk_object'],
		    ($has_hospi    ? ['assurance_hospi']    : []),
		    ($has_dentaire ? ['assurance_dentaire'] : [])
		));
		$vals = implode(', ', array_merge([$cid],
		    ($has_hospi    ? [$hospi_val]    : []),
		    ($has_dentaire ? [$dentaire_val] : [])
		));
		$dup = implode(', ', $set_parts);

		$sql_ups = "INSERT INTO " . MAIN_DB_PREFIX . "socpeople_extrafields ($cols) VALUES ($vals)"
		         . " ON DUPLICATE KEY UPDATE $dup";
		if ($db->query($sql_ups)) $nb_saved++;
	}
	header('Location: ' . $url_base_si . (strpos($url_base_si, '?') !== false ? '&' : '?') . 'save_ok=' . $nb_saved);
	exit;
}

// ── Feedback ──────────────────────────────────────────────────────────────────
if (GETPOSTISSET('sync_ok')) {
	$nb = (int) GETPOST('sync_ok', 'int');
	$msg_ok = '<b>' . $nb . '</b> contact(s) mis &agrave; jour : Assurance Hospi. coch&eacute;e d\'apr&egrave;s le n&deg; Assurcard.';
}
if (GETPOSTISSET('save_ok')) {
	$nb = (int) GETPOST('save_ok', 'int');
	$msg_ok = 'Enregistrement effectu&eacute; pour <b>' . $nb . '</b> contact(s).';
}
if (GETPOSTISSET('motif_ok')) {
	$msg_ok = 'Motif d\'archivage mis &agrave; jour.';
}

// ── Chargement des données ────────────────────────────────────────────────────
// Colonnes dynamiques selon existence
$sel_hospi    = $has_hospi    ? 'COALESCE(ex.assurance_hospi, 0)'    : '0';
$sel_dentaire = $has_dentaire ? 'COALESCE(ex.assurance_dentaire, 0)' : '0';
$sel_assurcard     = $has_assurcard ? "COALESCE(ex.assurcard, '')" : "''";
$sel_motif_archive = "COALESCE(ta.motif_archive, '')";

$sql_data = "SELECT
    s.rowid    AS soc_id,
    s.nom      AS soc_nom,
    s.status   AS soc_status,
    " . $sel_motif_archive . " AS motif_archive,
    c.rowid    AS cid,
    c.lastname,
    c.firstname,
    c.poste,
    " . $sel_hospi    . " AS hospi,
    " . $sel_dentaire . " AS dentaire,
    " . $sel_assurcard . " AS assurcard
FROM " . MAIN_DB_PREFIX . "societe s
LEFT JOIN " . MAIN_DB_PREFIX . "agebf_tiers_archive ta ON ta.fk_soc = s.rowid
JOIN " . MAIN_DB_PREFIX . "socpeople c ON c.fk_soc = s.rowid
LEFT JOIN " . MAIN_DB_PREFIX . "socpeople_extrafields ex ON ex.fk_object = c.rowid
WHERE s.entity = " . (int)$conf->entity . "
  AND c.entity = " . (int)$conf->entity . "
  AND c.statut  = 1"
. ($show_inactifs ? '' : " AND s.status = 1")
. " ORDER BY s.status DESC, s.nom ASC,
    CASE c.poste
        WHEN 'Employe'  THEN 1
        WHEN 'Conjoint' THEN 2
        WHEN 'Fils'     THEN 3
        WHEN 'Fille'    THEN 3
        ELSE 9
    END ASC,
    c.lastname ASC, c.firstname ASC";

$resql = $db->query($sql_data);
if (!$resql) {
	llxHeader();
	print '<div class="error">' . $db->lasterror() . '</div>';
	llxFooter(); $db->close(); exit;
}

// Grouper par Tiers
$tiers_list = [];  // [soc_id => {soc_id, soc_nom, soc_status, contacts:[]}]
$stats_hospi    = 0;
$stats_dentaire = 0;
$stats_assurcard = 0;
$stats_hospi_missing = 0; // a un assurcard mais hospi pas coché
$nb_tiers_inactifs = 0;

while ($obj = $db->fetch_object($resql)) {
	$sid = (int)$obj->soc_id;
	if (!isset($tiers_list[$sid])) {
		$tiers_list[$sid] = (object)['soc_id' => $sid, 'soc_nom' => $obj->soc_nom, 'soc_status' => (int)$obj->soc_status, 'motif_archive' => $obj->motif_archive, 'contacts' => []];
	}
	$obj->hospi    = (int)$obj->hospi;
	$obj->dentaire = (int)$obj->dentaire;
	$tiers_list[$sid]->contacts[] = $obj;

	if ($obj->hospi)    $stats_hospi++;
	if ($obj->dentaire) $stats_dentaire++;
	if ($obj->assurcard !== '') {
		$stats_assurcard++;
		if (!$obj->hospi) $stats_hospi_missing++;
	}
}
$db->free($resql);

foreach ($tiers_list as $t) {
	if ($t->soc_status === 0) $nb_tiers_inactifs++;
}
$nb_tiers    = count($tiers_list);
$nb_contacts = array_sum(array_map(fn($t) => count($t->contacts), $tiers_list));

// ── HTML ──────────────────────────────────────────────────────────────────────
llxHeader("", "Helpy — Assurances", '', '', 0, 0, '', '', '', 'mod-agebf page-assurances');

print '
<style>
.ass-detail-row { display:none; }
.ass-detail-row.open { display:table-row; }
.ass-toggle-btn {
    cursor:pointer; border:none; background:none; padding:0 4px;
    color:#0d6efd; font-size:1.1em; line-height:1;
    transition: transform 0.15s;
}
.ass-toggle-btn.open { transform: rotate(90deg); }
.ass-detail-inner {
    background:#f8f9fa; border-left:3px solid #6f42c1;
    padding:8px 12px; border-radius:0 4px 4px 0;
}
.ass-check { width:20px; height:20px; cursor:pointer; accent-color:#6f42c1; }
</style>
<script>
function assToggle(id) {
    var rows = document.querySelectorAll(".ass-det-" + id);
    var btn  = document.getElementById("ass-btn-" + id);
    var open = btn.classList.contains("open");
    rows.forEach(function(r) { r.classList.toggle("open", !open); });
    btn.classList.toggle("open", !open);
}
function assToggleAll(openAll) {
    document.querySelectorAll(".ass-toggle-btn").forEach(function(btn) {
        var id = btn.id.replace("ass-btn-", "");
        document.querySelectorAll(".ass-det-" + id).forEach(function(r) { r.classList.toggle("open", openAll); });
        btn.classList.toggle("open", openAll);
    });
}
</script>
';

print load_fiche_titre("Assurances", '', 'fa-heart');

if ($msg_ok)  print '<div class="ok">'    . $msg_ok  . '</div>';
if ($msg_err) print '<div class="error">' . $msg_err . '</div>';

// ── Avertissement si colonnes manquantes ─────────────────────────────────────
if (!$has_hospi || !$has_dentaire) {
	print '<div class="warning" style="margin-bottom:14px">';
	print img_picto('', 'fa-exclamation-triangle', '') . ' ';
	print 'Les champs extrafields <b>assurance_hospi</b> et/ou <b>assurance_dentaire</b> sont absents. ';
	print 'Veuillez <a href="' . DOL_URL_ROOT . '/admin/modules.php">d&eacute;sactiver puis r&eacute;activer le module AgeBF/Helpy</a> pour les cr&eacute;er.';
	print '</div>';
}

// ── Bandeau stats ─────────────────────────────────────────────────────────────
$stats_disp = [
	['label' => 'Tiers',                          'value' => $nb_tiers,             'color' => '#333'],
	['label' => 'Contacts total',                  'value' => $nb_contacts,          'color' => '#333'],
	['label' => 'Assurance Hospi.',                'value' => $stats_hospi,          'color' => '#6f42c1'],
	['label' => 'Assurance Dentaire',              'value' => $stats_dentaire,       'color' => '#0d6efd'],
	['label' => 'N&deg; Assurcard',                'value' => $stats_assurcard,      'color' => '#20c997'],
	['label' => 'Assurcard sans Hospi.',           'value' => $stats_hospi_missing,  'color' => ($stats_hospi_missing > 0 ? '#dc3545' : '#28a745')],
];
print '<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px">';
foreach ($stats_disp as $st) {
	print '<div style="flex:1;min-width:120px;padding:10px 14px;background:#fff;border:1px solid #ddd;border-radius:6px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.06)">';
	print '<div style="font-size:1.3em;font-weight:bold;color:' . $st['color'] . ';line-height:1.2">' . $st['value'] . '</div>';
	print '<div style="font-size:0.8em;color:#777;margin-top:3px">' . $st['label'] . '</div>';
	print '</div>';
}
print '</div>';

// ── Boutons globaux ───────────────────────────────────────────────────────────
print '<div class="fichecenter">';
print '<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap">';

// Bouton sync assurcard
if ($has_assurcard && $has_hospi && $stats_hospi_missing > 0) {
	print '<form method="POST" action="' . $url_base . '" onsubmit="return confirm(\'Cocher Assurance Hospi. pour tous les contacts ayant un n° Assurcard (' . $stats_hospi_missing . ' contact(s)) ?\')">';
	print '<input type="hidden" name="token"  value="' . newToken() . '">';
	print '<input type="hidden" name="action" value="sync_assurcard">';
	print '<button type="submit" style="padding:6px 14px;background:#6f42c1;color:#fff;border:none;border-radius:3px;cursor:pointer;font-weight:bold">';
	print img_picto('', 'fa-sync', '') . ' Synchroniser assurcard &rarr; Hospi. <span style="opacity:0.8;font-size:0.9em">(' . $stats_hospi_missing . ' &agrave; mettre &agrave; jour)</span>';
	print '</button>';
	print '</form>';
} elseif ($has_assurcard && $has_hospi) {
	print '<span style="color:#28a745;font-size:0.9em">' . img_picto('', 'fa-check-circle', '') . ' Tous les contacts avec assurcard ont d&eacute;j&agrave; Hospi. coch&eacute;e.</span>';
}

print '<button type="button" onclick="assToggleAll(true)" style="padding:6px 12px;background:#6c757d;color:#fff;border:none;border-radius:3px;font-size:0.85em;cursor:pointer">D&eacute;plier tout</button>';
print '<button type="button" onclick="assToggleAll(false)" style="padding:6px 12px;background:#6c757d;color:#fff;border:none;border-radius:3px;font-size:0.85em;cursor:pointer">Replier tout</button>';

// Toggle Tiers archivés
if ($show_inactifs) {
	print '<a href="' . $url_base . '" style="padding:6px 12px;background:#fff3cd;color:#856404;border:1px solid #ffc107;border-radius:3px;font-size:0.85em;text-decoration:none;font-weight:600">';
	print '&#9760; Masquer les archiv&eacute;s</a>';
} else {
	// Compter les Tiers inactifs (requête rapide)
	$r_inact = $db->query("SELECT COUNT(*) AS nb FROM " . MAIN_DB_PREFIX . "societe WHERE status = 0 AND entity = " . (int)$conf->entity);
	$nb_inact = $r_inact ? (int)$db->fetch_object($r_inact)->nb : 0;
	$db->free($r_inact);
	if ($nb_inact > 0) {
		print '<a href="' . $url_base . '?show_inactifs=1" style="padding:6px 12px;background:#f8f9fa;color:#6c757d;border:1px solid #dee2e6;border-radius:3px;font-size:0.85em;text-decoration:none">';
		print '&#9760; Afficher les archiv&eacute;s <span style="background:#6c757d;color:#fff;border-radius:8px;padding:0 5px;font-size:0.85em">' . $nb_inact . '</span></a>';
	}
}

print '</div>';

// ── Tableau ───────────────────────────────────────────────────────────────────
print '<form method="POST" action="' . $url_base . '">';
print '<input type="hidden" name="token"         value="' . newToken() . '">';
print '<input type="hidden" name="action"        value="save">';
print '<input type="hidden" name="show_inactifs" value="' . $show_inactifs . '">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th style="width:28px"></th>';
print '<th>Tiers</th>';
print '<th class="center">Contacts</th>';
print '<th class="center" style="color:#6f42c1">' . img_picto('', 'fa-heart', '') . ' Hospi.</th>';
print '<th class="center" style="color:#0d6efd">' . img_picto('', 'fa-tooth', '') . ' Dentaire</th>';
print '</tr>';

if (empty($tiers_list)) {
	print '<tr class="oddeven"><td colspan="5" class="center opacitymedium" style="padding:20px">Aucun tiers avec des contacts.</td></tr>';
}

foreach ($tiers_list as $t) {
	$sid = (int)$t->soc_id;
	$nb_c = count($t->contacts);

	// Comptage pour ce Tiers
	$nb_hospi    = 0;
	$nb_dentaire = 0;
	foreach ($t->contacts as $c) {
		if ($c->hospi)    $nb_hospi++;
		if ($c->dentaire) $nb_dentaire++;
	}

	$is_inactif  = ($t->soc_status === 0);
	$row_opacity = $is_inactif ? ' style="opacity:0.6"' : '';
	$motif_val   = isset($t->motif_archive) ? $t->motif_archive : '';
	// [icône html, libellé, couleur bg, couleur texte]
	$motifs_map = [
		'decede'    => ['&#9760;',  'D&eacute;c&eacute;d&eacute;(e)',       '#212529', '#f8f9fa'],
		'retraite'  => ['&#127958;','Retrait&eacute;(e)',                    '#2c5f7a', '#e9f4fb'],
		'demission' => ['&#128694;','D&eacute;missionn&eacute;(e)',          '#923f00', '#fff3e0'],
		'autre'     => ['&#128193;','Archiv&eacute;',                        '#4a4a4a', '#e9ecef'],
	];
	$motif_info = isset($motifs_map[$motif_val]) ? $motifs_map[$motif_val] : $motifs_map['autre'];
	$badge_inactif = $is_inactif
		? ' <span style="display:inline-block;font-size:0.74em;background:' . $motif_info[2] . ';color:' . $motif_info[3] . ';padding:2px 9px;border-radius:10px;vertical-align:middle;font-weight:600;letter-spacing:0.01em">'
		  . $motif_info[0] . '&nbsp;' . $motif_info[1] . '</span>'
		: '';

	print '<tr class="oddeven"' . $row_opacity . '>';
	print '<td style="text-align:center;padding:6px 4px">';
	print '<button type="button" id="ass-btn-' . $sid . '" class="ass-toggle-btn" onclick="assToggle(' . $sid . ')" title="Voir les contacts">&#9658;</button>';
	print '</td>';
	print '<td>';
	print '<a href="' . DOL_URL_ROOT . '/societe/card.php?socid=' . $sid . '" style="font-weight:600' . ($is_inactif ? ';color:#6c757d' : '') . '">' . dol_escape_htmltag($t->soc_nom) . '</a>';
	print $badge_inactif;
	if ($is_inactif) {
		$motif_select_opts = [
			'decede'    => '&#9760;&nbsp;D&eacute;c&eacute;d&eacute;(e)',
			'retraite'  => '&#127958;&nbsp;Retrait&eacute;(e)',
			'demission' => '&#128694;&nbsp;D&eacute;missionn&eacute;(e)',
			'autre'     => '&#128193;&nbsp;Autre',
		];
		print ' <form method="POST" action="' . $url_base . '" style="display:inline-block;margin-left:5px;vertical-align:middle">';
		print '<input type="hidden" name="token"  value="' . newToken() . '">';
		print '<input type="hidden" name="action" value="set_motif">';
		print '<input type="hidden" name="soc_id" value="' . $sid . '">';
		print '<select name="motif" style="font-size:0.73em;padding:1px 3px;border-radius:4px;border:1px solid #bbb;color:#444;background:#fff;vertical-align:middle">';
		foreach ($motif_select_opts as $val => $lbl) {
			print '<option value="' . $val . '"' . ($motif_val === $val ? ' selected' : '') . '>' . $lbl . '</option>';
		}
		print '</select>';
		print '&nbsp;<button type="submit" title="Appliquer" style="font-size:0.73em;padding:1px 6px;border-radius:4px;border:1px solid #bbb;background:#f8f9fa;cursor:pointer;color:#333;vertical-align:middle">&#10003;</button>';
		print '</form>';
	}
	print '</td>';
	print '<td class="center"><span style="font-weight:bold">' . $nb_c . '</span></td>';
	print '<td class="center">';
	if ($nb_hospi > 0) {
		print '<span style="color:#6f42c1;font-weight:bold">' . img_picto('', 'fa-check-circle', '') . ' ' . $nb_hospi . '/' . $nb_c . '</span>';
	} else {
		print '<span style="color:#aaa">' . img_picto('', 'fa-times-circle', '') . ' 0/' . $nb_c . '</span>';
	}
	print '</td>';
	print '<td class="center">';
	if ($nb_dentaire > 0) {
		print '<span style="color:#0d6efd;font-weight:bold">' . img_picto('', 'fa-check-circle', '') . ' ' . $nb_dentaire . '/' . $nb_c . '</span>';
	} else {
		print '<span style="color:#aaa">' . img_picto('', 'fa-times-circle', '') . ' 0/' . $nb_c . '</span>';
	}
	print '</td>';
	print '</tr>';

	// ── Détail contacts ────────────────────────────────────────────────────────
	print '<tr class="ass-detail-row ass-det-' . $sid . '">';
	print '<td colspan="5" style="padding:0 16px 12px 36px">';
	print '<div class="ass-detail-inner">';
	print '<table style="width:100%;border-collapse:collapse;font-size:0.9em">';
	print '<tr style="color:#666;border-bottom:1px solid #ddd">';
	print '<th style="text-align:left;padding:4px 8px;font-weight:600">Nom</th>';
	print '<th style="text-align:left;padding:4px 8px;font-weight:600">Pr&eacute;nom</th>';
	print '<th style="text-align:left;padding:4px 8px;font-weight:600">Poste</th>';
	print '<th style="text-align:left;padding:4px 8px;font-weight:600">N&deg; Assurcard</th>';
	print '<th style="text-align:center;padding:4px 8px;font-weight:600;color:#6f42c1">Hospi.</th>';
	print '<th style="text-align:center;padding:4px 8px;font-weight:600;color:#0d6efd">Dentaire</th>';
	print '</tr>';

	foreach ($t->contacts as $c) {
		$cid = (int)$c->cid;

		// Badge rôle famille
		$poste_lc = strtolower(trim($c->poste));
		if ($poste_lc === 'employe') {
			$role_badge = '<span style="display:inline-block;padding:1px 7px;border-radius:8px;background:#495057;color:#fff;font-size:11px;font-weight:bold">&#128084; Employ&eacute;(e)</span>';
		} elseif ($poste_lc === 'conjoint') {
			$role_badge = '<span style="display:inline-block;padding:1px 7px;border-radius:8px;background:#e91e8c;color:#fff;font-size:11px;font-weight:bold">&#128145; Conjoint(e)</span>';
		} elseif ($poste_lc === 'fils') {
			$role_badge = '<span style="display:inline-block;padding:1px 7px;border-radius:8px;background:#0d6efd;color:#fff;font-size:11px">&#128102; Fils</span>';
		} elseif ($poste_lc === 'fille') {
			$role_badge = '<span style="display:inline-block;padding:1px 7px;border-radius:8px;background:#d63384;color:#fff;font-size:11px">&#128103; Fille</span>';
		} else {
			$role_badge = '<span style="display:inline-block;padding:1px 7px;border-radius:8px;background:#aaa;color:#fff;font-size:11px">' . dol_escape_htmltag($c->poste) . '</span>';
		}

		// Badge assurcard
		$assurcard_cell = ($c->assurcard !== '')
			? '<span style="font-family:monospace;background:#e8f5e9;color:#1a7f37;padding:1px 6px;border-radius:4px;font-weight:600">' . dol_escape_htmltag($c->assurcard) . '</span>'
			: '<span style="color:#aaa">&mdash;</span>';

		// Highlight: a un assurcard mais hospi pas coché
		$row_style = ($c->assurcard !== '' && !$c->hospi) ? ' style="background:#fffbf0"' : '';

		// Séparateur visuel avant les enfants
		$separator = ($poste_lc === 'fils' || $poste_lc === 'fille') ? ' style="border-top:2px solid #dee2e6;border-bottom:1px solid #eee"' : ' style="border-bottom:1px solid #eee"';

		print '<input type="hidden" name="all_contacts[]" value="' . $cid . '">';
		print '<tr' . (($c->assurcard !== '' && !$c->hospi) ? ' style="background:#fffbf0;' . ltrim($separator, ' style="') : $separator) . '>';
		print '<td style="padding:5px 8px"><a href="' . DOL_URL_ROOT . '/contact/card.php?id=' . $cid . '" style="color:#333;font-weight:' . ($poste_lc === 'employe' ? 'bold' : 'normal') . '">' . dol_escape_htmltag($c->lastname) . '</a></td>';
		print '<td style="padding:5px 8px">' . dol_escape_htmltag($c->firstname) . '</td>';
		print '<td style="padding:5px 8px">' . $role_badge . '</td>';
		print '<td style="padding:5px 8px">' . $assurcard_cell . '</td>';
		print '<td style="padding:5px 8px;text-align:center">';
		if ($has_hospi) {
			print '<input type="checkbox" class="ass-check" name="hospi[' . $cid . ']" value="1"' . ($c->hospi ? ' checked' : '') . ' title="Assurance Hospitaliére">';
		} else {
			print '<span style="color:#aaa" title="Champ non install&eacute;">—</span>';
		}
		print '</td>';
		print '<td style="padding:5px 8px;text-align:center">';
		if ($has_dentaire) {
			print '<input type="checkbox" class="ass-check" name="dentaire[' . $cid . ']" value="1"' . ($c->dentaire ? ' checked' : '') . ' title="Assurance dentaire">';
		} else {
			print '<span style="color:#aaa" title="Champ non install&eacute;">—</span>';
		}
		print '</td>';
		print '</tr>';
	}

	print '</table>';
	print '</div>';
	print '</td></tr>';
}

print '</table>';

// ── Bouton enregistrer ─────────────────────────────────────────────────────────
print '<div style="margin-top:16px;display:flex;gap:10px;align-items:center">';
print '<button type="submit" style="padding:8px 22px;background:#6f42c1;color:#fff;border:none;border-radius:3px;cursor:pointer;font-weight:bold;font-size:1em">';
print img_picto('', 'fa-save', '') . ' Enregistrer les assurances';
print '</button>';
print '<span style="color:#666;font-size:0.85em">Cliquez apr&egrave;s avoir coch&eacute;/d&eacute;coch&eacute; les cases — toutes les modifications sont sauvegard&eacute;es en une fois.</span>';
print '</div>';

print '</form>';

// ── Légende ───────────────────────────────────────────────────────────────────
print '<div style="margin-top:18px;font-size:0.85em;color:#666;display:flex;gap:18px;flex-wrap:wrap;align-items:center">';
print '<b>L&eacute;gende :</b>';
print '<span style="background:#fffbf0;padding:2px 8px;border-radius:3px">&nbsp; = contact ayant un n&deg; Assurcard mais Hospi. non coch&eacute;e</span>';
print '<span>' . img_picto('', 'fa-sync', 'style="color:#6f42c1"') . ' Bouton Synchroniser : coche automatiquement Hospi. pour tous les contacts avec un n&deg; Assurcard</span>';
print '</div>';

print '</div>'; // fichecenter

llxFooter();
$db->close();
