# LearnFlow Admin

**LearnFlow Admin** is a powerful and flexible WordPress plugin designed for creating custom registration forms, login forms, and managing course applications.

The plugin is equipped with a convenient visual form builder, supports automatic field validation and shortcode usage, allowing administrators to create an unlimited number of forms without writing code.

---

## 🚀 Features

### 🛠️ Form Builder System

- **Custom Registration Forms** — create an unlimited number of registration forms
- **Custom Login Forms** — login forms with the ability to add a registration link
- **Visual Form Editor** — intuitive visual editor (drag & drop)
- **Unlimited Fields** — add any number of fields
- **Supported Field Types**:
  - Text
  - Password
  - Email
  - Telephone
  - Number
  - Textarea
  - Select
  - Checkbox
  - Radio
  - Date

---

### 🔧 Smart Field Validation

Automatic field type detection and application of validation rules:

- **Login** — Latin letters + numbers, minimum 6 characters
- **Password** — minimum 8 characters
- **Full Name** — Cyrillic letters and spaces only
- **Phone** — format `8(XXX)XXX-XX-XX`
- **Email** — standard email validation

---

### 📋 Course Application Management

- **Course Application Forms** — users can apply for courses
- **Admin Dashboard** — manage and update application statuses
- **User Feedback System** — student reviews of completed courses
- **Application Tracking** — centralized list of all applications

---

### 🎯 Shortcode System

- **Unique Shortcodes** — each form gets its own shortcode  
  Example: `[sa_register_main_form]`
- **Easy Integration** — insert forms into any pages and posts
- **Multiple Forms** — different forms for different pages

---

### 👥 User Management

- **Automatic Registration** — users are automatically registered in WordPress
- **Auto-Login** — automatic login after registration
- **Profile Data Storage** — form data is stored as user meta
- **Role Management** — the `Subscriber` role is assigned by default

---

### 🎨 Styling & UX

- **Clean Admin Interface** — modern and user-friendly admin panel
- **Responsive Forms** — adapts to any screen size
- **Custom CSS Support** — easy style customization
- **User-Friendly Errors** — clear and understandable error messages

---

## 📦 Installation

1. Upload the `learnflow-admin` folder to  
   `/wp-content/plugins/`
2. Activate the plugin in the **Plugins** menu
3. Go to **Constructor Forms** in the WordPress admin menu

---

## ⚙️ Usage

### Creating Registration Forms

1. **Constructor Forms → Registration Forms**
2. Click **Create New Registration Form**
3. Add fields with the required names (see auto-validation rules)
4. Save the form
5. Use the generated shortcode on a page

---

### Creating Login Forms

1. **Constructor Forms → Login Forms**
2. Create a new login form
3. (Optional) link a registration form
4. Insert the shortcode on the login page

---

### Managing Course Applications

- `[sa_application_form]` — application submission form
- `[sa_my_applications]` — view user applications
- The administrator manages applications via the **Applications** menu

---

## 🧠 Field Auto-Validation Rules

The plugin automatically applies validation based on the field name:

| Field Name / Label Contains | Validation Rule |
|-----------------------------|-----------------|
| `login`, `username`, `Логин` | Latin letters + numbers, min 6 chars |
| `password`, `pass`, `Пароль` | Minimum 8 characters |
| `fio`, `full_name`, `ФИО` | Cyrillic letters + spaces |
| `phone`, `tel`, `Телефон` | `8(XXX)XXX-XX-XX` |
| `email`, `mail`, `Почта` | Standard email format |

---

## 🔗 Shortcodes

### Registration Forms
### Registration Forms
- `[sa_register_form_id]` — registration form  
  Replace `form_id` with your form ID  
  **Example:** `[sa_register_main_form]`


## 🔐 Login Forms

### Login Form Shortcode

- `[sa_login_form_id]` — login form  
  Replace `form_id` with your form ID  
  **Example:** `[sa_login_main]`

---

## 🎓 Course Applications

- `[sa_application_form]` — course application form  
- `[sa_my_applications]` — view applications of the current user

---

## 🧩 Examples

### Basic Registration Form

Create a registration form with the following fields:

- **Login**
- **Password**
- **Email**
- **Full Name**
- **Phone**

**Field names:**

- `username`
- `password`
- `email`
- `full_name`
- `phone`

All fields automatically receive validation based on their name.

---

### Login Form with Registration Link

Create a login form linked to a registration form.

**Behavior:**
- The user sees a link  
  **"Not registered yet? Register"**
- Clicking it displays the registration form
- Registration and login work together without page reload

---

## ⚙️ Requirements

Minimum requirements for the plugin to work:

- **WordPress** 5.0 or higher
- **PHP** 7.4 or higher
- **MySQL** 5.6 or higher

---

## ✅ Recommended Setup

Recommended setup sequence:

1. Create a registration form with all required fields
2. Create a login form and link the registration form to it
3. Place the registration form on the registration page
4. Place the login form on the login page
5. Use application forms to enroll in courses

---

## 🗄️ Database Structure

The plugin automatically creates three tables:

- `wp_survival_applications` — course applications
- `wp_survival_app_feedback` — application feedback
- Custom forms are stored in **WordPress options**

---

## 🔒 Security Features

The plugin uses standard WordPress security mechanisms:

- **Nonce Verification**  
  All forms are protected by nonce tokens
- **Data Sanitization**  
  All user data is sanitized
- **SQL Injection Protection**  
  Prepared SQL statements are used
- **XSS Protection**  
  Data escaping on output
- **CSRF Protection**  
  Form submission validity checks
