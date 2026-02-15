<?php
if (!defined('ABSPATH')) exit;

/**
 * Add / ensure plugin capabilities
 */
function mst_add_caps(): void {
    $role = get_role('administrator');
    if ($role && !$role->has_cap('manage_mst_tickets')) {
        $role->add_cap('manage_mst_tickets');
    }
}

/**
 * Remove plugin capabilities (optional - not used now)
 */
function mst_remove_caps(): void {
    $role = get_role('administrator');
    if ($role && $role->has_cap('manage_mst_tickets')) {
        $role->remove_cap('manage_mst_tickets');
    }
}
