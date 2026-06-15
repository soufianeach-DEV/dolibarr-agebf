<?php
/**
 * Script de génération de données test — page Assurances
 * Couvre TOUS les scénarios visibles dans l'interface.
 *
 * Accès : http://localhost/dolibarr/htdocs/custom/agebf/gen_assurances_test.php
 *
 * ┌─────────────────────────────────────────────────────────────────┐
 * │  # │ Hospi │ Dent. │ Assurcard   │ Effet dans l'UI             │
 * ├─────────────────────────────────────────────────────────────────┤
 * │  1 │  ✓    │       │ —           │ Hospi. seulement            │
 * │  2 │       │  ✓    │ —           │ Dentaire seulement          │
 * │  3 │  ✓    │  ✓    │ —           │ Les deux                    │
 * │  4 │       │       │ —           │ Aucune assurance            │
 * │  5 │  ✓    │       │ BEL-001     │ Assurcard synced            │
 * │  6 │       │       │ BEL-002     │ ⚠ Assurcard SANS hospi     │
 * │  7 │  ✓    │  ✓    │ BEL-003     │ Assurcard + les deux       │
 * │  8 │       │  ✓    │ BEL-004     │ ⚠ Assurcard SANS hospi     │
 * └─────────────────────────────────────────────────────────────────┘
 *
 * Les scénarios 6 et 8 déclenchent le surlignage jaune et le bouton
 * "Synchroniser assurcard → Hospi." dans l'interface.
 *
 * Scénario archivé : mettre un Tiers en inactif manuellement dans
 * Dolibarr (Fiche Tiers → Actions → Mettre en inactif) pour tester
 * le bouton "Afficher les archivés".
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

// ── Vérification colonnes ─────────────────────────────────────────────────────
$cols = [];
$rc = $db->query("SHOW COLUMNS FROM " . MAIN_DB_PREFIX . "socpeople_extrafields");
if ($rc) { while ($c = $db->fetch_object($rc)) { $cols[] = $c->Field; } }

$has_hospi     = in_array('assurance_hospi',    $cols);
$has_dentaire  = in_array('assurance_dentaire', $cols);
$has_assurcard = in_array('assurcard',          $cols);

$missing = [];
if (!$has_hospi)    $missing[] = 'assurance_hospi';
if (!$has_dentaire) $missing[] = 'assurance_dentaire';

// ── Définition des 8 scénarios ────────────────────────────────────────────────
// [hospi, dentaire, assurcard, label, badge_color, ui_effect]
$scenarios = [
    1 => [1, 0, '',        'Hospi. seulement',       '#6f42c1', ''],
    2 => [0, 1, '',        'Dentaire seulement',      '#0d6efd', ''],
    3 => [1, 1, '',        'Hospi. + Dentaire',       '#198754', ''],
    4 => [0, 0, '',        'Aucune assurance',        '#aaa',    ''],
    5 => [1, 0, 'BEL-001', 'Assurcard + Hospi.',      '#17a2b8', ''],
    6 => [0, 0, 'BEL-002', 'Assurcard SANS hospi.',   '#dc3545', '⚠ Jaune'],
    7 => [1, 1, 'BEL-003', 'Assurcard + les deux',    '#fd7e14', ''],
    8 => [0, 1, 'BEL-004', 'Assurcard SANS hospi.',   '#dc3545', '⚠ Jaune'],
];

// ── Récupère tous les contacts ────────────────────────────────────────────────
$contacts = [];
$rq = $db->query(
    "SELECT c.rowid AS cid, c.fk_soc, c.firstname, c.lastname, c.poste,
            s.nom AS soc_nom
     FROM " . MAIN_DB_PREFIX . "socpeople c
     JOIN " . MAIN_DB_PREFIX . "societe  s ON s.rowid = c.fk_soc
     ORDER BY c.fk_soc, c.rowid"
);
if ($rq) { while ($o = $db->fetch_object($rq)) { $contacts[] = $o; } }

// ── Attribution des scénarios en round-robin ──────────────────────────────────
// Chaque contact reçoit le scénario correspondant à (index % 8) + 1
// → garantit que CHAQUE scénario est représenté au moins une fois
$assignments = [];
foreach ($contacts as $idx => $c) {
    $sc_num = ($idx % 8) + 1;
    [$hospi, $dent, $assurcard, $label] = $scenarios[$sc_num];
    // Numéro Assurcard unique par contact pour éviter les doublons
    if ($assurcard !== '') {
        $assurcard = sprintf('BEL-%04d', $c->cid);
    }
    $assignments[(int)$c->cid] = [
        'scenario'  => $sc_num,
        'hospi'     => $hospi,
        'dentaire'  => $dent,
        'assurcard' => $assurcard,
        'label'     => $label,
        'soc_nom'   => $c->soc_nom,
        'nom'       => $c->lastname . ' ' . $c->firstname,
        'poste'     => $c->poste,
    ];
}

// ── Comptage par scénario ─────────────────────────────────────────────────────
$count_per_sc = array_fill(1, 8, 0);
foreach ($assignments as $a) { $count_per_sc[$a['scenario']]++; }

// ── Exécution ─────────────────────────────────────────────────────────────────
$done = $errors = 0;
$executed = !empty($_POST['go']);

if ($executed && empty($missing)) {
    foreach ($assignments as $cid => $a) {
        // Construire INSERT dynamiquement selon colonnes disponibles
        $cols_i = ['fk_object'];
        $vals_i = [(int)$cid];
        $dup    = [];

        if ($has_hospi) {
            $cols_i[] = 'assurance_hospi';
            $vals_i[] = $a['hospi'];
            $dup[]    = 'assurance_hospi = ' . $a['hospi'];
        }
        if ($has_dentaire) {
            $cols_i[] = 'assurance_dentaire';
            $vals_i[] = $a['dentaire'];
            $dup[]    = 'assurance_dentaire = ' . $a['dentaire'];
        }
        if ($has_assurcard) {
            $cols_i[] = 'assurcard';
            $vals_i[] = "'" . $db->escape($a['assurcard']) . "'";
            $dup[]    = "assurcard = '" . $db->escape($a['assurcard']) . "'";
        }

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "socpeople_extrafields"
             . " (" . implode(', ', $cols_i) . ")"
             . " VALUES (" . implode(', ', $vals_i) . ")"
             . " ON DUPLICATE KEY UPDATE " . implode(', ', $dup);

        if ($db->query($sql)) $done++; else $errors++;
    }
}

// ── Couleur par scénario ──────────────────────────────────────────────────────
function sc_badge(int $n, array $sc): string {
    [$h, $d, $ac, $label, $color, $fx] = array_values($sc);
    $warn = $fx ? ' <span style="font-size:0.75em">⚠</span>' : '';
    return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:' . $color
         . ';color:#fff;font-size:11px;font-weight:bold">Sc.' . $n . ' — ' . htmlspecialchars($label) . $warn . '</span>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Gen — Scénarios Assurances</title>
<style>
body { font-family: Arial, sans-serif; max-width: 1100px; margin: 40px auto; padding: 0 20px; }
h2,h3 { color:#333; }
table { border-collapse:collapse; width:100%; margin-top:10px; font-size:13px; }
th,td { border:1px solid #ddd; padding:5px 10px; text-align:left; }
th { background:#f0f0f0; font-weight:bold; }
.warn { background:#fff3cd; border:1px solid #ffc107; padding:10px 16px; border-radius:6px; margin:10px 0; }
.ok   { background:#d4edda; border:1px solid #c3e6cb; padding:10px 16px; border-radius:6px; margin:10px 0; }
.sc-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin:14px 0; }
.sc-box { border-radius:8px; padding:12px; color:#fff; }
.sc-box .n { font-size:22px; font-weight:bold; }
.sc-box .l { font-size:11px; opacity:.9; margin-top:4px; }
.btn { background:#0d6efd; color:#fff; border:none; padding:10px 28px; border-radius:6px; font-size:15px; cursor:pointer; }
.btn:hover { background:#0b5ed7; }
.yellow { background:#fffbf0 !important; }
tr:nth-child(even) { background:#fafafa; }
</style>
</head>
<body>

<h2>🧪 Données test Assurances — tous les scénarios</h2>

<?php if (!empty($missing)): ?>
<div class="warn">
⚠️ Colonnes manquantes : <strong><?= implode(', ', $missing) ?></strong><br>
→ Désactivez puis réactivez le module <strong>Helpy</strong>, puis revenez ici.
</div>
<?php else: ?>

<?php if ($executed): ?>
<div class="ok">
✅ <strong><?= $done ?> contacts mis à jour</strong>
<?= $errors ? " · <span style='color:red'>$errors erreur(s)</span>" : '' ?>
<br><br>
<a href="agebf_assurances.php" style="font-weight:bold">→ Voir la page Assurances</a>
&nbsp;|&nbsp;
<a href="gen_assurances_test.php">↩ Relancer le script</a>
</div>
<?php endif; ?>

<!-- Légende des 8 scénarios -->
<h3>Les 8 scénarios</h3>
<div class="sc-grid">
<?php foreach ($scenarios as $n => [$h, $d, $ac, $label, $color, $fx]): ?>
<div class="sc-box" style="background:<?= $color ?>">
    <div class="n">Sc.<?= $n ?> &nbsp; <span style="font-size:14px"><?= $count_per_sc[$n] ?> contacts</span></div>
    <div class="l">
        <?= $label ?><br>
        Hospi : <?= $h ? '✓' : '—' ?> &nbsp; Dentaire : <?= $d ? '✓' : '—' ?> &nbsp; Assurcard : <?= $ac ?: '—' ?><br>
        <?= $fx ? '⚠ Surlignage jaune + bouton Sync' : '' ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<h3>Ce que tu vas voir dans la page Assurances après application</h3>
<table>
<tr>
    <th>Effet UI</th>
    <th>Déclenché par</th>
</tr>
<tr><td>🟣 Hospi. cochée</td><td>Scénarios 1, 3, 5, 7</td></tr>
<tr><td>🔵 Dentaire cochée</td><td>Scénarios 2, 3, 7, 8</td></tr>
<tr><td>🟢 N° Assurcard renseigné</td><td>Scénarios 5, 6, 7, 8</td></tr>
<tr class="yellow"><td>⚠ Fond jaune (Assurcard sans Hospi.)</td><td>Scénarios 6 et 8</td></tr>
<tr><td>🔔 Bouton "Sync Assurcard → Hospi."</td><td>Scénarios 6 et 8 présents</td></tr>
<tr><td>⚰ Tiers grisé Archivé</td><td>Mettre un Tiers en inactif dans Dolibarr<br><em>Fiche Tiers → Actions → Mettre en inactif</em></td></tr>
</table>

<!-- Tableau de prévisualisation -->
<h3>Répartition détaillée (<?= count($contacts) ?> contacts)</h3>
<table>
<tr>
    <th>CID</th>
    <th>Tiers</th>
    <th>Contact</th>
    <th>Poste</th>
    <th>Scénario</th>
    <th>Hospi.</th>
    <th>Dentaire</th>
    <th>Assurcard</th>
    <?php if ($executed): ?><th>Résultat</th><?php endif; ?>
</tr>
<?php foreach ($assignments as $cid => $a):
    $sc = $scenarios[$a['scenario']];
    $yellow = ($sc[2] !== '' && !$sc[0]) ? ' class="yellow"' : '';
?>
<tr<?= $yellow ?>>
    <td><?= $cid ?></td>
    <td style="color:#666;font-size:12px"><?= htmlspecialchars($a['soc_nom']) ?></td>
    <td><?= htmlspecialchars($a['nom']) ?></td>
    <td><?= htmlspecialchars($a['poste']) ?></td>
    <td><?= sc_badge($a['scenario'], $sc) ?></td>
    <td style="text-align:center"><?= $a['hospi']    ? '✓' : '' ?></td>
    <td style="text-align:center"><?= $a['dentaire'] ? '✓' : '' ?></td>
    <td style="font-size:12px;color:#1a7f37"><?= htmlspecialchars($a['assurcard']) ?: '<span style="color:#aaa">—</span>' ?></td>
    <?php if ($executed): ?><td style="text-align:center">✅</td><?php endif; ?>
</tr>
<?php endforeach; ?>
</table>

<?php if (!$executed): ?>
<br>
<form method="post">
    <button type="submit" name="go" value="1" class="btn">
        🚀 Appliquer les <?= count($contacts) ?> scénarios
    </button>
</form>
<?php endif; ?>

<?php endif; ?>
</body>
</html>
