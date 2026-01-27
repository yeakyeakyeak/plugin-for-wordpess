# EU
WordPress plugin for course management with user registration, application forms, and admin dashboard. Includes customizable sliders, payment options, and feedback system. Perfect for educational websites needing structured course enrollment and content display. 
LearnFlow Admin - WordPress Course Management Plugin
https://img.shields.io/badge/WordPress-5.0+-blue.svg
https://img.shields.io/badge/PHP-7.2+-purple.svg


A powerful WordPress plugin for managing course applications, user registrations, and dynamic sliders. Perfect for educational websites, training centers, and online learning platforms.

✨ Features
👥 User Management
Registration & Login: Secure user registration and login system with data validation
Profile Management: Custom user fields including full name and phone number
Authentication: Built on WordPress security standards

📝 Course Applications
Application Forms: Structured forms for course enrollment
Multiple Payment Options: Cash and phone transfer payment methods
Course Selection: Predefined course options with start date selection
Status Tracking: Track applications from New → In Progress → Completed

🎛️ Admin Dashboard
Application Management: Centralized interface for managing all applications
Real-time Updates: Update application status directly from admin panel
User Management: View user applications and feedback

🖼️ Slider System
Custom Sliders: Create unlimited sliders with images and text
Drag & Drop Ordering: Control slide display order
Shortcode Integration: Easy placement anywhere with [slider id="X"]

💬 Feedback System
Course Feedback: Students can leave feedback on completed courses
Admin Moderation: Manage and review all feedback

🚀 Installation
Download the plugin files
Upload to /wp-content/plugins/learnflow-admin/
Activate through WordPress plugins page
Tables will be created automatically

📋 Shortcodes
Authentication
[sa_register_form] - User registration form
[sa_login_form] - User login form
Course Management
[sa_application_form] - Submit new course applications
[sa_my_applications] - View and manage personal applications
Content Display
[slider id="X"] - Display slider (replace X with slider ID)

🗂️ Database Structure
The plugin creates four main tables:
wp_survival_applications - Course applications
wp_survival_app_feedback - Student feedback
wp_survival_sliders - Slider definitions
wp_survival_slider_images - Slider images and content

🔒 Security
SQL injection protection
XSS prevention
Data validation and sanitization
WordPress nonce system
User capability checks

📈 Requirements
WordPress 6.0 or higher
PHP 7.3 or higher
MySQL 5.6 or higher

🤝 Contributing
Contributions are welcome! Please feel free to submit a Pull Request.

📄 License
This project is licensed under the GPL v3 License - see the LICENSE file for details.

📞 Support
For support, feature requests, or bug reports, please open an issue on GitHub.
Perfect for: Educational institutions, online course platforms, training centers, workshops, and any organization needing structured course management with professional presentation.

