<?php

/**
 * Permite entrar a Adminer con contraseña (SQLite no tiene password real).
 * La contraseña se toma de ADMINER_PASSWORD (por defecto: nexomailer).
 */
require_once 'plugins/login-password-less.php';

$password = getenv('ADMINER_PASSWORD') ?: 'nexomailer';

return new AdminerLoginPasswordLess(
    password_hash($password, PASSWORD_DEFAULT)
);
