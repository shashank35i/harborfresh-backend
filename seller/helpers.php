<?php
function clean($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function valid_phone($phone) {
    return preg_match("/^[0-9]{10}$/", $phone);
}

function valid_password($password) {
    return preg_match(
        "/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/",
        $password
    );
}
?>
