<?php
/**
 * Script de génération de données test : assurance_hospi + assurance_dentaire
 * Accès : http://localhost/dolibarr/htdocs/custom/agebf/gen_assurances_test.php
 *
 * Répartition appliquée (modifiable via les constantes ci-dessous) :
 *   hospi SEULEMENT   → 30 % des contacts
 *   dentaire SEULEMENT → 20 % des contacts
 *   LES DEUX          → 30 % des contacts
 *   aucun             → 20 % des contacts
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) { $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php"; }
if (!$res && file_exists("../main.inc.php"))   { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

if (!defined('MAIN_DB_PREFIX')) { die("Dolibarr non chargé."); }

// ── Vérification que les colonnes existent ────────────────────────────────────
$cols = [];
$rc = $db->query("SHOW COLUMNS FROM " . MAIN_DB_PREFIX . "socpeople_extrafields");
if ($rc) { while ($c = $db->fetch_object($rc)) { $cols[] = $c->Field; } }

$missing = [];
if (!in_array('assurance_hospi',   $cols)) $missing[] = 'assurance_hospi';
if (!in_array('assurance_dentaire', $cols)) $missing[] = 'assurance_dentaire';

// ── Récupère tous les contacts ────────────────────────────────────────────────
$contacts = [];
$rq = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "socpeople ORDER BY rowid");
if ($rq) {
	while ($obj = $db->fetch_object($rq)) {
		$contacts[] = (int)$obj->rowid;
	}
}
$total = count($contacts);

// ── Répartition ───────────────────────────────────────────────────────────────
// Chaque contact reçoit un « groupe » selon sa position dans le tableau trié.
// On shufflé pour que la distribution soit aléatoire.
srand(42); // seed fixe → résultat reproductible
shuffle($contacts);

$n_hospi_only   = (int)round($total * 0.30);
$n_dent_only    = (int)round($total * 0.20);
$n_both         = (int)round($total * 0.30);
// le reste → aucun

$assignments = []; // cid => [hospi, dentaire]
foreach ($contacts as $idx => $cid) {
	if ($idx < $n_hospi_only) {
		$assignments[$cid] = [1, 0];
	} elseif ($idx < $n_hospi_only + $n_dent_only) {
		$assignments[$cid] = [0, 1];
	} elseif ($idx < $n_hospi_only + $n_dent_only + $n_both) {
		$assignments[$cid] = [1, 1];
	} else {
		$assignments[$cid] = [0, 0];
	}
}

// ── Action : exécuter ─────────────────────────────────────────────────────────
$done     = [];
$errors   = [];
$executed = false;

if (!empty($_POST['go']) && empty($missing)) {
	$executed = true;
	foreach ($assignments as $cid => [$hospi, $dent]) {
		$sql = "INSERT INTO " . MAIN_DB_PREFIX . "socpeople_extrafields"
		     . " (fk_object, assurance_hospi, assurance_dentaire)"
		     . " VALUES (" . (int)$cid . ", " . $hospi . ", " . $dent . ")"
		     . " ON DUPLICATE KEY UPDATE"
		     . "   assurance_hospi   = " . $hospi . ","
		     . "   assurance_dentaire = " . $dent;
		if ($db->query($sql)) {
			$done[] = $cid;
		} else {
			$errors[] = "cid=$cid : " . $db->lasterror();
		}
	}
}

// ── Affichage ─────────────────────────────────────────────────────────────────
$nb_h  = count(array_filter($assignments, fn($v) => $v[0] && !$v[1]));
$nb_d  = count(array_filter($assignments, fn($v) => !$v[0] && $v[1]));
$nb_hd = count(array_filter($assignments, fn($v) => $v[0] && $v[1]));
$nb_0  = count(array_filter($assignments, fn($v) => !$v[0] && !$v[1]));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Gen — Assurances test</title>
<style>
body { font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; }
h2 { color: #333; }
table { border-collapse: collapse; width: 100%; margin-top: 16px; }
th, td { border: 1px solid #ddd; padding: 7px 12px; text-align: left; font-size: 13px; }
th { background: #f0f0f0; }
.badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:12px; font-weight:bold; color:#fff; }
.h  { background:#0d6efd; }
.d  { background:#198754; }
.hd { background:#dc3545; }
.n  { background:#aaa; }
.ok  { background:#d4edda; border:1px solid #c3e6cb; padding:10px 16px; border-radius:6px; margin:12px 0; }
.err { background:#f8d7da; border:1px solid #f5c6cb; padding:10px 16px; border-radius:6px; margin:12px 0; }
.warn { background:#fff3cd; border:1px solid #ffc107; padding:10px 16px; border-radius:6px; margin:12px 0; }
.btn { background:#0d6efd; color:#fff; border:none; padding:10px 24px; border-radius:6px; font-size:15px; cursor:pointer; }
.btn:hover { background:#0b5ed7; }
.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin:16px 0; }
.stat-box { border-radius:8px; padding:14px; text-align:center; color:#fff; }
.stat-box .n2 { font-size:28px; font-weight:bold; }
.stat-box .l  { font-size:12px; opacity:.9; }
</style>
</head>
<body>

<h2>🧪 Génération données test — Assurances</h2>

<?php if (!empty($missing)): ?>
<div class="warn">
⚠️ Colonnes manquantes dans <code>llx_socpeople_extrafields</code> :
<strong><?= implode(', ', $missing) ?></strong><br>
→ Désactivez puis réactivez le module <strong>Helpy</strong> pour créer ces colonnes, puis revenez ici.
</div>
<?php else: ?>

<?php if ($executed): ?>
	<?php if (empty($errors)): ?>
	<div class="ok">✅ <strong><?= count($done) ?> contacts mis à jour</strong> avec succès.</div>
	<?php else: ?>
	<div class="err">⚠️ <?= count($done) ?> OK, <?= count($errors) ?> erreur(s) :<br>
		<?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
	<?php endif; ?>
<?php endif; ?>

<h3>Répartition prévue (<?= $total ?> contacts au total)</h3>
<div class="stats-grid">
	<div class="stat-box" style="background:#0d6efd">
		<div class="n2"><?= $nb_h ?></div>
		<div class="l">Hospi seulement</div>
	</div>
	<div class="stat-box" style="background:#198754">
		<div class="n2"><?= $nb_d ?></div>
		<div class="l">Dentaire seulement</div>
	</div>
	<div class="stat-box" style="background:#dc3545">
		<div class="n2"><?= $nb_hd ?></div>
		<div class="l">Hospi + Dentaire</div>
	</div>
	<div class="stat-box" style="background:#aaa">
		<div class="n2"><?= $nb_0 ?></div>
		<div class="l">Aucune assurance</div>
	</div>
</div>

<h3>Détail par contact</h3>
<table>
<tr><th>CID</th><th>Hospi</th><th>Dentaire</th><th>Résultat</th></tr>
<?php
// Réafficher trié par cid
ksort($assignments);
foreach ($assignments as $cid => [$h, $d]):
	if ($h && !$d)  { $badge = '<span class="badge h">Hospi</span>'; }
	elseif (!$h && $d) { $badge = '<span class="badge d">Dentaire</span>'; }
	elseif ($h && $d)  { $badge = '<span class="badge h">Hospi</span> <span class="badge d">Dentaire</span>'; }
	else               { $badge = '<span class="badge n">Aucune</span>'; }

	$status = '';
	if ($executed) {
		$status = in_array($cid, $done) ? '✅' : '❌';
	}
?>
<tr>
	<td><?= $cid ?></td>
	<td><?= $h ? '✔' : '' ?></td>
	<td><?= $d ? '✔' : '' ?></td>
	<td><?= $badge ?> <?= $status ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php if (!$executed): ?>
<br>
<form method="post">
	<input type="hidden" name="token" value="<?= newToken() ?>">
	<button type="submit" name="go" value="1" class="btn">
		🚀 Appliquer ces données test (<?= $total ?> contacts)
	</button>
</form>
<?php else: ?>
<br>
<a href="agebf_assurances.php" style="font-size:15px;">→ Voir la page Assurances</a>
<?php endif; ?>

<?php endif; ?>

</body>
</html>
