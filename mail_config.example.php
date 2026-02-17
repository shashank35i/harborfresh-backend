<?php
// Example mail configuration for HarborFresh
return [
    'from_email' => 'no-reply@harborfresh.local',
    'from_name'  => 'HarborFresh',
    'smtp' => [
        'enabled'  => false,
        'host'     => 'smtp.gmail.com',
        'port'     => 587,
        'username' => '',
        'password' => '',
        'secure'   => 'tls'
    ]
];
?>
