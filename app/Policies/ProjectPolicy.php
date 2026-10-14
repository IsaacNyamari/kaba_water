<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Projects are vendor-private. Every check confirms the project belongs to the
 * user's own workspace (on top of the global vendor scope), and closed or
 * cancelled projects are read-only until reopened.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $this->own($user, $project) && $user->can('projects.view');
    }

    public function create(User $user): bool
    {
        return $user->can('projects.manage');
    }

    public function update(User $user, Project $project): bool
    {
        return $this->open($user, $project) && $user->can('projects.manage');
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->own($user, $project) && $project->status === 'draft' && $user->can('projects.manage');
    }

    public function report(User $user, Project $project): bool
    {
        return $this->open($user, $project) && $user->can('projects.report');
    }

    public function submitExpense(User $user, Project $project): bool
    {
        return $this->open($user, $project) && $user->can('projects.expenses.submit');
    }

    public function approveExpense(User $user, Project $project): bool
    {
        return $this->open($user, $project) && $user->can('projects.expenses.approve');
    }

    public function manageMaterials(User $user, Project $project): bool
    {
        return $this->open($user, $project) && $user->can('projects.materials.manage');
    }

    public function reviseBudget(User $user, Project $project): bool
    {
        return $this->open($user, $project) && $user->can('projects.budget.revise');
    }

    public function close(User $user, Project $project): bool
    {
        return $this->own($user, $project) && $user->can('projects.close');
    }

    protected function own(User $user, Project $project): bool
    {
        return $user->vendor_id !== null && $project->vendor_id === $user->vendor_id;
    }

    protected function open(User $user, Project $project): bool
    {
        return $this->own($user, $project) && ! $project->isClosed();
    }
}
