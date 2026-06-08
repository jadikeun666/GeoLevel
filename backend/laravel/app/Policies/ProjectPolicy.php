<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Siapa pun yang login boleh melihat daftar proyeknya sendiri.
     * Index hanya menampilkan proyek milik user — filter di controller.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Hanya pemilik proyek yang boleh melihat detail.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    /**
     * Siapa pun yang login boleh membuat proyek baru.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Hanya pemilik yang boleh update metadata, hitung ulang, dan adjust.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    /**
     * Hanya pemilik yang boleh hapus proyek.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    // restore() dan forceDelete() tidak diimplementasi —
    // project tidak menggunakan SoftDeletes.
}