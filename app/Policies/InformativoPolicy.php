<?php

namespace App\Policies;

use App\Models\Informativo;
use App\Models\User;

class InformativoPolicy
{
    /**
     * Determine if the user can transition an Informativo to target status.
     */
    public function create(User $user, Informativo $informativo): bool
    {
        // Any user with 'informativos.create' permission can create an Informativo
        return $user->can('informativos.create') || ($user->can('informativos.create_own') && $user->id === $informativo->author_id);
    }
    public function updateStatus(User $user, Informativo $informativo, string $to): bool
    {
        // Reviewers: pendente, revisao, aprovado, rejeitado
        if ($user->can('informativos.review')) {
            return in_array($to, ['pendente','revisao','aprovado','rejeitado'], true);
        }

        // Authors: only if owner and editing within rascunho/revisao contexts
        if ($user->id === $informativo->author_id) {
            // Author can send to pendente from rascunho
            if (in_array($informativo->status, ['rascunho','revisao'], true) && $to === 'pendente') {
                return true;
            }
            // Author can revert to rascunho from revisao to keep editing
            if ($informativo->status === 'revisao' && $to === 'rascunho') {
                return true;
            }
        }

        return false;
    }
}
