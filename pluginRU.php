<?php
/**
 * Plugin Name: LearnFlow Admin + Form Builder
 * Description: Плагин для управления заявками на курсы и конструктором форм с кастомными шорткодами.
 * Version: 2.0
 * Author: YeakYeakYeak
 */

// ------------------- Активация плагина (создание таблиц) --------------------

register_activation_hook(__FILE__, 'sa_create_tables');

function sa_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Таблица заявок на курсы
    $table_apps = $wpdb->prefix . 'survival_applications';
    $sql_apps = "CREATE TABLE $table_apps (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        course_name VARCHAR(255) NOT NULL,
        start_date DATE NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Новая',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";

    // Таблица отзывов к заявкам
    $table_feedback = $wpdb->prefix . 'survival_app_feedback';
    $sql_feedback = "CREATE TABLE $table_feedback (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        application_id MEDIUMINT(9) NOT NULL,
        feedback_text TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        KEY application_id (application_id)
    ) $charset_collate;";

    // Таблица для сохранения данных кастомных форм
    $table_forms = $wpdb->prefix . 'sa_custom_forms_data';
    $sql_forms = "CREATE TABLE $table_forms (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        form_slug VARCHAR(100) NOT NULL,
        user_id BIGINT(20) UNSIGNED DEFAULT NULL,
        form_data LONGTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        KEY form_slug (form_slug),
        KEY user_id (user_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_apps);
    dbDelta($sql_feedback);
    dbDelta($sql_forms);
}

// ------------------- Конструктор форм (админка) --------------------

add_action('admin_menu', 'sa_add_admin_menu');

function sa_add_admin_menu() {
    add_menu_page(
        'Конструктор форм', 
        'Конструктор форм', 
        'manage_options', 
        'sa_form_builder', 
        'sa_render_form_builder_page',
        'dashicons-feedback'
    );
    
    // Подменю для заявок на курсы (старая функциональность)
    add_submenu_page(
        'sa_form_builder',
        'Заявки на курсы',
        'Заявки на курсы',
        'manage_options',
        'sa_applications',
        'sa_applications_page'
    );
}

// Главная страница конструктора форм
function sa_render_form_builder_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Нет прав для доступа.');
    }

    global $wpdb;
    $forms_table = $wpdb->prefix . 'sa_custom_forms_data';

    // Обработка удаления формы
    if (isset($_GET['delete']) && !empty($_GET['delete'])) {
        $form_slug = sanitize_key($_GET['delete']);
        $forms_option = get_option('sa_custom_forms', []);
        if (isset($forms_option[$form_slug])) {
            unset($forms_option[$form_slug]);
            update_option('sa_custom_forms', $forms_option);
            
            // Удаляем сохранённые данные этой формы
            $wpdb->delete($forms_table, ['form_slug' => $form_slug]);
            
            echo '<div class="updated"><p>Форма удалена.</p></div>';
        }
    }

    // Обработка сохранения/редактирования формы
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sa_save_form'])) {
        check_admin_referer('sa_form_builder_save');
        
        $form_slug = sanitize_key($_POST['form_slug']);
        $form_title = sanitize_text_field($_POST['form_title']);
        $fields = isset($_POST['fields']) ? $_POST['fields'] : [];
        
        // Валидация полей
        $validated_fields = [];
        foreach ($fields as $field) {
            if (!empty($field['label']) && !empty($field['name'])) {
                $validated_fields[] = [
                    'label' => sanitize_text_field($field['label']),
                    'name' => sanitize_key($field['name']),
                    'type' => in_array($field['type'], ['text', 'email', 'password', 'textarea', 'tel', 'number', 'date', 'select', 'checkbox', 'radio']) ? $field['type'] : 'text',
                    'placeholder' => sanitize_text_field($field['placeholder']),
                    'required' => isset($field['required']) ? 1 : 0,
                    'options' => !empty($field['options']) ? sanitize_text_field($field['options']) : '' // Для select
                ];
            }
        }
        
        // Сохраняем в опциях
        $forms_option = get_option('sa_custom_forms', []);
        $forms_option[$form_slug] = [
            'title' => $form_title,
            'slug' => $form_slug,
            'fields' => $validated_fields,
            'created' => current_time('mysql')
        ];
        update_option('sa_custom_forms', $forms_option);
        
        echo '<div class="updated"><p>Форма "' . esc_html($form_title) . '" сохранена. Используйте шорткод: <code>[sa_form_' . esc_html($form_slug) . ']</code></p></div>';
    }

    // Получаем все сохранённые формы
    $all_forms = get_option('sa_custom_forms', []);
    
    // Если редактируем существующую форму
    $editing_form = null;
    if (isset($_GET['edit']) && !empty($_GET['edit']) && isset($all_forms[$_GET['edit']])) {
        $editing_form = $all_forms[$_GET['edit']];
    }

    ?>
    <div class="wrap">
        <h1>Конструктор форм</h1>
        
        <div class="sa-form-builder-container">
            <!-- Форма создания/редактирования -->
            <div class="sa-form-editor">
                <h2><?php echo $editing_form ? 'Редактировать форму' : 'Создать новую форму'; ?></h2>
                
                <form method="post">
                    <?php wp_nonce_field('sa_form_builder_save'); ?>
                    
                    <div class="form-meta-fields">
                        <p>
                            <label><strong>Название формы:</strong><br>
                                <input type="text" name="form_title" 
                                       value="<?php echo $editing_form ? esc_attr($editing_form['title']) : ''; ?>" 
                                       placeholder="Например: Форма обратной связи" required>
                            </label>
                        </p>
                        
                        <p>
                            <label><strong>Название шорткода (только латинские буквы, цифры и нижнее подчёркивание):</strong><br>
                                <input type="text" name="form_slug" 
                                       value="<?php echo $editing_form ? esc_attr($editing_form['slug']) : ''; ?>" 
                                       pattern="[a-z0-9_]+" 
                                       placeholder="feedback_form" required>
                                <br><small>Шорткод будет: <code>[sa_form_<span id="slug-preview"><?php echo $editing_form ? esc_html($editing_form['slug']) : 'ваш_шорткод'; ?></span>]</code></small>
                            </label>
                        </p>
                    </div>
                    
                    <h3>Поля формы</h3>
                    <div id="form-fields-container">
                        <?php if ($editing_form && !empty($editing_form['fields'])): ?>
                            <?php foreach ($editing_form['fields'] as $index => $field): ?>
                                <div class="field-editor" data-index="<?php echo $index; ?>">
                                    <h4>Поле <?php echo $index + 1; ?></h4>
                                    <div class="field-inputs">
                                        <input type="text" name="fields[<?php echo $index; ?>][label]" 
                                               placeholder="Метка поля (например: Ваше имя)" 
                                               value="<?php echo esc_attr($field['label']); ?>" required>
                                        <input type="text" name="fields[<?php echo $index; ?>][name]" 
                                               placeholder="Название поля (англ., например: full_name)" 
                                               value="<?php echo esc_attr($field['name']); ?>" required>
                                        <select name="fields[<?php echo $index; ?>][type]" class="field-type-select">
                                            <option value="text" <?php selected($field['type'], 'text'); ?>>Текст</option>
                                            <option value="email" <?php selected($field['type'], 'email'); ?>>Email</option>
                                            <option value="tel" <?php selected($field['type'], 'tel'); ?>>Телефон</option>
                                            <option value="textarea" <?php selected($field['type'], 'textarea'); ?>>Текстовая область</option>
                                            <option value="select" <?php selected($field['type'], 'select'); ?>>Выпадающий список</option>
                                            <option value="checkbox" <?php selected($field['type'], 'checkbox'); ?>>Галочка</option>
                                            <option value="radio" <?php selected($field['type'], 'radio'); ?>>Радио-кнопки</option>
                                            <option value="date" <?php selected($field['type'], 'date'); ?>>Дата</option>
                                            <option value="number" <?php selected($field['type'], 'number'); ?>>Число</option>
                                        </select>
                                        <input type="text" name="fields[<?php echo $index; ?>][placeholder]" 
                                               placeholder="Подсказка в поле" 
                                               value="<?php echo esc_attr($field['placeholder']); ?>">
                                        <div class="field-options-container" style="display:<?php echo ($field['type'] == 'select' || $field['type'] == 'radio') ? 'block' : 'none'; ?>">
                                            <input type="text" name="fields[<?php echo $index; ?>][options]" 
                                                   placeholder="Варианты через запятую (только для select/radio)" 
                                                   value="<?php echo esc_attr($field['options']); ?>">
                                        </div>
                                        <label class="field-required">
                                            <input type="checkbox" name="fields[<?php echo $index; ?>][required]" value="1" <?php checked($field['required'], 1); ?>> Обязательное поле
                                        </label>
                                        <button type="button" class="remove-field button">Удалить поле</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" id="add-field-btn" class="button button-primary">+ Добавить поле</button>
                    
                    <div class="form-submit">
                        <input type="submit" name="sa_save_form" class="button button-large button-primary" value="<?php echo $editing_form ? 'Обновить форму' : 'Создать форму'; ?>">
                        <?php if ($editing_form): ?>
                            <a href="?page=sa_form_builder" class="button button-large">Отмена</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Список созданных форм -->
            <div class="sa-forms-list">
                <h2>Мои формы</h2>
                
                <?php if (empty($all_forms)): ?>
                    <p>Вы ещё не создали ни одной формы.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Шорткод</th>
                                <th>Кол-во полей</th>
                                <th>Дата создания</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_forms as $slug => $form): ?>
                            <tr>
                                <td><strong><?php echo esc_html($form['title']); ?></strong></td>
                                <td><code>[sa_form_<?php echo esc_html($slug); ?>]</code></td>
                                <td><?php echo count($form['fields']); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($form['created'])); ?></td>
                                <td>
                                    <a href="?page=sa_form_builder&edit=<?php echo esc_attr($slug); ?>" class="button button-small">Редактировать</a>
                                    <a href="?page=sa_form_builder&delete=<?php echo esc_attr($slug); ?>" 
                                       class="button button-small button-link-delete" 
                                       onclick="return confirm('Удалить форму «<?php echo esc_js($form['title']); ?>»?')">Удалить</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <div class="sa-info-box">
                    <h3>Как использовать:</h3>
                    <ol>
                        <li>Создайте форму, задайте ей название и уникальный шорткод</li>
                        <li>Добавьте нужные поля</li>
                        <li>На сайте вставьте шорткод формы: <code>[sa_form_ваш_шорткод]</code></li>
                        <li>Данные формы сохраняются в базе данных</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('form-fields-container');
            const addBtn = document.getElementById('add-field-btn');
            const slugInput = document.querySelector('input[name="form_slug"]');
            const slugPreview = document.getElementById('slug-preview');
            
            // Обновление превью шорткода
            if (slugInput && slugPreview) {
                slugInput.addEventListener('input', function() {
                    slugPreview.textContent = this.value || 'ваш_шорткод';
                });
            }
            
            // Добавление нового поля
            addBtn.addEventListener('click', function() {
                const index = container.children.length;
                const fieldHtml = `
                    <div class="field-editor" data-index="${index}">
                        <h4>Поле ${index + 1}</h4>
                        <div class="field-inputs">
                            <input type="text" name="fields[${index}][label]" placeholder="Метка поля (например: Ваше имя)" required>
                            <input type="text" name="fields[${index}][name]" placeholder="Название поля (англ., например: full_name)" required>
                            <select name="fields[${index}][type]" class="field-type-select">
                                <option value="text">Текст</option>
                                <option value="email">Email</option>
                                <option value="tel">Телефон</option>
                                <option value="textarea">Текстовая область</option>
                                <option value="select">Выпадающий список</option>
                                <option value="checkbox">Галочка</option>
                                <option value="radio">Радио-кнопки</option>
                                <option value="date">Дата</option>
                                <option value="number">Число</option>
                            </select>
                            <input type="text" name="fields[${index}][placeholder]" placeholder="Подсказка в поле">
                            <div class="field-options-container" style="display:none;">
                                <input type="text" name="fields[${index}][options]" placeholder="Варианты через запятую (только для select/radio)">
                            </div>
                            <label class="field-required">
                                <input type="checkbox" name="fields[${index}][required]" value="1"> Обязательное поле
                            </label>
                            <button type="button" class="remove-field button">Удалить поле</button>
                        </div>
                    </div>`;
                container.insertAdjacentHTML('beforeend', fieldHtml);
                
                // Добавляем обработчик для нового поля
                const newField = container.lastElementChild;
                const typeSelect = newField.querySelector('.field-type-select');
                const optionsContainer = newField.querySelector('.field-options-container');
                
                typeSelect.addEventListener('change', function() {
                    optionsContainer.style.display = (this.value === 'select' || this.value === 'radio') ? 'block' : 'none';
                });
            });
            
            // Обработка изменения типа поля
            document.querySelectorAll('.field-type-select').forEach(select => {
                select.addEventListener('change', function() {
                    const optionsContainer = this.closest('.field-inputs').querySelector('.field-options-container');
                    optionsContainer.style.display = (this.value === 'select' || this.value === 'radio') ? 'block' : 'none';
                });
            });
            
            // Удаление поля (делегирование)
            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-field')) {
                    e.target.closest('.field-editor').remove();
                    // Обновляем индексы
                    document.querySelectorAll('.field-editor').forEach((field, idx) => {
                        field.querySelector('h4').textContent = `Поле ${idx + 1}`;
                        field.dataset.index = idx;
                        // Обновляем имена полей ввода
                        field.querySelectorAll('[name^="fields["]').forEach(input => {
                            const name = input.name;
                            const newName = name.replace(/fields\[\d+\]/, `fields[${idx}]`);
                            input.name = newName;
                        });
                    });
                }
            });
        });
    </script>
    
    <style>
        .sa-form-builder-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        .sa-form-editor, .sa-forms-list {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .field-editor {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            background: #f9f9f9;
        }
        .field-editor h4 {
            margin-top: 0;
            color: #23282d;
        }
        .field-inputs input[type="text"],
        .field-inputs select {
            width: 100%;
            margin-bottom: 8px;
            padding: 8px;
        }
        .field-required {
            display: block;
            margin: 10px 0;
        }
        .form-meta-fields input[type="text"] {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            margin-bottom: 15px;
        }
        .form-submit {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .sa-info-box {
            background: #f0f8ff;
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid #0073aa;
        }
        .button-link-delete {
            color: #d63638;
        }
    </style>
    <?php
}

// ------------------- Динамическая регистрация шорткодов --------------------

add_action('init', 'sa_register_dynamic_shortcodes');

function sa_register_dynamic_shortcodes() {
    $forms = get_option('sa_custom_forms', []);
    
    foreach ($forms as $slug => $form_data) {
        add_shortcode('sa_form_' . $slug, function($atts) use ($slug, $form_data) {
            return sa_render_custom_form($slug, $form_data, $atts);
        });
    }
}

// Функция рендеринга кастомной формы
function sa_render_custom_form($slug, $form_data, $atts = []) {
    // Обработка отправки формы
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
        isset($_POST['sa_form_slug']) && 
        $_POST['sa_form_slug'] === $slug) {
        
        if (!isset($_POST['sa_form_nonce']) || !wp_verify_nonce($_POST['sa_form_nonce'], 'sa_form_' . $slug)) {
            $message = '<div class="sa-form-error">Ошибка безопасности. Попробуйте ещё раз.</div>';
        } else {
            // Собираем данные формы
            $submission_data = [];
            foreach ($form_data['fields'] as $field) {
                $value = isset($_POST[$field['name']]) ? sanitize_text_field($_POST[$field['name']]) : '';
                $submission_data[$field['label']] = $value;
            }
            
            // Сохраняем в базу данных
            global $wpdb;
            $table = $wpdb->prefix . 'sa_custom_forms_data';
            
            $wpdb->insert($table, [
                'form_slug' => $slug,
                'user_id' => is_user_logged_in() ? get_current_user_id() : NULL,
                'form_data' => json_encode($submission_data, JSON_UNESCAPED_UNICODE),
                'created_at' => current_time('mysql')
            ]);
            
            if ($wpdb->insert_id) {
                $message = '<div class="sa-form-success">Спасибо! Ваша форма отправлена.</div>';
                
                // Можно также отправить email администратору
                $admin_email = get_option('admin_email');
                $subject = 'Новая заявка с формы: ' . $form_data['title'];
                $body = "Новая заявка с сайта " . get_bloginfo('name') . "\n\n";
                $body .= "Форма: " . $form_data['title'] . "\n";
                $body .= "Дата: " . current_time('d.m.Y H:i') . "\n\n";
                foreach ($submission_data as $label => $value) {
                    $body .= $label . ": " . $value . "\n";
                }
                wp_mail($admin_email, $subject, $body);
                
                // Очищаем POST, чтобы форма не отображала старые значения
                $_POST = [];
            } else {
                $message = '<div class="sa-form-error">Произошла ошибка при сохранении. Попробуйте позже.</div>';
            }
        }
    }
    
    ob_start();
    
    echo $message;
    
    if (!isset($_POST['sa_form_slug']) || $_POST['sa_form_slug'] !== $slug || $message) {
        ?>
        <form method="post" class="sa-custom-form" id="sa-form-<?php echo esc_attr($slug); ?>">
            <input type="hidden" name="sa_form_slug" value="<?php echo esc_attr($slug); ?>">
            <?php wp_nonce_field('sa_form_' . $slug, 'sa_form_nonce'); ?>
            
            <h3 class="sa-form-title"><?php echo esc_html($form_data['title']); ?></h3>
            
            <?php foreach ($form_data['fields'] as $field): ?>
                <div class="sa-form-field">
                    <label>
                        <?php echo esc_html($field['label']); ?>
                        <?php if ($field['required']): ?>
                            <span class="required">*</span>
                        <?php endif; ?>
                    </label>
                    
                    <?php
                    $value = isset($_POST[$field['name']]) ? esc_attr($_POST[$field['name']]) : '';
                    
                    switch ($field['type']) {
                        case 'textarea':
                            echo '<textarea name="' . esc_attr($field['name']) . '" 
                                  placeholder="' . esc_attr($field['placeholder']) . '"
                                  ' . ($field['required'] ? 'required' : '') . '>' . $value . '</textarea>';
                            break;
                            
                        case 'select':
                            echo '<select name="' . esc_attr($field['name']) . '" ' . ($field['required'] ? 'required' : '') . '>';
                            echo '<option value="">' . esc_html($field['placeholder'] ?: 'Выберите...') . '</option>';
                            if (!empty($field['options'])) {
                                $options = explode(',', $field['options']);
                                foreach ($options as $option) {
                                    $option = trim($option);
                                    $selected = ($value === $option) ? 'selected' : '';
                                    echo '<option value="' . esc_attr($option) . '" ' . $selected . '>' . esc_html($option) . '</option>';
                                }
                            }
                            echo '</select>';
                            break;
                            
                        case 'checkbox':
                            echo '<label class="sa-checkbox-label">';
                            echo '<input type="checkbox" name="' . esc_attr($field['name']) . '" value="1" ' . ($value ? 'checked' : '') . ' ' . ($field['required'] ? 'required' : '') . '>';
                            echo '<span>' . esc_html($field['placeholder'] ?: 'Да') . '</span>';
                            echo '</label>';
                            break;
                            
                        case 'radio':
                            if (!empty($field['options'])) {
                                $options = explode(',', $field['options']);
                                echo '<div class="sa-radio-group">';
                                foreach ($options as $option) {
                                    $option = trim($option);
                                    $checked = ($value === $option) ? 'checked' : '';
                                    echo '<label class="sa-radio-label">';
                                    echo '<input type="radio" name="' . esc_attr($field['name']) . '" value="' . esc_attr($option) . '" ' . $checked . ' ' . ($field['required'] ? 'required' : '') . '>';
                                    echo '<span>' . esc_html($option) . '</span>';
                                    echo '</label>';
                                }
                                echo '</div>';
                            }
                            break;
                            
                        default:
                            echo '<input type="' . esc_attr($field['type']) . '" 
                                  name="' . esc_attr($field['name']) . '" 
                                  value="' . $value . '"
                                  placeholder="' . esc_attr($field['placeholder']) . '"
                                  ' . ($field['required'] ? 'required' : '') . '>';
                    }
                    ?>
                </div>
            <?php endforeach; ?>
            
            <div class="sa-form-submit">
                <button type="submit" class="sa-submit-button">Отправить</button>
            </div>
        </form>
        
        <style>
            .sa-custom-form {
                max-width: 600px;
                margin: 20px auto;
                padding: 20px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
            .sa-form-title {
                margin-top: 0;
                color: #333;
                border-bottom: 2px solid #0073aa;
                padding-bottom: 10px;
            }
            .sa-form-field {
                margin-bottom: 20px;
            }
            .sa-form-field label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
                color: #555;
            }
            .sa-form-field input[type="text"],
            .sa-form-field input[type="email"],
            .sa-form-field input[type="tel"],
            .sa-form-field input[type="number"],
            .sa-form-field input[type="date"],
            .sa-form-field select,
            .sa-form-field textarea {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-sizing: border-box;
            }
            .sa-form-field textarea {
                min-height: 100px;
                resize: vertical;
            }
            .sa-checkbox-label,
            .sa-radio-label {
                display: flex;
                align-items: center;
                margin-bottom: 5px;
                font-weight: normal;
                cursor: pointer;
            }
            .sa-checkbox-label input,
            .sa-radio-label input {
                margin-right: 8px;
            }
            .sa-radio-group {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .required {
                color: #d63638;
            }
            .sa-form-submit {
                margin-top: 25px;
                text-align: center;
            }
            .sa-submit-button {
                background: #0073aa;
                color: white;
                border: none;
                padding: 12px 30px;
                border-radius: 4px;
                font-size: 16px;
                cursor: pointer;
                transition: background 0.3s;
            }
            .sa-submit-button:hover {
                background: #005a87;
            }
            .sa-form-success {
                background: #d4edda;
                color: #155724;
                padding: 15px;
                border: 1px solid #c3e6cb;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            .sa-form-error {
                background: #f8d7da;
                color: #721c24;
                padding: 15px;
                border: 1px solid #f5c6cb;
                border-radius: 4px;
                margin-bottom: 20px;
            }
        </style>
        <?php
    }
    
    return ob_get_clean();
}

// ------------------- Просмотр отправленных данных форм (админка) --------------------

add_action('admin_menu', 'sa_add_form_submissions_menu');

function sa_add_form_submissions_menu() {
    add_submenu_page(
        'sa_form_builder',
        'Отправленные формы',
        'Отправленные формы',
        'manage_options',
        'sa_form_submissions',
        'sa_render_form_submissions_page'
    );
}

function sa_render_form_submissions_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Нет прав для доступа.');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'sa_custom_forms_data';
    $forms = get_option('sa_custom_forms', []);
    
    // Определяем, какую форму просматриваем
    $current_form = isset($_GET['form']) ? sanitize_key($_GET['form']) : '';
    $where_clause = $current_form ? "WHERE form_slug = '$current_form'" : '';
    
    // Пагинация
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Получаем общее количество записей
    $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table $where_clause");
    $total_pages = ceil($total_items / $per_page);
    
    // Получаем данные
    $submissions = $wpdb->get_results("
        SELECT * FROM $table 
        $where_clause 
        ORDER BY created_at DESC 
        LIMIT $per_page OFFSET $offset
    ");
    
    ?>
    <div class="wrap">
        <h1>Отправленные формы</h1>
        
        <div class="sa-submissions-container">
            <!-- Фильтр по формам -->
            <div class="sa-form-filter">
                <form method="get">
                    <input type="hidden" name="page" value="sa_form_submissions">
                    <select name="form" onchange="this.form.submit()">
                        <option value="">Все формы</option>
                        <?php foreach ($forms as $slug => $form): ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($current_form, $slug); ?>>
                                <?php echo esc_html($form['title']); ?> (<?php echo esc_html($slug); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            
            <?php if (empty($submissions)): ?>
                <p>Нет отправленных форм.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="50">ID</th>
                            <th>Форма</th>
                            <th>Данные</th>
                            <th>Пользователь</th>
                            <th>Дата отправки</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $submission): 
                            $form_title = isset($forms[$submission->form_slug]['title']) ? $forms[$submission->form_slug]['title'] : $submission->form_slug;
                            $data = json_decode($submission->form_data, true);
                            $user_info = $submission->user_id ? get_userdata($submission->user_id) : null;
                        ?>
                            <tr>
                                <td><?php echo $submission->id; ?></td>
                                <td>
                                    <strong><?php echo esc_html($form_title); ?></strong><br>
                                    <small>Шорткод: <?php echo esc_html($submission->form_slug); ?></small>
                                </td>
                                <td>
                                    <div class="sa-form-data-preview">
                                        <?php if (is_array($data)): ?>
                                            <?php foreach ($data as $label => $value): ?>
                                                <div><strong><?php echo esc_html($label); ?>:</strong> <?php echo esc_html($value); ?></div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <?php echo esc_html($data); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($user_info): ?>
                                        <?php echo esc_html($user_info->display_name); ?><br>
                                        <small><?php echo esc_html($user_info->user_email); ?></small>
                                    <?php else: ?>
                                        <em>Гость</em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d.m.Y H:i', strtotime($submission->created_at)); ?></td>
                                <td>
                                    <button class="button button-small sa-view-details" 
                                            data-id="<?php echo $submission->id; ?>">Подробнее</button>
                                    <a href="?page=sa_form_submissions&delete=<?php echo $submission->id; ?>&_wpnonce=<?php echo wp_create_nonce('delete_submission_' . $submission->id); ?>" 
                                       class="button button-small button-link-delete"
                                       onclick="return confirm('Удалить эту запись?')">Удалить</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Пагинация -->
                <div class="sa-pagination">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page
                    ]);
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Модальное окно с деталями
            document.querySelectorAll('.sa-view-details').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    // Здесь можно сделать AJAX запрос для получения полных данных
                    alert('Детальный просмотр записи ID: ' + id + '\nМожно расширить функционал для просмотра всех данных.');
                });
            });
        });
    </script>
    
    <style>
        .sa-form-filter {
            margin-bottom: 20px;
        }
        .sa-form-filter select {
            min-width: 300px;
            padding: 5px;
        }
        .sa-form-data-preview {
            max-height: 150px;
            overflow-y: auto;
            padding: 5px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .sa-pagination {
            margin-top: 20px;
            text-align: center;
        }
    </style>
    <?php
    
    // Обработка удаления записи
    if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) {
        $submission_id = intval($_GET['delete']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_submission_' . $submission_id)) {
            $wpdb->delete($table, ['id' => $submission_id]);
            echo '<div class="updated"><p>Запись удалена.</p></div>';
        }
    }
}

// ------------------- СТАРАЯ ФУНКЦИОНАЛЬНОСТЬ (для совместимости) --------------------

// Форма заявки на курсы (старый шорткод)
add_shortcode('sa_application_form', 'sa_application_form_shortcode');
function sa_application_form_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Пожалуйста, войдите для подачи заявки.</p>';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'survival_applications';
    $msg = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sa_application_nonce']) && wp_verify_nonce($_POST['sa_application_nonce'], 'sa_application_action')) {
        $course_options = [
            'Основы алгоритмизации и программирования',
            'Основы веб-дизайна',
            'Основы проектирования баз данных',
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
            $msg = '<p style="color:red;">Выберите корректный курс.</p>';
        } elseif (!$start_date) {
            $msg = '<p style="color:red;">Некорректная дата начала обучения.</p>';
        } elseif (!in_array($payment_method, ['Наличными', 'Перевод по номеру телефона'])) {
            $msg = '<p style="color:red;">Выберите способ оплаты.</p>';
        } else {
            $wpdb->insert($table_name, [
                'user_id' => get_current_user_id(),
                'course_name' => $course_name,
                'start_date' => $start_date,
                'payment_method' => $payment_method,
                'status' => 'Новая',
                'created_at' => current_time('mysql'),
            ]);
            $msg = '<p style="color:green;">Заявка отправлена на рассмотрение администратору.</p>';
        }
    }

    ob_start();
    echo $msg;
    ?>
    <form method="post" novalidate style="max-width: 500px; margin: 20px auto; padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">
        <h3>Заявка на курс</h3>
        <p>
            <select name="course_name" required style="width: 100%; padding: 10px;">
                <option value="">Выберите курс</option>
                <option value="Основы алгоритмизации и программирования">Основы алгоритмизации и программирования</option>
                <option value="Основы веб-дизайна">Основы веб-дизайна</option>
                <option value="Основы проектирования баз данных">Основы проектирования баз данных</option>
            </select>
        </p>
        <p><input type="text" name="start_date" placeholder="Дата начала (ДД.ММ.ГГГГ)" required pattern="\d{2}\.\d{2}\.\d{4}" style="width: 100%; padding: 10px;"></p>
        <p>
            <select name="payment_method" required style="width: 100%; padding: 10px;">
                <option value="">Выберите способ оплаты</option>
                <option value="Наличными">Наличными</option>
                <option value="Перевод по номеру телефона">Перевод по номеру телефона</option>
            </select>
        </p>
        <?php wp_nonce_field('sa_application_action', 'sa_application_nonce'); ?>
        <p><input type="submit" value="Отправить" style="background: #0073aa; color: white; border: none; padding: 12px 25px; cursor: pointer;"></p>
    </form>
    <?php
    return ob_get_clean();
}

// Админ-панель для заявок на курсы
function sa_applications_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Требуется права администратора!');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'survival_applications';

    if (isset($_POST['sa_update_status_nonce']) && wp_verify_nonce($_POST['sa_update_status_nonce'], 'sa_update_status_action')) {
        $app_id = intval($_POST['application_id']);
        $new_status = sanitize_text_field($_POST['new_status']);
        $valid_statuses = ['Новая', 'Идет обучение', 'Обучение завершено'];
        if (in_array($new_status, $valid_statuses)) {
            $wpdb->update($table_name, ['status' => $new_status], ['id' => $app_id]);
        }
    }

    $applications = $wpdb->get_results("SELECT a.*, u.user_login FROM $table_name a LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID ORDER BY a.created_at DESC");

    echo '<div class="wrap"><h1>Заявки на курсы</h1>';
    echo '<table class="wp-list-table widefat fixed striped"><thead><tr>
           <th>ID</th><th>Пользователь</th><th>Курс</th><th>Дата начала</th><th>Способ оплаты</th><th>Статус</th><th>Дата подачи</th><th>Действия</th>
          </tr></thead><tbody>';

    foreach ($applications as $app) {
        $visible_status = $app->status === 'Новая' ? 'Скрыт (Новая)' : $app->status;
        echo '<tr>';
        echo '<td>' . esc_html($app->id) . '</td>';
        echo '<td>' . esc_html($app->user_login) . '</td>';
        echo '<td>' . esc_html($app->course_name) . '</td>';
        echo '<td>' . esc_html(date('d.m.Y', strtotime($app->start_date))) . '</td>';
        echo '<td>' . esc_html($app->payment_method) . '</td>';
        echo '<td>' . esc_html($visible_status) . '</td>';
        echo '<td>' . esc_html(date('d.m.Y H:i', strtotime($app->created_at))) . '</td>';
        echo '<td><form method="post" style="display: inline;">';
        echo '<input type="hidden" name="application_id" value="' . esc_attr($app->id) . '">';
        wp_nonce_field('sa_update_status_action', 'sa_update_status_nonce');
        echo '<select name="new_status">';
        foreach (['Новая', 'Идет обучение', 'Обучение завершено'] as $status) {
            $selected = $app->status === $status ? 'selected' : '';
            echo '<option value="' . esc_attr($status) . '" ' . $selected . '>' . esc_html($status) . '</option>';
        }
        echo '</select> <input type="submit" value="Обновить" class="button button-small">';
        echo '</form></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

// Шорткод "Мои заявки" + отзывы (старый)
add_shortcode('sa_my_applications', 'sa_my_applications_shortcode');
function sa_my_applications_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Пожалуйста, войдите для просмотра своих заявок.</p>';
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
            $msg = '<p style="color:green;">Отзыв добавлен.</p>';
        } else {
            $msg = '<p style="color:red;">Отзыв не может быть пустым.</p>';
        }
    }

    $applications = $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, f.feedback_text FROM $table_apps a LEFT JOIN $table_feedback f ON a.id = f.application_id WHERE a.user_id = %d ORDER BY a.created_at DESC",
        $user_id
    ));

    ob_start();
    echo $msg;
    if (empty($applications)) {
        echo '<p>У вас нет заявок на курсы.</p>';
    } else {
        foreach ($applications as $app) {
            echo '<div style="border:1px solid #ccc;padding:15px;margin-bottom:20px;background:#fff;">';
            echo '<h4 style="margin-top:0;">' . esc_html($app->course_name) . '</h4>';
            echo '<p><strong>Дата начала:</strong> ' . esc_html(date('d.m.Y', strtotime($app->start_date))) . '</p>';
            echo '<p><strong>Способ оплаты:</strong> ' . esc_html($app->payment_method) . '</p>';
            echo '<p><strong>Статус:</strong> ' . esc_html($app->status) . '</p>';
            echo '<p><strong>Отзыв:</strong> ' . ($app->feedback_text ? esc_html($app->feedback_text) : 'Отзыв не оставлен') . '</p>';

            if (!$app->feedback_text) {
                echo '<form method="post" style="margin-top:15px;">';
                wp_nonce_field('sa_feedback_action', 'sa_feedback_nonce');
                echo '<input type="hidden" name="application_id" value="' . esc_attr($app->id) . '">';
                echo '<textarea name="feedback_text" rows="3" style="width:100%;" placeholder="Оставьте отзыв"></textarea><br>';
                echo '<input type="submit" value="Отправить отзыв" style="margin-top:10px;">';
                echo '</form>';
            }
            echo '</div>';
        }
    }
    return ob_get_clean();
}

// Регистрация и вход (старые шорткоды, оставлены для совместимости)
add_shortcode('sa_register_form', function() {
    return is_user_logged_in() ? '<p>Вы уже зарегистрированы.</p>' : '<p>Используйте конструктор форм для создания форм регистрации.</p>';
});
add_shortcode('sa_login_form', function() {
    return is_user_logged_in() ? '<p>Вы уже вошли в систему.</p>' : '<p>Используйте конструктор форм для создания формы входа.</p>';
});

// ------------------- Завершение плагина --------------------
?>