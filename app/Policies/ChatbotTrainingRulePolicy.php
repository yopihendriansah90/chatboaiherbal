<?php

namespace App\Policies;

use App\Models\ChatbotTrainingRule;
use App\Models\User;

class ChatbotTrainingRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('content_reviewer', 'supervisor');
    }

    public function view(User $user, ChatbotTrainingRule $rule): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ChatbotTrainingRule $rule): bool
    {
        return $user->hasRole('supervisor');
    }
}
