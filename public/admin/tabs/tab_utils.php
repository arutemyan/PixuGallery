<?php
namespace App\Admin\Tabs;
function checkAccess(): void{
    if (!defined('ADMIN_TABS_ALLOWED'))
    {
        http_response_code(403);
        exit;
    }
    if (!ADMIN_TABS_ALLOWED)
    {
        http_response_code(403);
        exit;
    }
}