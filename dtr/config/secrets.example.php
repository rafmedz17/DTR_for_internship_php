<?php
// Copy this file to secrets.php and fill in your values. secrets.php is gitignored.
return [
    // Local (XAMPP) defaults. On InfinityFree, use the values from
    // Control Panel > MySQL Databases (host looks like sqlXXX.infinityfree.com).
    'db_host'       => 'localhost',
    'db_name'       => 'dtr_system',
    'db_user'       => 'root',
    'db_pass'       => '',
    // Brevo SMTP credentials (Brevo > SMTP & API > SMTP)
    'mail_username' => '',
    'mail_password' => '',
    'mail_from'     => 'you@example.com',
    // Base URL of the site, no trailing slash (used in password reset links)
    'app_url'       => 'http://localhost/dtr',
];
