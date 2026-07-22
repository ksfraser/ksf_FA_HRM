<?php

function ksf_fa_hrm_import_menu()
{
    add_menu_entry('employee_import', 'Import Employees', '', 'employee_import');
    
    add_shortcode('ksf_import_employees', function() {
        return ksf_render_hrm_import('employee');
    });
}

function ksf_render_hrm_import($type)
{
    $target_fields = [
        'emp_no',
        'first_name',
        'last_name',
        'email',
        'phone',
        'department', 
        'position',
        'location',
        'joined_date',
    ];

    $processor = function($row) use ($type) {
        global $db;
        include_once INCLUDES . '/db.inc';
        
        $emp_no = $row['emp_no'] ?? '';
        if (empty($emp_no)) return false;
        
        $check = db_fetch_assoc(db_query(
            "SELECT emp_no FROM " . TB_PREF . "ksf_employees WHERE emp_no = " . db_escape($emp_no)
        ));
        
        if ($check) {
            $sets = [];
            foreach ($row as $f => $v) {
                if ($f !== 'emp_no') $sets[] = "$f = " . db_escape($v);
            }
            db_query("UPDATE " . TB_PREF . "ksf_employees SET " . implode(', ', $sets) . " WHERE emp_no = " . db_escape($emp_no));
        } else {
            $cols = implode(', ', array_keys($row));
            $vals = implode(', ', array_map(fn($v) => db_escape($v), array_values($row)));
            db_query("INSERT INTO " . TB_PREF . "ksf_employees ($cols) VALUES ($vals)");
        }
        
        return ['emp_no' => $emp_no];
    };
    
    ob_start();
    
    $step = $_GET['import_step'] ?? 1;
    
    if ($step == 1) {
        ?>
        <form method="post" enctype="multipart/form-data" class="ksf-import-form">
            <h3>Import Employees</h3>
            <p>Upload a CSV file with employee data.</p>
            
            <input type="hidden" name="proc" value="ksf_import">
            <input type="hidden" name="import_step" value="2">
            
            <div class="form-group">
                <label>Select File (CSV)</label>
                <input type="file" name="import_file" accept=".csv" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Next</button>
        </form>
        <?php
    } elseif ($step == 2) {
        ksf_render_field_mapping($target_fields);
    }
    
    return ob_get_clean();
}

function ksf_render_field_mapping($target_fields)
{
    $data = $_SESSION['ksf_import_data'] ?? [];
    $headers = $data['headers'] ?? [];
    $sample = $data['sample'] ?? [];
    
    if (empty($headers)) {
        echo '<p>Please upload a file first. <a href="?">Back</a></p>';
        return;
    }
    
    $mappingSvc = new \Ksfraser\DataIO\FieldMappingService();
    $autoMap = $mappingSvc->autoMap($headers, $target_fields);
    ?>
    <form method="post" class="ksf-import-form">
        <h3>Map Fields</h3>
        
        <input type="hidden" name="proc" value="ksf_import">
        <input type="hidden" name="import_step" value="3">
        
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>File Column</th>
                    <th>Sample</th>
                    <th></th>
                    <th>Target Field</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($headers as $field): ?>
                <tr>
                    <td><?php echo $field; ?></td>
                    <td><small><?php echo $sample[$field] ?? ''; ?></small></td>
                    <td>→</td>
                    <td>
                        <select name="mapping[<?php echo $field; ?>]">
                            <option value="">-- Skip --</option>
                            <?php foreach ($target_fields as $tf): ?>
                            <option value="<?php echo $tf; ?>" <?php echo ($autoMap[$field] ?? '') === $tf ? 'selected' : ''; ?>>
                                <?php echo $tf; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <button type="submit" class="btn btn-primary">Next</button>
        <a href="?" class="btn">Back</a>
    </form>
    <?php
}

add_hook('ksf_fa_hrm_install', 'ksf_fa_hrm_import_menu');