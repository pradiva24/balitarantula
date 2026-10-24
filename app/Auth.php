<?php
declare(strict_types=1);

function current_admin(): ?array
{
    return isset($_SESSION['admin']) && is_array($_SESSION['admin']) ? $_SESSION['admin'] : null;
}

function require_admin(): array
{
    $admin = current_admin();
    if ($admin === null) {
        redirect('admin/login.php');
    }
    return $admin;
}

function require_role(array $allowedRoles): array
{
    $admin = require_admin();
    if (!in_array($admin['role'] ?? '', $allowedRoles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
    return $admin;
}
