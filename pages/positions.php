<?php
$path_to_root = "../..";

$sql = "SELECT * FROM " . TB_PREF . "fa_positions ORDER BY name ASC";
$result = db_query($sql);
?>
<div class="card">
<div class="card-header"><?php echo _("Positions"); ?></div>
<div class="card-body">
<table class="table table-sm table-striped">
<thead class="thead-dark">
<tr>
    <th><?php echo _("Code"); ?></th>
    <th><?php echo _("Name"); ?></th>
    <th><?php echo _("Description"); ?></th>
    <th><?php echo _("Status"); ?></th>
</tr>
</thead>
<tbody>
<?php
if ($result && db_num_rows($result)) {
    while ($row = db_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['position_code'] ?? $row['code'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['description'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['is_active'] ?? $row['status'] ?? '') . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='4' class='text-muted'>" . _("No records found.") . "</td></tr>";
}
?>
</tbody></table>
</div></div>
