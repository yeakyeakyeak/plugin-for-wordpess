# LearnFlow Admin + Form Builder WordPress Plugin
A powerful, all-in-one WordPress plugin for creating custom forms, managing course applications, and handling dynamic form submissions with ease.

🚀 Features
Form Builder System
Drag-and-drop inspired UI - Intuitive form creation interface in WordPress admin
Multiple field types - Text, email, textarea, select dropdowns, checkboxes, radio buttons, date picker, number fields
Dynamic shortcodes - Automatically generates unique shortcodes for each form
Form validation - Built-in required field validation and sanitization

Form Management
Centralized form storage - All forms saved in WordPress options for easy management
Submission tracking - Complete logging of all form submissions with timestamps
User association - Automatic linking of submissions to logged-in users
Admin notifications - Email alerts for new submissions
Course Application System (Legacy)
Course management - Built-in system for handling course enrollments
Application tracking - Monitor application status (New, In Progress, Completed)
User feedback - Allow students to submit feedback on courses
Payment methods - Support for multiple payment options

Admin Interface
Clean dashboard - Separate admin pages for form builder, submissions, and course applications
Form preview - View all submissions with pagination and filtering
Data export - Easy access to submission data in admin area
Bulk actions - Delete submissions individually or in bulk

📋 Requirements
WordPress 6.0 or higher
PHP 7.2 or higher
MySQL 5.6 or higher

🛠 Installation
Download the plugin files
Upload to /wp-content/plugins/ directory
Activate through WordPress Plugins screen
Navigate to Form Builder in WordPress admin to get started

💡 Usage Examples
Creating a Contact Form
Go to Form Builder → Create New Form
Name your form "Contact Us" and set slug to contact_form
Add fields: Name (text), Email (email), Message (textarea)
Save and use shortcode [sa_form_contact_form] anywhere on your site
Managing Course Applications
Use shortcode [sa_application_form] for users to apply for courses
View all applications in Form Builder → Course Applications
Update application statuses as they progress

Viewing Submissions
Navigate to Form Builder → Form Submissions
Filter by form type or view all submissions
Click "Details" to view complete submission data

🎯 Shortcodes
Shortcode	Purpose	Description
[sa_form_{slug}]	Custom Forms	Insert any form created in the form builder
[sa_application_form]	Course Application	Legacy course enrollment form
[sa_my_applications]	My Applications	Users view their course applications
[sa_register_form]	Registration	(Legacy) Redirects to form builder
[sa_login_form]	Login	(Legacy) Redirects to form builder

🔧 Database Structure
The plugin creates three main tables:
wp_survival_applications - Stores course applications
wp_survival_app_feedback - Stores user feedback for courses
wp_sa_custom_forms_data - Stores all custom form submissions

🛡 Security Features
Nonce verification - All form submissions protected with WordPress nonces
SQL injection protection - Uses WordPress $wpdb prepared statements
XSS prevention - All output properly escaped with WordPress functions
Capability checks - Admin functions restricted to manage_options users
Data sanitization - All user input sanitized before processing

📊 Performance
Efficient queries - Optimized database queries with proper indexing
Pagination - All submission lists include pagination for large datasets
Caching ready - Compatible with WordPress caching plugins
Lightweight - Minimal impact on site performance

🔄 Compatibility
Themes - Works with any WordPress theme
Plugins - Compatible with most popular plugins
Multisite - Fully compatible with WordPress Multisite
Translation - Ready for internationalization (RTL support)

📈 Future Roadmap
Form export to CSV/Excel
Advanced field types (file upload, conditional logic)
Form analytics and reporting
Integration with email marketing services
Frontend form builder (drag & drop)
REST API for form submissions

🤝 Contributing
Contributions are welcome! Please feel free to submit a Pull Request.

📄 License
GPL v2 or later

👨‍💻 Author
YeakYeakYeak

⭐ Support
For support, feature requests, or bug reports, please create an issue in the GitHub repository.
