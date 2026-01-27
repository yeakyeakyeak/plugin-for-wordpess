<?php
/**
 * Plugin Name: LearnFlow Admin - Form Builder
 * Description: Plugin for creating custom registration forms, login forms, and application management.
 * Version: 1.9
 * Author: YeakYeakYeak
 */

// Create tables on activation
register_activation_hook(__FILE__, 'sa_create_tables');
function sa_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Course applications table
    $table_apps = $wpdb->prefix . 'survival_applications';
    $sql_apps = "CREATE TABLE $table_apps (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        course_name VARCHAR(255) NOT NULL,
        start_date DATE NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'New',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    // Application feedback table
    $table_feedback = $wpdb->prefix . 'survival_app_feedback';
    $sql_feedback = "CREATE TABLE $table_feedback (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        application_id MEDIUMINT(9) NOT NULL,
        feedback_text TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        KEY application_id (application_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_apps);
    dbDelta($sql_feedback);
}

// ------------------- FORM BUILDER --------------------

add_action('admin_menu', 'sa_add_form_builder_menu');

function sa_add_form_builder_menu() {
    // Main form builder menu
    add_menu_page(
        'Form Builder', 
        'Form Builder', 
        'manage_options', 
        'sa_form_builder', 
        'sa_render_form_builder_page',
        'dashicons-forms'
    );
    
    // Submenu for registration forms
    add_submenu_page(
        'sa_form_builder',
        'Registration Forms',
        'Registration Forms',
        'manage_options',
        'sa_registration_forms',
        'sa_render_registration_forms_page'
    );
    
    // Submenu for login forms
    add_submenu_page(
        'sa_form_builder',
        'Login Forms',
        'Login Forms',
        'manage_options',
        'sa_login_forms',
        'sa_render_login_forms_page'
    );
    
    // SEPARATE menu for course applications
    add_menu_page(
        'Course Applications',
        'Applications',
        'manage_options',
        'sa_applications',
        'sa_applications_page',
        'dashicons-clipboard'
    );
}

// ------------------- REGISTRATION FORMS PAGE --------------------

function sa_render_registration_forms_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }
    
    global $wpdb;
    
    // Handle form deletion
    if (isset($_GET['delete']) && !empty($_GET['delete'])) {
        $form_id = sanitize_key($_GET['delete']);
        $forms = get_option('sa_registration_forms', []);
        
        if (isset($forms[$form_id])) {
            unset($forms[$form_id]);
            update_option('sa_registration_forms', $forms);
            echo '<div class="updated"><p>Form deleted.</p></div>';
        }
    }
    
    // Handle form saving
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sa_save_registration_form'])) {
        check_admin_referer('sa_registration_form_save');
        
        $form_id = sanitize_key($_POST['form_id']);
        $form_name = sanitize_text_field($_POST['form_name']);
        $fields = isset($_POST['fields']) ? $_POST['fields'] : [];
        
        // Validate and process fields
        $validated_fields = [];
        foreach ($fields as $index => $field) {
            if (!empty($field['name']) && !empty($field['label'])) {
                $field_name = sanitize_key($field['name']);
                
                // AUTOMATIC RESTRICTIONS FOR STANDARD FIELDS
                $auto_pattern = '';
                $auto_minlength = '';
                $auto_maxlength = '';
                
                // Determine field type by name or label
                $field_lower_name = strtolower($field_name);
                $field_lower_label = strtolower($field['label']);
                
                // LOGIN: Latin letters and numbers, at least 6 characters
                if (strpos($field_lower_name, 'login') !== false || 
                    strpos($field_lower_name, 'username') !== false ||
                    strpos($field_lower_label, 'login') !== false) {
                    $auto_pattern = '^[a-zA-Z0-9]{6,}$';
                    $auto_minlength = '6';
                }
                
                // PASSWORD: minimum 8 characters, ANY characters
                elseif (strpos($field_lower_name, 'password') !== false ||
                        strpos($field_lower_name, 'pass') !== false ||
                        strpos($field_lower_label, 'password') !== false) {
                    $auto_minlength = '8';
                    // DO NOT set pattern for password! Password can be anything
                }
                
                // FULL NAME: Cyrillic, spaces
                elseif (strpos($field_lower_name, 'fio') !== false ||
                        strpos($field_lower_name, 'full_name') !== false ||
                        strpos($field_lower_label, 'fio') !== false ||
                        strpos($field_lower_label, 'full name') !== false) {
                    $auto_pattern = '^[а-яА-ЯёЁ]+[\\s\\-][а-яА-ЯёЁ]+[\\s\\-][а-яА-ЯёЁ]+$';
                }
                
                // PHONE: format 8(XXX)XXX-XX-XX
                elseif (strpos($field_lower_name, 'phone') !== false ||
                        strpos($field_lower_name, 'tel') !== false ||
                        strpos($field_lower_label, 'phone') !== false) {
                    $auto_pattern = '^8\(\d{3}\)\d{3}-\d{2}-\d{2}$';
                }
                
                // EMAIL: standard email format
                elseif (strpos($field_lower_name, 'email') !== false ||
                        strpos($field_lower_name, 'mail') !== false ||
                        strpos($field_lower_label, 'email') !== false ||
                        strpos($field_lower_label, 'e-mail') !== false) {
                    // Leave pattern empty for email, WordPress will validate it
                }
                
                // Fix: properly handle pattern (remove extra escaping)
                $pattern_value = '';
                if (!empty($field['pattern'])) {
                    // Remove extra escaping
                    $pattern_value = stripslashes($field['pattern']);
                    // Remove leading and trailing slashes if present
                    $pattern_value = trim($pattern_value, '/');
                } elseif (!empty($auto_pattern)) {
                    $pattern_value = $auto_pattern;
                }
                
                $validated_fields[] = [
                    'name' => $field_name,
                    'label' => sanitize_text_field($field['label']),
                    'type' => in_array($field['type'], ['text', 'password', 'email', 'tel', 'number', 'textarea', 'select', 'checkbox', 'radio', 'date']) 
                                ? $field['type'] : 'text',
                    'placeholder' => sanitize_text_field($field['placeholder']),
                    'required' => isset($field['required']) ? 1 : 0,
                    'options' => !empty($field['options']) ? sanitize_text_field($field['options']) : '',
                    'minlength' => !empty($field['minlength']) ? intval($field['minlength']) : (!empty($auto_minlength) ? $auto_minlength : ''),
                    'maxlength' => !empty($field['maxlength']) ? intval($field['maxlength']) : (!empty($auto_maxlength) ? $auto_maxlength : ''),
                    'pattern' => $pattern_value
                ];
            }
        }
        
        // Save the form
        $forms = get_option('sa_registration_forms', []);
        $forms[$form_id] = [
            'name' => $form_name,
            'id' => $form_id,
            'fields' => $validated_fields,
            'created' => current_time('mysql')
        ];
        update_option('sa_registration_forms', $forms);
        
        echo '<div class="updated"><p>Form "' . esc_html($form_name) . '" saved! Use shortcode: <code>[sa_register_' . esc_html($form_id) . ']</code></p></div>';
    }
    
    // Get all registration forms
    $registration_forms = get_option('sa_registration_forms', []);
    
    // If editing existing form
    $editing_form = null;
    if (isset($_GET['edit']) && !empty($_GET['edit']) && isset($registration_forms[$_GET['edit']])) {
        $editing_form = $registration_forms[$_GET['edit']];
    }
    
    ?>
    <div class="wrap">
        <h1>Registration Forms</h1>
        
        <div class="sa-forms-container">
            <!-- Form editor -->
            <div class="sa-form-editor">
                <h2><?php echo $editing_form ? 'Edit Form' : 'Create New Registration Form'; ?></h2>
                
                <form method="post">
                    <?php wp_nonce_field('sa_registration_form_save'); ?>
                    
                    <div class="form-meta">
                        <p>
                            <label><strong>Form Name:</strong><br>
                                <input type="text" name="form_name" 
                                       value="<?php echo $editing_form ? esc_attr($editing_form['name']) : ''; ?>" 
                                       placeholder="Example: Main Registration Form" required>
                            </label>
                        </p>
                        
                        <p>
                            <label><strong>Form ID (English letters only):</strong><br>
                                <input type="text" name="form_id" 
                                       value="<?php echo $editing_form ? esc_attr($editing_form['id']) : ''; ?>" 
                                       pattern="[a-z0-9_]+" 
                                       placeholder="main_register" required>
                                <br><small>Shortcode will be: <code>[sa_register_<span id="shortcode-preview"><?php echo $editing_form ? esc_html($editing_form['id']) : 'your_id'; ?></span>]</code></small>
                            </label>
                        </p>
                    </div>
                    
                    <h3>Form Fields</h3>
                    <div class="sa-field-info">
                        <p><strong>Automatic restrictions for standard fields:</strong></p>
                        <ul>
                            <li><strong>Login:</strong> Latin letters and numbers, at least 6 characters</li>
                            <li><strong>Password:</strong> minimum 8 characters (any characters)</li>
                            <li><strong>Full Name:</strong> Cyrillic, spaces (Last Name First Name Middle Name)</li>
                            <li><strong>Phone:</strong> format 8(XXX)XXX-XX-XX</li>
                            <li><strong>Email:</strong> standard email format</li>
                        </ul>
                        <p><em>Restrictions are applied automatically when field name or label matches.</em></p>
                    </div>
                    
                    <div id="fields-container">
                        <?php if ($editing_form && !empty($editing_form['fields'])): ?>
                            <?php foreach ($editing_form['fields'] as $index => $field): ?>
                                <div class="field-editor" data-index="<?php echo $index; ?>">
                                    <div class="field-header">
                                        <h4>Field <?php echo $index + 1; ?>: <?php echo esc_html($field['label']); ?></h4>
                                        <button type="button" class="remove-field button button-small">Delete</button>
                                    </div>
                                    <div class="field-inputs">
                                        <input type="text" name="fields[<?php echo $index; ?>][label]" 
                                               placeholder="Field label (example: Login)" 
                                               value="<?php echo esc_attr($field['label']); ?>" required>
                                        <input type="text" name="fields[<?php echo $index; ?>][name]" 
                                               placeholder="Field name (English, example: username)" 
                                               value="<?php echo esc_attr($field['name']); ?>" required>
                                        <select name="fields[<?php echo $index; ?>][type]" class="field-type-select">
                                            <option value="text" <?php selected($field['type'], 'text'); ?>>Text</option>
                                            <option value="password" <?php selected($field['type'], 'password'); ?>>Password</option>
                                            <option value="email" <?php selected($field['type'], 'email'); ?>>Email</option>
                                            <option value="tel" <?php selected($field['type'], 'tel'); ?>>Phone</option>
                                            <option value="number" <?php selected($field['type'], 'number'); ?>>Number</option>
                                            <option value="textarea" <?php selected($field['type'], 'textarea'); ?>>Textarea</option>
                                            <option value="select" <?php selected($field['type'], 'select'); ?>>Dropdown</option>
                                            <option value="checkbox" <?php selected($field['type'], 'checkbox'); ?>>Checkbox</option>
                                            <option value="radio" <?php selected($field['type'], 'radio'); ?>>Radio buttons</option>
                                            <option value="date" <?php selected($field['type'], 'date'); ?>>Date</option>
                                        </select>
                                        <input type="text" name="fields[<?php echo $index; ?>][placeholder]" 
                                               placeholder="Field placeholder" 
                                               value="<?php echo esc_attr($field['placeholder']); ?>">
                                        
                                        <!-- Parameters for different field types -->
                                        <div class="field-params">
                                            <div class="field-option" data-show="select,radio">
                                                <input type="text" name="fields[<?php echo $index; ?>][options]" 
                                                       placeholder="Options separated by commas (for select/radio)" 
                                                       value="<?php echo esc_attr($field['options']); ?>">
                                            </div>
                                            <div class="field-option" data-show="text,password,email,tel,number,textarea">
                                                <input type="number" name="fields[<?php echo $index; ?>][minlength]" 
                                                       placeholder="Min length (auto)" min="0"
                                                       value="<?php echo esc_attr($field['minlength']); ?>">
                                                <input type="number" name="fields[<?php echo $index; ?>][maxlength]" 
                                                       placeholder="Max length" min="1"
                                                       value="<?php echo esc_attr($field['maxlength']); ?>">
                                            </div>
                                            <div class="field-option" data-show="text,password,tel">
                                                <input type="text" name="fields[<?php echo $index; ?>][pattern]" 
                                                       placeholder="Regular expression (auto)" 
                                                       value="<?php echo esc_attr($field['pattern']); ?>"
                                                       title="Automatically filled for standard fields">
                                            </div>
                                        </div>
                                        
                                        <label class="field-required">
                                            <input type="checkbox" name="fields[<?php echo $index; ?>][required]" value="1" <?php checked($field['required'], 1); ?>> Required field
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" id="add-field-btn" class="button button-primary">+ Add Field</button>
                    
                    <div class="form-actions">
                        <input type="submit" name="sa_save_registration_form" class="button button-primary button-large" 
                               value="<?php echo $editing_form ? 'Update Form' : 'Save Form'; ?>">
                        <?php if ($editing_form): ?>
                            <a href="?page=sa_registration_forms" class="button button-large">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Forms list -->
            <div class="sa-forms-list">
                <h2>Saved Registration Forms</h2>
                
                <?php if (empty($registration_forms)): ?>
                    <p>No saved forms. Create your first form.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Shortcode</th>
                                <th>Fields</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registration_forms as $form_id => $form): ?>
                            <tr>
                                <td><strong><?php echo esc_html($form['name']); ?></strong></td>
                                <td><code>[sa_register_<?php echo esc_html($form_id); ?>]</code></td>
                                <td><?php echo count($form['fields']); ?> fields</td>
                                <td><?php echo date('m.d.Y', strtotime($form['created'])); ?></td>
                                <td>
                                    <a href="?page=sa_registration_forms&edit=<?php echo esc_attr($form_id); ?>" class="button button-small">Edit</a>
                                    <a href="?page=sa_registration_forms&delete=<?php echo esc_attr($form_id); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('Delete form «<?php echo esc_js($form['name']); ?>»?')">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <div class="sa-form-info">
                    <h3>How to use:</h3>
                    <ol>
                        <li>Create a form with needed fields</li>
                        <li>For standard fields, restrictions are applied automatically</li>
                        <li>Insert shortcode on page: <code>[sa_register_FORM_ID]</code></li>
                        <li>Add "Not registered yet? Register" link to login form</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('fields-container');
            const addBtn = document.getElementById('add-field-btn');
            const idInput = document.querySelector('input[name="form_id"]');
            const previewSpan = document.getElementById('shortcode-preview');
            let fieldIndex = <?php echo $editing_form ? count($editing_form['fields']) : 0; ?>;
            
            // Update shortcode preview
            if (idInput && previewSpan) {
                idInput.addEventListener('input', function() {
                    previewSpan.textContent = this.value || 'your_id';
                });
            }
            
            // Function to determine automatic restrictions
            function getAutoRestrictions(fieldName, fieldLabel, fieldType) {
                const nameLower = fieldName.toLowerCase();
                const labelLower = fieldLabel.toLowerCase();
                let pattern = '';
                let minlength = '';
                
                // Login
                if (nameLower.includes('login') || nameLower.includes('username') || labelLower.includes('login')) {
                    pattern = '^[a-zA-Z0-9]{6,}$';
                    minlength = '6';
                }
                // Password - DO NOT set pattern! Only minlength
                else if (nameLower.includes('password') || nameLower.includes('pass') || labelLower.includes('password')) {
                    minlength = '8';
                    // Leave pattern empty
                }
                // Full name: Cyrillic, spaces
                else if (nameLower.includes('fio') || nameLower.includes('full_name') || labelLower.includes('fio') || labelLower.includes('full name')) {
                    pattern = '^[а-яА-ЯёЁ]+[\\s\\-][а-яА-ЯёЁ]+[\\s\\-][а-яА-ЯёЁ]+$';
                }
                // Phone
                else if (nameLower.includes('phone') || nameLower.includes('tel') || labelLower.includes('phone')) {
                    pattern = '^8\\(\\d{3}\\)\\d{3}-\\d{2}-\\d{2}$';
                }
                
                return { pattern, minlength };
            }
            
            // Add new field
            addBtn.addEventListener('click', function() {
                const index = fieldIndex++;
                const fieldHtml = `
                    <div class="field-editor" data-index="${index}">
                        <div class="field-header">
                            <h4>New Field</h4>
                            <button type="button" class="remove-field button button-small">Delete</button>
                        </div>
                        <div class="field-inputs">
                            <input type="text" name="fields[${index}][label]" 
                                   placeholder="Field label (example: Login)" 
                                   class="field-label-input" required>
                            <input type="text" name="fields[${index}][name]" 
                                   placeholder="Field name (English, example: username)" 
                                   class="field-name-input" required>
                            <select name="fields[${index}][type]" class="field-type-select">
                                <option value="text">Text</option>
                                <option value="password">Password</option>
                                <option value="email">Email</option>
                                <option value="tel">Phone</option>
                                <option value="number">Number</option>
                                <option value="textarea">Textarea</option>
                                <option value="select">Dropdown</option>
                                <option value="checkbox">Checkbox</option>
                                <option value="radio">Radio buttons</option>
                                <option value="date">Date</option>
                            </select>
                            <input type="text" name="fields[${index}][placeholder]" 
                                   placeholder="Field placeholder">
                            
                            <div class="field-params">
                                <div class="field-option" data-show="select,radio">
                                    <input type="text" name="fields[${index}][options]" 
                                           placeholder="Options separated by commas (for select/radio)">
                                </div>
                                <div class="field-option" data-show="text,password,email,tel,number,textarea">
                                    <input type="number" name="fields[${index}][minlength]" 
                                           placeholder="Min length (auto)" min="0" class="field-minlength-input">
                                    <input type="number" name="fields[${index}][maxlength]" 
                                           placeholder="Max length" min="1">
                                </div>
                                <div class="field-option" data-show="text,password,tel">
                                    <input type="text" name="fields[${index}][pattern]" 
                                           placeholder="Regular expression (auto)" 
                                           class="field-pattern-input"
                                           title="Automatically filled for standard fields">
                                </div>
                            </div>
                            
                            <label class="field-required">
                                <input type="checkbox" name="fields[${index}][required]" value="1"> Required field
                            </label>
                        </div>
                    </div>`;
                
                container.insertAdjacentHTML('beforeend', fieldHtml);
                
                // Add handlers for new field
                const newField = container.lastElementChild;
                const typeSelect = newField.querySelector('.field-type-select');
                const labelInput = newField.querySelector('.field-label-input');
                const nameInput = newField.querySelector('.field-name-input');
                const minlengthInput = newField.querySelector('.field-minlength-input');
                const patternInput = newField.querySelector('.field-pattern-input');
                
                // Function to update restrictions
                function updateAutoRestrictions() {
                    const fieldName = nameInput.value;
                    const fieldLabel = labelInput.value;
                    const fieldType = typeSelect.value;
                    
                    const restrictions = getAutoRestrictions(fieldName, fieldLabel, fieldType);
                    
                    // Set automatic values only if fields are empty
                    if (restrictions.pattern && (!patternInput.value || patternInput.value.includes('auto'))) {
                        patternInput.value = restrictions.pattern;
                    } else if (!restrictions.pattern && fieldType === 'password') {
                        // For password REMOVE pattern if it was set
                        patternInput.value = '';
                    }
                    
                    if (restrictions.minlength && (!minlengthInput.value || minlengthInput.value === '0')) {
                        minlengthInput.value = restrictions.minlength;
                    }
                }
                
                // Events for automatic restriction updates
                labelInput.addEventListener('input', updateAutoRestrictions);
                nameInput.addEventListener('input', updateAutoRestrictions);
                typeSelect.addEventListener('change', function() {
                    updateAutoRestrictions();
                    updateFieldParamsVisibility(this);
                });
                
                // Initialization
                updateAutoRestrictions();
                updateFieldParamsVisibility(typeSelect);
            });
            
            // Handle existing field type changes
            document.querySelectorAll('.field-type-select').forEach(select => {
                select.addEventListener('change', function() {
                    updateFieldParamsVisibility(this);
                });
                
                // Initialization on load
                updateFieldParamsVisibility(select);
            });
            
            // Add handlers for existing fields
            document.querySelectorAll('.field-label-input, .field-name-input').forEach(input => {
                input.addEventListener('input', function() {
                    const fieldEditor = this.closest('.field-editor');
                    const nameInput = fieldEditor.querySelector('.field-name-input');
                    const labelInput = fieldEditor.querySelector('.field-label-input');
                    const typeSelect = fieldEditor.querySelector('.field-type-select');
                    const patternInput = fieldEditor.querySelector('.field-pattern-input');
                    const minlengthInput = fieldEditor.querySelector('.field-minlength-input');
                    
                    if (nameInput && labelInput && typeSelect && patternInput && minlengthInput) {
                        const restrictions = getAutoRestrictions(
                            nameInput.value, 
                            labelInput.value, 
                            typeSelect.value
                        );
                        
                        // Update only if current values are empty or automatic
                        if (restrictions.pattern && (!patternInput.value || patternInput.value.includes('auto'))) {
                            patternInput.value = restrictions.pattern;
                        } else if (!restrictions.pattern && typeSelect.value === 'password') {
                            // For password REMOVE pattern
                            patternInput.value = '';
                        }
                        
                        if (restrictions.minlength && (!minlengthInput.value || minlengthInput.value === '0')) {
                            minlengthInput.value = restrictions.minlength;
                        }
                    }
                });
            });
            
            // Delete field
            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-field')) {
                    e.target.closest('.field-editor').remove();
                    updateFieldNumbers();
                }
            });
            
            // Function to update parameter visibility
            function updateFieldParamsVisibility(selectElement) {
                const fieldInputs = selectElement.closest('.field-inputs');
                const fieldType = selectElement.value;
                
                // Hide all parameters
                fieldInputs.querySelectorAll('.field-option').forEach(option => {
                    option.style.display = 'none';
                });
                
                // Show needed parameters
                fieldInputs.querySelectorAll('.field-option').forEach(option => {
                    const showFor = option.dataset.show.split(',');
                    if (showFor.includes(fieldType)) {
                        option.style.display = 'block';
                    }
                });
            }
            
            // Function to update field numbering
            function updateFieldNumbers() {
                document.querySelectorAll('.field-editor').forEach((field, idx) => {
                    const header = field.querySelector('.field-header h4');
                    if (header) {
                        const fieldName = field.querySelector('input[name$="[label]"]')?.value || 'New Field';
                        header.textContent = `Field ${idx + 1}: ${fieldName}`;
                    }
                });
            }
            
            // Update field number when label changes
            container.addEventListener('input', function(e) {
                if (e.target.name && e.target.name.includes('[label]')) {
                    const fieldEditor = e.target.closest('.field-editor');
                    const fieldIndex = Array.from(container.children).indexOf(fieldEditor);
                    const header = fieldEditor.querySelector('.field-header h4');
                    if (header) {
                        header.textContent = `Field ${fieldIndex + 1}: ${e.target.value}`;
                    }
                }
            });
        });
    </script>
    
    <style>
        .sa-forms-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        .sa-form-editor, .sa-forms-list {
            background: #fff;
            padding: 25px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .form-meta input[type="text"] {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            margin-bottom: 15px;
        }
        .sa-field-info {
            background: #f0f8ff;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #0073aa;
            border-radius: 4px;
        }
        .sa-field-info ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .sa-field-info li {
            margin-bottom: 5px;
        }
        .field-editor {
            border: 2px solid #e0e0e0;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            background: #fafafa;
        }
        .field-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .field-header h4 {
            margin: 0;
            color: #1d2327;
        }
        .field-inputs input[type="text"],
        .field-inputs input[type="number"],
        .field-inputs select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            box-sizing: border-box;
        }
        .field-params {
            margin: 10px 0;
        }
        .field-option {
            display: none;
            background: #f0f0f0;
            padding: 10px;
            border-radius: 3px;
            margin-bottom: 10px;
        }
        .field-option input[type="text"],
        .field-option input[type="number"] {
            margin-right: 10px;
            margin-bottom: 5px;
        }
        .field-required {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
        }
        .form-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .sa-form-info {
            background: #f0f8ff;
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid #0073aa;
        }
        .sa-form-info h3 {
            margin-top: 0;
        }
        @media (max-width: 1200px) {
            .sa-forms-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php
}

// ------------------- LOGIN FORMS PAGE --------------------

function sa_render_login_forms_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }
    
    // Handle login form saving
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sa_save_login_form'])) {
        check_admin_referer('sa_login_form_save');
        
        $form_id = sanitize_key($_POST['form_id']);
        $form_name = sanitize_text_field($_POST['form_name']);
        $registration_form = sanitize_key($_POST['registration_form']);
        $registration_text = sanitize_text_field($_POST['registration_text']);
        
        // Save login form
        $login_forms = get_option('sa_login_forms', []);
        $login_forms[$form_id] = [
            'name' => $form_name,
            'id' => $form_id,
            'registration_form' => $registration_form,
            'registration_text' => $registration_text ?: 'Not registered yet? Register',
            'created' => current_time('mysql')
        ];
        update_option('sa_login_forms', $login_forms);
        
        echo '<div class="updated"><p>Login form "' . esc_html($form_name) . '" saved! Use shortcode: <code>[sa_login_' . esc_html($form_id) . ']</code></p></div>';
    }
    
    // Get all login and registration forms
    $login_forms = get_option('sa_login_forms', []);
    $registration_forms = get_option('sa_registration_forms', []);
    
    // If editing existing form
    $editing_form = null;
    if (isset($_GET['edit']) && !empty($_GET['edit']) && isset($login_forms[$_GET['edit']])) {
        $editing_form = $login_forms[$_GET['edit']];
    }
    
    ?>
    <div class="wrap">
        <h1>Login Forms</h1>
        
        <div class="sa-login-forms-container">
            <!-- Login form editor -->
            <div class="sa-form-editor">
                <h2><?php echo $editing_form ? 'Edit Login Form' : 'Create New Login Form'; ?></h2>
                
                <form method="post">
                    <?php wp_nonce_field('sa_login_form_save'); ?>
                    
                    <div class="form-meta">
                        <p>
                            <label><strong>Form Name:</strong><br>
                                <input type="text" name="form_name" 
                                       value="<?php echo $editing_form ? esc_attr($editing_form['name']) : ''; ?>" 
                                       placeholder="Example: Main Login Form" required>
                            </label>
                        </p>
                        
                        <p>
                            <label><strong>Form ID (English letters only):</strong><br>
                                <input type="text" name="form_id" 
                                       value="<?php echo $editing_form ? esc_attr($editing_form['id']) : ''; ?>" 
                                       pattern="[a-z0-9_]+" 
                                       placeholder="main_login" required>
                                <br><small>Shortcode will be: <code>[sa_login_<span id="login-shortcode-preview"><?php echo $editing_form ? esc_html($editing_form['id']) : 'your_id'; ?></span>]</code></small>
                            </label>
                        </p>
                        
                        <p>
                            <label><strong>Link to registration form:</strong><br>
                                <select name="registration_form">
                                    <option value="">— Don't add link —</option>
                                    <?php foreach ($registration_forms as $reg_id => $reg_form): ?>
                                        <option value="<?php echo esc_attr($reg_id); ?>" 
                                            <?php selected($editing_form ? $editing_form['registration_form'] : '', $reg_id); ?>>
                                            <?php echo esc_html($reg_form['name']); ?> ([sa_register_<?php echo esc_html($reg_id); ?>])
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </p>
                        
                        <p>
                            <label><strong>Registration link text:</strong><br>
                                <input type="text" name="registration_text" 
                                       value="<?php echo $editing_form ? esc_attr($editing_form['registration_text']) : 'Not registered yet? Register'; ?>" 
                                       placeholder="Not registered yet? Register">
                            </label>
                        </p>
                    </div>
                    
                    <h3>Standard login form fields:</h3>
                    <ul>
                        <li><strong>Login:</strong> text field (required)</li>
                        <li><strong>Password:</strong> password field (required)</li>
                    </ul>
                    
                    <div class="form-actions">
                        <input type="submit" name="sa_save_login_form" class="button button-primary button-large" 
                               value="<?php echo $editing_form ? 'Update Form' : 'Save Form'; ?>">
                        <?php if ($editing_form): ?>
                            <a href="?page=sa_login_forms" class="button button-large">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Login forms list -->
            <div class="sa-forms-list">
                <h2>Saved Login Forms</h2>
                
                <?php if (empty($login_forms)): ?>
                    <p>No saved login forms.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Shortcode</th>
                                <th>Registration Link</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($login_forms as $form_id => $form): ?>
                            <tr>
                                <td><strong><?php echo esc_html($form['name']); ?></strong></td>
                                <td><code>[sa_login_<?php echo esc_html($form_id); ?>]</code></td>
                                <td>
                                    <?php if (!empty($form['registration_form'])): ?>
                                        Yes (<?php echo esc_html($form['registration_text']); ?>)
                                    <?php else: ?>
                                        No
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('m.d.Y', strtotime($form['created'])); ?></td>
                                <td>
                                    <a href="?page=sa_login_forms&edit=<?php echo esc_attr($form_id); ?>" class="button button-small">Edit</a>
                                    <a href="?page=sa_login_forms&delete=<?php echo esc_attr($form_id); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('Delete form «<?php echo esc_js($form['name']); ?>»?')">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Shortcode preview for login form
            const loginInput = document.querySelector('input[name="form_id"]');
            const loginPreview = document.getElementById('login-shortcode-preview');
            
            if (loginInput && loginPreview) {
                loginInput.addEventListener('input', function() {
                    loginPreview.textContent = this.value || 'your_id';
                });
            }
        });
    </script>
    
    <style>
        .sa-login-forms-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
    </style>
    <?php
}

// ------------------- FORM SHORTCODES --------------------

// Register all shortcodes
add_action('init', 'sa_register_all_shortcodes');
function sa_register_all_shortcodes() {
    // Register registration forms
    $registration_forms = get_option('sa_registration_forms', []);
    foreach ($registration_forms as $form_id => $form_data) {
        add_shortcode('sa_register_' . $form_id, function() use ($form_data) {
            return sa_render_registration_form($form_data);
        });
    }
    
    // Register login forms
    $login_forms = get_option('sa_login_forms', []);
    foreach ($login_forms as $form_id => $form_data) {
        add_shortcode('sa_login_' . $form_id, function() use ($form_data) {
            return sa_render_login_form($form_data);
        });
    }
}

// Registration form rendering function - FIXED VERSION
function sa_render_registration_form($form_data) {
    if (is_user_logged_in()) {
        return '<p>You are already registered and logged in.</p>';
    }
    
    ob_start();
    
    // Form submission handling
    $errors = [];
    $success = false;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sa_register_nonce'])) {
        if (wp_verify_nonce($_POST['sa_register_nonce'], 'sa_register_action_' . $form_data['id'])) {
            
            // Collect data
            $user_data = [];
            $meta_data = [];
            
            foreach ($form_data['fields'] as $field) {
                $value = isset($_POST[$field['name']]) ? sanitize_text_field($_POST[$field['name']]) : '';
                
                // Validate required fields
                if ($field['required'] && empty($value)) {
                    $errors[] = 'Field "' . $field['label'] . '" is required.';
                    continue;
                }
                
                // Skip validation for optional empty fields
                if (empty($value) && !$field['required']) {
                    continue;
                }
                
                // Check min/max length
                if (!empty($field['minlength']) && strlen($value) < $field['minlength']) {
                    $errors[] = 'Field "' . $field['label'] . '" must contain at least ' . $field['minlength'] . ' characters.';
                }
                
                if (!empty($field['maxlength']) && strlen($value) > $field['maxlength']) {
                    $errors[] = 'Field "' . $field['label'] . '" must contain at most ' . $field['maxlength'] . ' characters.';
                }
                
                // Determine field type
                $field_lower_label = strtolower($field['label']);
                $field_lower_name = strtolower($field['name']);
                
                $is_fio_field = (strpos($field_lower_label, 'fio') !== false || 
                                 strpos($field_lower_name, 'fio') !== false ||
                                 strpos($field_lower_name, 'full_name') !== false);
                
                $is_phone_field = (strpos($field_lower_label, 'phone') !== false || 
                                   strpos($field_lower_name, 'phone') !== false ||
                                   strpos($field_lower_name, 'tel') !== false);
                
                $is_login_field = (strpos($field_lower_label, 'login') !== false || 
                                   strpos($field_lower_name, 'login') !== false ||
                                   strpos($field_lower_name, 'username') !== false);
                
                // Handle Full Name - EXACTLY 3 WORDS
                if ($is_fio_field && !empty($value)) {
                    // Check that there are exactly 3 words
                    $words = preg_split('/\s+/', trim($value));
                    $words = array_filter($words, function($word) {
                        return !empty($word);
                    });
                    
                    if (count($words) !== 3) {
                        $errors[] = 'Full Name must contain exactly 3 words (Last Name First Name Middle Name)';
                    } else {
                        // Check that all words contain only Cyrillic and hyphens
                        foreach ($words as $word) {
                            if (!preg_match('/^[а-яА-ЯёЁ\-]+$/u', $word)) {
                                $errors[] = 'Full Name must contain only Cyrillic letters and hyphens';
                                break;
                            }
                        }
                        
                        // Automatic capitalization of Full Name
                        if (empty($errors)) {
                            $words = array_map(function($word) {
                                return mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
                            }, $words);
                            $value = implode(' ', $words);
                            $_POST[$field['name']] = $value;
                        }
                    }
                }
                
                // Handle phone - auto-formatting and validation
                if ($is_phone_field && !empty($value)) {
                    // Remove all non-digit characters
                    $clean_phone = preg_replace('/\D/', '', $value);
                    
                    // If number starts with 7, change to 8
                    if (strlen($clean_phone) === 11 && $clean_phone[0] === '7') {
                        $clean_phone = '8' . substr($clean_phone, 1);
                    }
                    
                    // Check format: starts with 8 and has 11 digits
                    if (!preg_match('/^8\d{10}$/', $clean_phone)) {
                        $errors[] = 'Phone must contain 11 digits and start with 8 (example: 89191234567)';
                    } else {
                        // Format the number
                        $formatted_phone = sprintf(
                            '8(%s)%s-%s-%s',
                            substr($clean_phone, 1, 3),
                            substr($clean_phone, 4, 3),
                            substr($clean_phone, 7, 2),
                            substr($clean_phone, 9, 2)
                        );
                        $value = $formatted_phone;
                        $_POST[$field['name']] = $value;
                    }
                }
                
                // Login validation
                if ($is_login_field && !empty($value)) {
                    if (!preg_match('/^[a-zA-Z0-9]{6,}$/', $value)) {
                        $errors[] = 'Login must contain only Latin letters and numbers, at least 6 characters.';
                    }
                }
                
                // Email validation separately
                if ($field['type'] === 'email' && !empty($value)) {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = 'Please enter a valid email address.';
                    }
                }
                
                // Regex validation for other fields
                if (!empty($field['pattern']) && !empty($value) && !$is_fio_field && !$is_phone_field) {
                    $pattern = $field['pattern'];
                    $pattern = trim($pattern, '/');
                    $pattern = stripslashes($pattern);
                    
                    if (!preg_match('/^' . $pattern . '$/u', $value)) {
                        $errors[] = 'Field "' . $field['label'] . '" has invalid format.';
                    }
                }
                
                // Determine what is login/password/email
                if ($is_login_field) {
                    $user_data['user_login'] = $value;
                }
                elseif ($field['type'] === 'password') {
                    $user_data['user_pass'] = $value;
                }
                elseif ($field['type'] === 'email' && empty($user_data['user_email'])) {
                    $user_data['user_email'] = $value;
                }
                else {
                    $meta_data[$field['name']] = $value;
                }
            }
            
            // Check required WordPress fields
            if (empty($user_data['user_login'])) {
                $errors[] = 'You must add a "Login" field to the form.';
            }
            
            if (empty($user_data['user_pass'])) {
                $errors[] = 'You must add a "Password" field to the form.';
            }
            
            if (empty($user_data['user_email'])) {
                $errors[] = 'You must add an "Email" field to the form.';
            }
            
            // Check if user exists ONLY if no other errors
            if (empty($errors)) {
                if (username_exists($user_data['user_login'])) {
                    $errors[] = 'User with this login already exists.';
                }
                
                if (email_exists($user_data['user_email'])) {
                    $errors[] = 'User with this email already exists.';
                }
            }
            
            // Registration
            if (empty($errors)) {
                $user_id = wp_create_user($user_data['user_login'], $user_data['user_pass'], $user_data['user_email']);
                
                if (!is_wp_error($user_id)) {
                    // Save metadata
                    foreach ($meta_data as $key => $value) {
                        update_user_meta($user_id, $key, $value);
                    }
                    
                    // Automatic login
                    wp_set_current_user($user_id);
                    wp_set_auth_cookie($user_id);
                    
                    $success = true;
                } else {
                    $errors[] = 'Registration error: ' . $user_id->get_error_message();
                }
            }
        }
    }
    
    // If successfully registered
    if ($success) {
        return '<div class="sa-registration-success"><p>Registration successful! You have been automatically logged in.</p></div>';
    }
    
    // Display errors
    if (!empty($errors)) {
        echo '<div class="sa-registration-errors">';
        foreach ($errors as $error) {
            echo '<p class="error">' . esc_html($error) . '</p>';
        }
        echo '</div>';
    }
    
    // Display form
    ?>
    <form method="post" class="sa-registration-form" id="sa-form-<?php echo esc_attr($form_data['id']); ?>" novalidate>
        <input type="hidden" name="form_id" value="<?php echo esc_attr($form_data['id']); ?>">
        <?php wp_nonce_field('sa_register_action_' . $form_data['id'], 'sa_register_nonce'); ?>
        
        <h3><?php echo esc_html($form_data['name']); ?></h3>
        
        <?php foreach ($form_data['fields'] as $field): 
            $value = isset($_POST[$field['name']]) ? esc_attr($_POST[$field['name']]) : '';
            $required = $field['required'] ? 'required' : '';
            $pattern_attr = '';
            $minlength_attr = !empty($field['minlength']) ? 'minlength="' . esc_attr($field['minlength']) . '"' : '';
            $maxlength_attr = !empty($field['maxlength']) ? 'maxlength="' . esc_attr($field['maxlength']) . '"' : '';
            $title = '';
            
            // Determine field type for hints
            $field_lower_label = strtolower($field['label']);
            $field_lower_name = strtolower($field['name']);
            
            // Create hints
            if (strpos($field_lower_label, 'login') !== false || 
                strpos($field_lower_name, 'login') !== false ||
                strpos($field_lower_name, 'username') !== false) {
                $title = 'Latin letters and numbers, at least 6 characters';
            }
            elseif (strpos($field_lower_label, 'password') !== false || 
                    strpos($field_lower_name, 'password') !== false) {
                $title = 'Minimum 8 characters (any characters)';
            }
            elseif (strpos($field_lower_label, 'fio') !== false || 
                    strpos($field_lower_name, 'fio') !== false ||
                    strpos($field_lower_name, 'full_name') !== false) {
                $title = 'Cyrillic, spaces (Last Name First Name Middle Name)';
            }
            elseif (strpos($field_lower_label, 'phone') !== false || 
                    strpos($field_lower_name, 'phone') !== false ||
                    strpos($field_lower_name, 'tel') !== false) {
                $title = 'Starts with 8, 11 digits (example: 89191234567)';
            }
            elseif ($field['type'] === 'email') {
                $title = 'Format: example@mail.com';
            }
            
            // For phone don't use pattern, as auto-formatting will be used
            if (!empty($field['pattern']) && $field['type'] !== 'tel') {
                $html_pattern = $field['pattern'];
                $html_pattern = preg_replace('/^\/(.*)\/[a-z]*$/i', '$1', $html_pattern);
                $html_pattern = htmlspecialchars($html_pattern, ENT_QUOTES);
                $pattern_attr = 'pattern="' . $html_pattern . '"';
            }
        ?>
            <div class="form-field">
                <label>
                    <?php echo esc_html($field['label']); ?>
                    <?php if ($field['required']): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
                
                <?php if ($field['type'] === 'textarea'): ?>
                    <textarea name="<?php echo esc_attr($field['name']); ?>" 
                              placeholder="<?php echo esc_attr($field['placeholder']); ?>"
                              title="<?php echo esc_attr($title); ?>"
                              <?php echo $required; ?> <?php echo $minlength_attr; ?> <?php echo $maxlength_attr; ?>><?php echo $value; ?></textarea>
                
                <?php elseif ($field['type'] === 'select'): ?>
                    <select name="<?php echo esc_attr($field['name']); ?>" <?php echo $required; ?>>
                        <option value=""><?php echo esc_html($field['placeholder'] ?: 'Select...'); ?></option>
                        <?php if (!empty($field['options'])): 
                            $options = explode(',', $field['options']);
                            foreach ($options as $option):
                                $option = trim($option);
                        ?>
                            <option value="<?php echo esc_attr($option); ?>" <?php selected($value, $option); ?>>
                                <?php echo esc_html($option); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                
                <?php elseif ($field['type'] === 'checkbox'): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="<?php echo esc_attr($field['name']); ?>" value="1" 
                               <?php checked($value, '1'); ?> <?php echo $required; ?>>
                        <?php echo esc_html($field['placeholder'] ?: 'Yes'); ?>
                    </label>
                
                <?php elseif ($field['type'] === 'radio' && !empty($field['options'])): ?>
                    <div class="radio-group">
                        <?php $options = explode(',', $field['options']);
                        foreach ($options as $option):
                            $option = trim($option);
                        ?>
                            <label class="radio-label">
                                <input type="radio" name="<?php echo esc_attr($field['name']); ?>" 
                                       value="<?php echo esc_attr($option); ?>" 
                                       <?php checked($value, $option); ?> <?php echo $required; ?>>
                                <?php echo esc_html($option); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                
                <?php else: ?>
                    <input type="<?php echo esc_attr($field['type']); ?>" 
                           name="<?php echo esc_attr($field['name']); ?>" 
                           value="<?php echo $value; ?>"
                           placeholder="<?php echo esc_attr($field['placeholder']); ?>"
                           title="<?php echo esc_attr($title); ?>"
                           <?php echo $required; ?> 
                           <?php echo $pattern_attr; ?>
                           <?php echo $minlength_attr; ?>
                           <?php echo $maxlength_attr; ?>
                           <?php if ($field['type'] === 'tel'): ?>class="phone-input"<?php endif; ?>>
                <?php endif; ?>
                
                <?php if (!empty($title)): ?>
                    <div class="field-hint"><?php echo esc_html($title); ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <div class="form-submit">
            <button type="submit" class="sa-submit-button">Create User</button>
        </div>
    </form>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Phone auto-formatting
        const phoneInputs = document.querySelectorAll('.phone-input, input[type="tel"], input[name*="phone"], input[name*="tel"]');
        
        phoneInputs.forEach(function(input) {
            // Save original value
            let lastValue = input.value;
            
            // Input handler
            input.addEventListener('input', function(e) {
                let value = this.value.replace(/\D/g, '');
                
                // If empty value
                if (!value) {
                    lastValue = '';
                    return;
                }
                
                // If doesn't start with 8, add 8
                if (!value.startsWith('8')) {
                    if (value.length <= 10) {
                        value = '8' + value;
                    } else {
                        value = '8' + value.substring(0, 10);
                    }
                }
                
                // Limit to 11 digits (8 + 10 digits)
                value = value.substring(0, 11);
                
                if (value.startsWith('8')) {
                    let formatted = '8';
                    
                    if (value.length > 1) {
                        formatted += '(' + value.substring(1, 4);
                    }
                    if (value.length >= 4) {
                        formatted += ')' + value.substring(4, 7);
                    }
                    if (value.length >= 7) {
                        formatted += '-' + value.substring(7, 9);
                    }
                    if (value.length >= 9) {
                        formatted += '-' + value.substring(9, 11);
                    }
                    
                    // Update only if value changed
                    if (formatted !== lastValue) {
                        this.value = formatted;
                        lastValue = formatted;
                        
                        // Set cursor after last digit
                        const cursorPos = formatted.length;
                        this.setSelectionRange(cursorPos, cursorPos);
                    }
                }
            });
            
            // Paste handler
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                const numbers = pastedText.replace(/\D/g, '');
                
                if (numbers) {
                    let phone = numbers;
                    if (!phone.startsWith('8')) {
                        if (phone.startsWith('7')) {
                            phone = '8' + phone.substring(1);
                        } else {
                            phone = '8' + phone;
                        }
                    }
                    
                    // Limit to 11 digits
                    phone = phone.substring(0, 11);
                    
                    // Format
                    if (phone.startsWith('8')) {
                        let formatted = '8';
                        if (phone.length > 1) formatted += '(' + phone.substring(1, 4);
                        if (phone.length >= 4) formatted += ')' + phone.substring(4, 7);
                        if (phone.length >= 7) formatted += '-' + phone.substring(7, 9);
                        if (phone.length >= 9) formatted += '-' + phone.substring(9, 11);
                        
                        this.value = formatted;
                        lastValue = formatted;
                    }
                }
            });
            
            // Keydown handler for control
            input.addEventListener('keydown', function(e) {
                // Allow: digits, Backspace, Delete, Tab, arrows, Home, End
                if ((e.key >= '0' && e.key <= '9') || 
                    e.key === 'Backspace' || 
                    e.key === 'Delete' ||
                    e.key === 'Tab' ||
                    e.key === 'ArrowLeft' ||
                    e.key === 'ArrowRight' ||
                    e.key === 'Home' ||
                    e.key === 'End' ||
                    e.key === 'Enter') {
                    return true;
                }
                
                // Allow Ctrl+V, Ctrl+A
                if (e.ctrlKey && (e.key === 'v' || e.key === 'V' || e.key === 'a' || e.key === 'A')) {
                    return true;
                }
                
                e.preventDefault();
                return false;
            });
            
            // Focus handler - if empty, set 8(
            input.addEventListener('focus', function() {
                if (!this.value) {
                    this.value = '8(';
                    lastValue = '8(';
                    this.setSelectionRange(2, 2);
                } else {
                    // Set cursor to end
                    const cursorPos = this.value.length;
                    this.setSelectionRange(cursorPos, cursorPos);
                }
            });
            
            // Click handler for proper cursor placement
            input.addEventListener('click', function() {
                const value = this.value;
                if (value) {
                    // Set cursor to end
                    const cursorPos = value.length;
                    this.setSelectionRange(cursorPos, cursorPos);
                }
            });
        });
        
        // Full Name validation in real time
        const fioInputs = document.querySelectorAll('input[name*="fio"], input[name*="full_name"]');
        
        fioInputs.forEach(function(input) {
            input.addEventListener('blur', function() {
                const value = this.value.trim();
                if (value) {
                    // Check word count
                    const words = value.split(/\s+/).filter(word => word.length > 0);
                    if (words.length !== 3) {
                        this.setCustomValidity('Full Name must contain exactly 3 words (Last Name First Name Middle Name)');
                        this.reportValidity();
                    } else {
                        this.setCustomValidity('');
                    }
                }
            });
            
            input.addEventListener('input', function() {
                this.setCustomValidity('');
            });
        });
    });
    </script>
    
    <style>
        .sa-registration-form {
            max-width: 600px;
            margin: 20px auto;
            padding: 25px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .sa-registration-form h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }
        .form-field {
            margin-bottom: 20px;
        }
        .form-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        .form-field input[type="text"],
        .form-field input[type="password"],
        .form-field input[type="email"],
        .form-field input[type="tel"],
        .form-field input[type="number"],
        .form-field input[type="date"],
        .form-field select,
        .form-field textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .form-field textarea {
            min-height: 100px;
            resize: vertical;
        }
        .field-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
        .checkbox-label,
        .radio-label {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-weight: normal;
            cursor: pointer;
        }
        .checkbox-label input,
        .radio-label input {
            margin-right: 10px;
        }
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .required {
            color: #d63638;
        }
        .form-submit {
            margin-top: 25px;
            text-align: center;
        }
        .sa-submit-button {
            background: #0073aa;
            color: white;
            border: none;
            padding: 14px 35px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .sa-submit-button:hover {
            background: #005a87;
        }
        .sa-registration-errors {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin-bottom: 20px;
            max-width: 600px;
            margin: 20px auto;
        }
        .sa-registration-errors .error {
            margin: 5px 0;
        }
        .sa-registration-success {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            text-align: center;
            max-width: 600px;
            margin: 20px auto;
        }
    </style>
    <?php
    
    return ob_get_clean();
}

// Login form rendering function
function sa_render_login_form($form_data) {
    if (is_user_logged_in()) {
        return '<p>You are already logged in.</p>';
    }
    
    ob_start();
    
    // Login handling
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sa_login_nonce'])) {
        if (wp_verify_nonce($_POST['sa_login_nonce'], 'sa_login_action_' . $form_data['id'])) {
            $username = sanitize_user($_POST['username']);
            $password = $_POST['password'];
            
            $creds = array(
                'user_login'    => $username,
                'user_password' => $password,
                'remember'      => false
            );
            
            $user = wp_signon($creds, false);
            
            if (is_wp_error($user)) {
                $error = 'Invalid login or password.';
            } else {
                wp_redirect(home_url());
                exit;
            }
        }
    }
    
    ?>
    <form method="post" class="sa-login-form" id="sa-login-<?php echo esc_attr($form_data['id']); ?>" novalidate>
        <input type="hidden" name="form_id" value="<?php echo esc_attr($form_data['id']); ?>">
        <?php wp_nonce_field('sa_login_action_' . $form_data['id'], 'sa_login_nonce'); ?>
        
        <h3><?php echo esc_html($form_data['name']); ?></h3>
        
        <?php if ($error): ?>
            <div class="sa-login-error">
                <p><?php echo esc_html($error); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="form-field">
            <label>Login:</label>
            <input type="text" name="username" required>
        </div>
        
        <div class="form-field">
            <label>Password:</label>
            <input type="password" name="password" required>
        </div>
        
        <div class="form-submit">
            <button type="submit" class="sa-submit-button">Login</button>
        </div>
        
        <?php if (!empty($form_data['registration_form'])): ?>
            <div class="registration-link">
                <a href="javascript:void(0)" onclick="document.getElementById('sa-form-<?php echo esc_attr($form_data['registration_form']); ?>').scrollIntoView({behavior: 'smooth'})">
                    <?php echo esc_html($form_data['registration_text']); ?>
                </a>
            </div>
        <?php endif; ?>
    </form>
    
    <style>
        .sa-login-form {
            max-width: 400px;
            margin: 20px auto;
            padding: 25px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .sa-login-form h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }
        .sa-login-error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-field {
            margin-bottom: 20px;
        }
        .form-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        .form-field input[type="text"],
        .form-field input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .form-submit {
            margin-top: 25px;
            text-align: center;
        }
        .registration-link {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
        }
        .registration-link a {
            color: #0073aa;
            text-decoration: none;
        }
        .registration-link a:hover {
            text-decoration: underline;
        }
    </style>
    <?php
    
    return ob_get_clean();
}

// ------------------- FORM BUILDER MAIN PAGE --------------------

function sa_render_form_builder_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }
    
    ?>
    <div class="wrap">
        <h1>Form Builder</h1>
        
        <div class="sa-dashboard">
            <div class="sa-dashboard-cards">
                <div class="sa-card">
                    <h2><span class="dashicons dashicons-forms"></span> Registration Forms</h2>
                    <p>Create custom user registration forms with automatic restrictions for standard fields.</p>
                    <a href="?page=sa_registration_forms" class="button button-primary">Manage Forms</a>
                </div>
                
                <div class="sa-card">
                    <h2><span class="dashicons dashicons-lock"></span> Login Forms</h2>
                    <p>Create forms for user authentication with registration links.</p>
                    <a href="?page=sa_login_forms" class="button button-primary">Manage Forms</a>
                </div>
                
                <div class="sa-card">
                    <h2><span class="dashicons dashicons-clipboard"></span> Course Applications</h2>
                    <p>Manage user course applications.</p>
                    <a href="?page=sa_applications" class="button button-primary">Go to Applications</a>
                </div>
            </div>
            
            <div class="sa-dashboard-info">
                <h2>Automatic restrictions for standard fields:</h2>
                <ul>
                    <li><strong>Login:</strong> Latin letters and numbers, at least 6 characters</li>
                    <li><strong>Password:</strong> minimum 8 characters (any characters)</li>
                    <li><strong>Full Name:</strong> Cyrillic, spaces (Last Name First Name Middle Name)</li>
                    <li><strong>Phone:</strong> format 8(XXX)XXX-XX-XX</li>
                    <li><strong>Email:</strong> standard email format</li>
                </ul>
                <p><em>Restrictions are applied automatically when field name or label matches (example: login, username, Login).</em></p>
            </div>
        </div>
    </div>
    
    <style>
        .sa-dashboard {
            margin-top: 20px;
        }
        .sa-dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .sa-card {
            background: #fff;
            padding: 25px;
            border: 1px solid #ccd0d4;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .sa-card h2 {
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sa-card h2 .dashicons {
            color: #0073aa;
        }
        .sa-card p {
            margin-bottom: 20px;
            color: #666;
        }
        .sa-dashboard-info {
            background: #f0f8ff;
            padding: 25px;
            border-left: 4px solid #0073aa;
        }
        .sa-dashboard-info h2 {
            margin-top: 0;
        }
        .sa-dashboard-info ul {
            margin-left: 20px;
        }
        .sa-dashboard-info li {
            margin-bottom: 8px;
        }
    </style>
    <?php
}

// ------------------- OLD FUNCTIONALITY (course applications) --------------------

// Course application form (old shortcode)
add_shortcode('sa_application_form', 'sa_application_form_shortcode');
function sa_application_form_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to submit an application.</p>';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'survival_applications';
    $msg = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sa_application_nonce']) && wp_verify_nonce($_POST['sa_application_nonce'], 'sa_application_action')) {
        $course_options = [
            'Basics of Algorithmization and Programming',
            'Basics of Web Design',
            'Basics of Database Design',
        ];
        $course_name = sanitize_text_field($_POST['course_name']);
        $start_date_raw = sanitize_text_field($_POST['start_date']);
        $payment_method = sanitize_text_field($_POST['payment_method']);

        $date_parts = explode('.', $start_date_raw);
        $start_date = null;
        if (count($date_parts) === 3) {
            $start_date = sprintf('%04d-%02d-%02d', $date_parts[2], $date_parts[1], $date_parts[0]);
        }

        if (!in_array($course_name, $course_options)) {
            $msg = '<p style="color:red;">Please select a valid course.</p>';
        } elseif (!$start_date) {
            $msg = '<p style="color:red;">Invalid training start date.</p>';
        } elseif (!in_array($payment_method, ['Cash', 'Transfer by phone number'])) {
            $msg = '<p style="color:red;">Please select payment method.</p>';
        } else {
            $wpdb->insert($table_name, [
                'user_id' => get_current_user_id(),
                'course_name' => $course_name,
                'start_date' => $start_date,
                'payment_method' => $payment_method,
                'status' => 'New',
                'created_at' => current_time('mysql'),
            ]);
            $msg = '<p style="color:green;">Application submitted for administrator review.</p>';
        }
    }

    ob_start();
    echo $msg;
    ?>
    <form method="post" novalidate style="max-width: 500px; margin: 20px auto; padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">
        <h3>Course Application</h3>
        <p>
            <select name="course_name" required style="width: 100%; padding: 10px;">
                <option value="">Select course</option>
                <option value="Basics of Algorithmization and Programming">Basics of Algorithmization and Programming</option>
                <option value="Basics of Web Design">Basics of Web Design</option>
                <option value="Basics of Database Design">Basics of Database Design</option>
            </select>
        </p>
        <p><input type="text" name="start_date" placeholder="Start date (DD.MM.YYYY)" required pattern="\d{2}\.\d{2}\.\d{4}" style="width: 100%; padding: 10px;"></p>
        <p>
            <select name="payment_method" required style="width: 100%; padding: 10px;">
                <option value="">Select payment method</option>
                <option value="Cash">Cash</option>
                <option value="Transfer by phone number">Transfer by phone number</option>
            </select>
        </p>
        <?php wp_nonce_field('sa_application_action', 'sa_application_nonce'); ?>
        <p><input type="submit" value="Submit" style="background: #0073aa; color: white; border: none; padding: 12px 25px; cursor: pointer;"></p>
    </form>
    <?php
    return ob_get_clean();
}

// Admin panel for course applications
function sa_applications_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Administrator rights required!');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'survival_applications';

    if (isset($_POST['sa_update_status_nonce']) && wp_verify_nonce($_POST['sa_update_status_nonce'], 'sa_update_status_action')) {
        $app_id = intval($_POST['application_id']);
        $new_status = sanitize_text_field($_POST['new_status']);
        $valid_statuses = ['New', 'Training in progress', 'Training completed'];
        if (in_array($new_status, $valid_statuses)) {
            $wpdb->update($table_name, ['status' => $new_status], ['id' => $app_id]);
        }
    }

    $applications = $wpdb->get_results("SELECT a.*, u.user_login FROM $table_name a LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID ORDER BY a.created_at DESC");

    echo '<div class="wrap"><h1>Course Applications</h1>';
    echo '<table class="wp-list-table widefat fixed striped"><thead><tr>
           <th>ID</th><th>User</th><th>Course</th><th>Start Date</th><th>Payment Method</th><th>Status</th><th>Submission Date</th><th>Actions</th>
          </tr></thead><tbody>';

    foreach ($applications as $app) {
        $visible_status = $app->status === 'New' ? 'Hidden (New)' : $app->status;
        echo '<tr>';
        echo '<td>' . esc_html($app->id) . '</td>';
        echo '<td>' . esc_html($app->user_login) . '</td>';
        echo '<td>' . esc_html($app->course_name) . '</td>';
        echo '<td>' . esc_html(date('m.d.Y', strtotime($app->start_date))) . '</td>';
        echo '<td>' . esc_html($app->payment_method) . '</td>';
        echo '<td>' . esc_html($visible_status) . '</td>';
        echo '<td>' . esc_html(date('m.d.Y H:i', strtotime($app->created_at))) . '</td>';
        echo '<td><form method="post" style="display: inline;">';
        echo '<input type="hidden" name="application_id" value="' . esc_attr($app->id) . '">';
        wp_nonce_field('sa_update_status_action', 'sa_update_status_nonce');
        echo '<select name="new_status">';
        foreach (['New', 'Training in progress', 'Training completed'] as $status) {
            $selected = $app->status === $status ? 'selected' : '';
            echo '<option value="' . esc_attr($status) . '" ' . $selected . '>' . esc_html($status) . '</option>';
        }
        echo '</select> <input type="submit" value="Update" class="button button-small">';
        echo '</form></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

// "My Applications" shortcode + feedback (old)
add_shortcode('sa_my_applications', 'sa_my_applications_shortcode');
function sa_my_applications_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to view your applications.</p>';
    }

    global $wpdb;
    $table_apps = $wpdb->prefix . 'survival_applications';
    $table_feedback = $wpdb->prefix . 'survival_app_feedback';
    $user_id = get_current_user_id();

    $msg = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sa_feedback_nonce']) && wp_verify_nonce($_POST['sa_feedback_nonce'], 'sa_feedback_action')) {
        $app_id = intval($_POST['application_id']);
        $feedback_text = sanitize_textarea_field($_POST['feedback_text']);
        if ($feedback_text) {
            $wpdb->insert($table_feedback, [
                'application_id' => $app_id,
                'feedback_text' => $feedback_text,
                'created_at' => current_time('mysql'),
            ]);
            $msg = '<p style="color:green;">Feedback added.</p>';
        } else {
            $msg = '<p style="color:red;">Feedback cannot be empty.</p>';
        }
    }

    $applications = $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, f.feedback_text FROM $table_apps a LEFT JOIN $table_feedback f ON a.id = f.application_id WHERE a.user_id = %d ORDER BY a.created_at DESC",
        $user_id
    ));

    ob_start();
    echo $msg;
    if (empty($applications)) {
        echo '<p>You have no course applications.</p>';
    } else {
        foreach ($applications as $app) {
            echo '<div style="border:1px solid #ccc;padding:15px;margin-bottom:20px;background:#fff;">';
            echo '<h4 style="margin-top:0;">' . esc_html($app->course_name) . '</h4>';
            echo '<p><strong>Start date:</strong> ' . esc_html(date('m.d.Y', strtotime($app->start_date))) . '</p>';
            echo '<p><strong>Payment method:</strong> ' . esc_html($app->payment_method) . '</p>';
            echo '<p><strong>Status:</strong> ' . esc_html($app->status) . '</p>';
            echo '<p><strong>Feedback:</strong> ' . ($app->feedback_text ? esc_html($app->feedback_text) : 'No feedback provided') . '</p>';

            if (!$app->feedback_text) {
                echo '<form method="post" style="margin-top:15px;">';
                wp_nonce_field('sa_feedback_action', 'sa_feedback_nonce');
                echo '<input type="hidden" name="application_id" value="' . esc_attr($app->id) . '">';
                echo '<textarea name="feedback_text" rows="3" style="width:100%;" placeholder="Leave feedback"></textarea><br>';
                echo '<input type="submit" value="Submit feedback" style="margin-top:10px;">';
                echo '</form>';
            }
            echo '</div>';
        }
    }
    return ob_get_clean();
}

?>
