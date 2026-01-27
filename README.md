#LearnFlow Admin - WordPress Form Builder Plugin
Description
LearnFlow Admin is a powerful and flexible WordPress plugin for creating custom registration forms, login forms, and managing course applications. Built with a user-friendly form builder interface, this plugin allows administrators to create unlimited forms with automatic field validation and custom shortcodes.

Features
🛠️ Form Builder System
Custom Registration Forms: Create unlimited registration forms with any combination of fields
Custom Login Forms: Build login forms with optional registration links
Visual Form Editor: Intuitive drag-and-drop style interface
Unlimited Fields: Add as many fields as needed to any form
Field Types Supported: Text, Password, Email, Telephone, Number, Textarea, Select, Checkbox, Radio, Date

🔧 Smart Field Validation
Automatic Constraints: Intelligent field recognition with auto-applied validation rules
Login Field: Latin letters and numbers, minimum 6 characters
Password Field: Minimum 8 characters
Full Name Field: Cyrillic letters and spaces only
Phone Field: Format: 8(XXX)XXX-XX-XX
Email Field: Standard email format validation

📋 Course Application Management
Course Application Forms: Users can apply for courses
Admin Dashboard: Manage and update application statuses
User Feedback System: Students can leave feedback on completed courses
Application Tracking: Track all applications in a centralized admin panel

🎯 Shortcode System
Custom Shortcodes: Each form gets a unique shortcode (e.g., [sa_register_main_form])
Easy Integration: Insert forms anywhere using shortcodes
Multiple Forms: Create different forms for different pages

👥 User Management
Automatic Registration: Users are automatically registered in WordPress
Auto-Login: Users are automatically logged in after registration
Profile Data: Custom field data saved as user meta
Role Management: New users get "Subscriber" role by default

🎨 Styling & UX
Clean Admin Interface: Modern, intuitive admin dashboard
Responsive Forms: Forms adapt to any screen size
Custom CSS: Easy to style with custom CSS classes
User-Friendly Error Messages: Clear validation error messages

Installation
Upload the learnflow-admin folder to the /wp-content/plugins/ directory
Activate the plugin through the 'Plugins' menu in WordPress
Go to 'Constructor Forms' in the admin menu to start creating forms

Usage
Creating Registration Forms
Navigate to Constructor Forms → Registration Forms
Click "Create New Registration Form"
Add fields with appropriate names/labels (see auto-validation rules below)
Save the form and use the generated shortcode on any page

Creating Login Forms
Navigate to Constructor Forms → Login Forms
Create a new login form
Optionally link to a registration form
Use the generated shortcode on your login page
Managing Course Applications
Use [sa_application_form] shortcode for application submission
Use [sa_my_applications] shortcode for users to view their applications
Admin can manage applications from Applications menu

Field Auto-Validation Rules
The plugin automatically detects field types and applies validation:

Field Name/Label Contains	Auto Validation Applied
login, username, Логин	Latin letters + numbers, min 6 chars
password, pass, Пароль	Minimum 8 characters
fio, full_name, ФИО	Cyrillic letters + spaces only
phone, tel, Телефон	Format: 8(XXX)XXX-XX-XX
email, mail, Почта	Standard email format
Shortcodes
Registration Forms
text
[sa_register_form_id]
Replace form_id with your form's ID (e.g., [sa_register_main_form])

Login Forms
text
[sa_login_form_id]
Replace form_id with your form's ID (e.g., [sa_login_main])

Course Applications
text
[sa_application_form]      # Submit course application
[sa_my_applications]       # View user's applications
Examples
Basic Registration Form
Create a form with fields: Login, Password, Email, Full Name, Phone
Field names: username, password, email, full_name, phone
All fields get automatic validation based on names
Login Form with Registration Link
Create a login form that links to your registration form
Users see "Not registered yet? Register" link
Clicking shows registration form

Requirements
WordPress 5.0 or higher
PHP 7.4 or higher
MySQL 5.6 or higher

Recommended Setup
Create a registration form with all required fields
Create a login form linked to the registration form
Add registration form to your registration page
Add login form to your login page
Use application forms for course enrollment

Database Structure
The plugin creates three tables:
wp_survival_applications - Course applications
wp_survival_app_feedback - Application feedback
Custom forms are stored in WordPress options

Security Features
Nonce Verification: All forms protected with WordPress nonces
Data Sanitization: All user input properly sanitized
SQL Injection Protection: Prepared statements for database queries
XSS Protection: Output escaping on all displayed data
CSRF Protection: Form submission validation
