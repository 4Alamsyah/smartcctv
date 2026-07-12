<?php

use Illuminate\Support\Facades\Broadcast;

/**
 * Any authenticated dashboard user (Laravel session guard) may listen to the
 * live dispatch feed. Tighten to a role/permission check once RBAC is added
 * (e.g. `$user->hasRole('dispatcher')`).
 */
Broadcast::channel('dispatch-center', function ($user) {
    return $user !== null;
});
