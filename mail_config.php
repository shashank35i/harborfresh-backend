<?php
// Central mail configuration for OTP and notifications
return [
    'from_email' => getenv('HARBORFRESH_FROM_EMAIL') ?: 'no-reply@harborfresh.local',
    'from_name'  => 'HarborFresh',
    'smtp' => [
        'enabled'  => (getenv('HARBORFRESH_SMTP_ENABLED') ?: 'false') === 'true',
        'host'     => getenv('HARBORFRESH_SMTP_HOST') ?: 'smtp.gmail.com',
        'port'     => (int)(getenv('HARBORFRESH_SMTP_PORT') ?: 587),
        'username' => getenv('HARBORFRESH_SMTP_USER') ?: '',
        'password' => getenv('HARBORFRESH_SMTP_PASS') ?: '',
        'secure'   => getenv('HARBORFRESH_SMTP_SECURE') ?: 'tls'
    ]
];
?>
