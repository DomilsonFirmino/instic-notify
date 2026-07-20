<?php

namespace App\Policies;

use App\Models\Informativo;
use App\Models\User;

class InformativoPolicy
{
    /**
     * Determine if the user can create an Informativo.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->hasRole('editor')
            || $user->can('informativo.create')
            || $user->can('informativo.create_own');
    }

    /**
     * Admin can skip review and set aprovado, or pull a published item back to rascunho.
     */
    public function updateStatus(User $user, Informativo $informativo, string $to): bool
    {
        if ($informativo->status === $to) {
            return true;
        }

        if ($informativo->status === 'despublicado') {
            return false;
        }

        if ($user->hasRole('admin')) {
            // Publicado → rascunho removes it from the feed
            if ($informativo->status === 'publicado' && $to === 'rascunho') {
                return true;
            }

            return in_array($to, ['rascunho', 'pendente', 'aprovado'], true)
                && $informativo->status !== 'publicado';
        }

        $isAuthor = $user->id === $informativo->author_id;

        // Author: rascunho/revisao → rascunho|pendente
        if ($isAuthor && in_array($informativo->status, ['rascunho', 'revisao'], true)) {
            return in_array($to, ['rascunho', 'pendente'], true);
        }

        return false;
    }

    /**
     * Determine if the user can delete an Informativo.
     */
    public function delete(User $user, Informativo $informativo): bool
    {
        if ($user->hasRole('admin') || $user->can('informativo.delete')) {
            return true;
        }

        return $user->id === $informativo->author_id
            && $informativo->status === 'rascunho';
    }
}
