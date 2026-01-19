<?php

/**
 * Plugin Name: LearnFlow Admin
 * Description: Plugin for managing course applications, registration, and sliders.
 * Version: 1.3
 * Author: YeakYeakYeak
 */

// Создание таблиц при активации плагина

register_activation_hook(__FILE__, 'sa_create_tables');

function sa_create_tables()

{

  global $wpdb;

  $charset_collate = $wpdb->get_charset_collate();



  $table_apps = $wpdb->prefix . 'survival_applications';

  $table_feedback = $wpdb->prefix . 'survival_app_feedback';



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



// Валидация данных регистрации

function sa_validate_registration_data($data)

{

  $errors = [];



  if (!preg_match('/^[a-zA-Z0-9]{6,}$/', $data['username'])) {

    $errors[] = 'Логин должен содержать латиницу и цифры минимум 6 символов.';

  }

  if (strlen($data['password']) < 8) {

    $errors[] = 'Пароль должен содержать минимум 8 символов.';

  }

  if (!preg_match('/^[А-Яа-яЁё\s]+$/u', $data['full_name'])) {

    $errors[] = 'ФИО должно содержать только кириллицу и пробелы.';

  }

  if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {

    $errors[] = 'Неверный формат электронной почты.';

  }

  return $errors;

}



// Шорткод для регистрации

add_shortcode('sa_register_form', 'sa_register_form_shortcode');

function sa_register_form_shortcode()

{

  if (is_user_logged_in()) {

    return '<p>Вы уже зарегистрированы.</p>';

  }



  $errors = [];

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sa_register_nonce']) && wp_verify_nonce($_POST['sa_register_nonce'], 'sa_register_action')) {

    $username = sanitize_user($_POST['username']);

    $password = $_POST['password'];

    $full_name = sanitize_text_field($_POST['full_name']);

    $phone = sanitize_text_field($_POST['phone']);

    $email = sanitize_email($_POST['email']);



    $errors = sa_validate_registration_data(compact('username', 'password', 'full_name', 'phone', 'email'));



    if (username_exists($username) || email_exists($email)) {

      $errors[] = 'Пользователь с таким логином или e-mail уже существует.';

    }



    if (empty($errors)) {

      $user_id = wp_create_user($username, $password, $email);

      if (!is_wp_error($user_id)) {

        update_user_meta($user_id, 'full_name', $full_name);

        update_user_meta($user_id, 'phone', $phone);

        wp_set_current_user($user_id);

        wp_set_auth_cookie($user_id);

        wp_redirect(home_url());

        exit;

      } else {

        $errors[] = 'Ошибка при регистрации, попробуйте позже.';

      }

    }

  }



  ob_start();

  if ($errors) {

    foreach ($errors as $error) {

      echo '<p style="color:red;">' . esc_html($error) . '</p>';

    }

  }

?>

  <form method="post" novalidate>

    <p><input type="text" name="username" placeholder="Логин (латиница и цифры, минимум 6)" required pattern="[a-zA-Z0-9]{6,}"></p>

    <p><input type="password" name="password" placeholder="Пароль (минимум 8 символов)" required minlength="8"></p>

    <p><input type="text" name="full_name" placeholder="ФИО (кириллица и пробелы)" required pattern="[А-Яа-яЁё\s]+"></p>

    <p><input type="text" name="phone" placeholder="Телефон 8(XXX)XXX-XX-XX" required></p>

    <p><input type="email" name="email" placeholder="Email" required></p>

    <?php wp_nonce_field('sa_register_action', 'sa_register_nonce'); ?>

    <p><input type="submit" value="Создать пользователя"></p>

  </form>

  <p><a href="<?php echo wp_login_url(); ?>">Еще не зарегистрированы? Регистрация</a></p>

<?php

  return ob_get_clean();

}



// Шорткод для входа

add_shortcode('sa_login_form', 'sa_login_form_shortcode');

function sa_login_form_shortcode()

{

  if (is_user_logged_in()) return '<p>Вы уже вошли в систему.</p>';



  $error = '';

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sa_login_nonce']) && wp_verify_nonce($_POST['sa_login_nonce'], 'sa_login_action')) {

    $creds = [

      'user_login' => sanitize_user($_POST['username']),

      'user_password' => $_POST['password'],

      'remember' => isset($_POST['remember']),

    ];

    $user = wp_signon($creds, false);

    if (is_wp_error($user)) {

      $error = 'Неверный логин или пароль.';

    } else {

      wp_redirect(home_url());

      exit;

    }

  }



  ob_start();

  if ($error) echo '<p style="color:red;">' . esc_html($error) . '</p>';

?>

  <form method="post" novalidate>

    <p><input type="text" name="username" placeholder="Логин" required></p>

    <p><input type="password" name="password" placeholder="Пароль" required></p>

    <p><label><input type="checkbox" name="remember"> Запомнить меня</label></p>

    <?php wp_nonce_field('sa_login_action', 'sa_login_nonce'); ?>

    <p><input type="submit" value="Войти"></p>

  </form>

  <p><a href="<?php echo wp_registration_url(); ?>">Еще не зарегистрированы? Регистрация</a></p>

<?php

  return ob_get_clean();

}



// Форма заявки

add_shortcode('sa_application_form', 'sa_application_form_shortcode');

function sa_application_form_shortcode()

{

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

    $start_date_raw = sanitize_text_field($_POST['start_date']); // ДД.ММ.ГГГГ

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

  <form method="post" novalidate>

    <p>

      <select name="course_name" required>

        <option value="">Выберите курс</option>

        <option value="Основы алгоритмизации и программирования">Основы алгоритмизации и программирования</option>

        <option value="Основы веб-дизайна">Основы веб-дизайна</option>

        <option value="Основы проектирования баз данных">Основы проектирования баз данных</option>

      </select>

    </p>

    <p><input type="text" name="start_date" placeholder="Дата начала (ДД.ММ.ГГГГ)" required pattern="\d{2}\.\d{2}\.\d{4}"></p>

    <p>Способ оплаты:

      <select name="payment_method" required>

        <option value="">Выберите</option>

        <option value="Наличными">Наличными</option>

        <option value="Перевод по номеру телефона">Перевод по номеру телефона</option>

      </select>

    </p>

    <?php wp_nonce_field('sa_application_action', 'sa_application_nonce'); ?>

    <p><input type="submit" value="Отправить"></p>

  </form>

<?php

  return ob_get_clean();

}



// Админ панель. Только для админа (WordPress role check)

add_action('admin_menu', 'sa_admin_menu');

function sa_admin_menu()

{

  add_menu_page('Заявки', 'Заявки', 'manage_options', 'sa_applications', 'sa_admin_page');

}



function sa_admin_page()

{

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



  echo '<div class="wrap"><h1>Все заявки</h1>';

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

    echo '<td><form method="post">';

    echo '<input type="hidden" name="application_id" value="' . esc_attr($app->id) . '">';

    wp_nonce_field('sa_update_status_action', 'sa_update_status_nonce');

    echo '<select name="new_status">';

    foreach (['Новая', 'Идет обучение', 'Обучение завершено'] as $status) {

      $selected = $app->status === $status ? 'selected' : '';

      echo '<option value="' . esc_attr($status) . '" ' . $selected . '>' . esc_html($status) . '</option>';

    }

    echo '</select> <input type="submit" value="Обновить">';

    echo '</form></td>';

    echo '</tr>';

  }

  echo '</tbody></table></div>';

}



// Шорткод "Мои заявки" + отзывы

add_shortcode('sa_my_applications', 'sa_my_applications_shortcode');

function sa_my_applications_shortcode()

{

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

  foreach ($applications as $app) {

    echo '<div style="border:1px solid #ccc;padding:10px;margin-bottom:15px;">';

    echo '<p><strong>Курс:</strong> ' . esc_html($app->course_name) . '</p>';

    echo '<p><strong>Дата начала:</strong> ' . esc_html(date('d.m.Y', strtotime($app->start_date))) . '</p>';

    echo '<p><strong>Способ оплаты:</strong> ' . esc_html($app->payment_method) . '</p>';

    echo '<p><strong>Статус:</strong> ' . esc_html($app->status) . '</p>';

    echo '<p><strong>Отзыв:</strong> ' . ($app->feedback_text ? esc_html($app->feedback_text) : 'Отзыв не оставлен') . '</p>';



    if (!$app->feedback_text) {

      echo '<form method="post">';

      wp_nonce_field('sa_feedback_action', 'sa_feedback_nonce');

      echo '<input type="hidden" name="application_id" value="' . esc_attr($app->id) . '">';

      echo '<textarea name="feedback_text" rows="3" cols="40" placeholder="Оставьте отзыв"></textarea><br>';

      echo '<input type="submit" value="Отправить отзыв">';

      echo '</form>';

    }

    echo '</div>';

  }

  return ob_get_clean();

}