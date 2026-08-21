<?php

declare(strict_types=1);

function admin_logged_in(): bool
{
    return (int) ($_SESSION['pelish_admin_id'] ?? 0) > 0;
}

function admin_require_login(): void
{
    if (!admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function admin_logout(): void
{
    unset($_SESSION['pelish_admin_id']);
}
