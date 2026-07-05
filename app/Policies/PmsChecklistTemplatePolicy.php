<?php

namespace App\Policies;

use App\Models\PmsChecklistTemplate;
use App\Models\User;

class PmsChecklistTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function view(User $user, PmsChecklistTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, PmsChecklistTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, PmsChecklistTemplate $template): bool
    {
        return $this->viewAny($user);
    }
}
