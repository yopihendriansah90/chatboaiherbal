<?php

namespace App\Policies;

use App\Models\ChatbotTrainingCandidate;
use App\Models\User;

class ChatbotTrainingCandidatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('content_reviewer', 'supervisor');
    }

    public function view(User $user, ChatbotTrainingCandidate $candidate): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ChatbotTrainingCandidate $candidate): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, ChatbotTrainingCandidate $candidate): bool
    {
        return $user->hasRole('supervisor');
    }
}
