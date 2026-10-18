<?php
// Brevo SMTP configuration. Credentials live in secrets.php (gitignored).
$secrets = require __DIR__ . '/secrets.php';

define('MAIL_HOST',      'smtp-relay.brevo.com');
define('MAIL_PORT',      587);
define('MAIL_USERNAME',  $secrets['mail_username']);
define('MAIL_PASSWORD',  $secrets['mail_password']);
define('MAIL_FROM',      $secrets['mail_from']);
define('MAIL_FROM_NAME', 'DTR System');
define('APP_URL',        $secrets['app_url']);
