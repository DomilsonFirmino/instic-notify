<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\V1\StoreInformativoRequest;
use App\Http\Requests\Api\V1\UpdateInformativoRequest;
use App\Jobs\NotifyUsersJob;
use App\Models\Favorite;
use App\Models\Informativo;
use App\Models\Log;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log as FacadeLog;
use App\Models\InformativoFile;

class InformativoController extends ApiController
{

    public function statusOptions()
    {
        $options = [
            'rascunho',      // Em edição pelo autor
            'pendente',      // Enviado para revisão
            'revisao',       // Precisa de correção
            'aprovado',      // Aprovado, aguardando publicação
            'agendado',      // Publicação programada
            'publicado',     // Já está visível
            'despublicado',  // Foi retirado do ar
            'rejeitado'      // Recusado definitivamente
        ];
        return $this->success($options);
    }

    public function index(Request $request)
    {
        $auth = $request->user();
        $isStaff = $auth->hasAnyRole(['admin', 'editor', 'revisor']);
        $with = ['category','course','year','department','author','files'];
        if ($isStaff) {
            $with[] = 'reviews';
            $with[] = 'publisher';
        }

        $query = Informativo::query()->with($with);

        if ($auth->hasAnyRole(['admin', 'editor'])) {
            // No status/audience filter
        } elseif ($auth->hasRole('revisor')) {
            $query->whereIn('status', ['pendente', 'aprovado', 'agendado', 'publicado']);
        } else {
            // Leitor (or any non-staff): published + AND audience
            $query->where('status', 'publicado')->visibleToAudience($auth);
        }

        if ($request->filled('status')) {
            $raw = $request->input('status');
            // Support both array (?status[]=a&status[]=b) and comma-separated (?status=a,b)
            $statuses = is_array($raw)
                ? $raw
                : array_filter(array_map('trim', explode(',', (string) $raw)));
            if (count($statuses) === 1) {
                $query->where('status', reset($statuses));
            } else {
                $query->whereIn('status', $statuses);
            }
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'recentes');

        switch ($sort) {
            case 'antigos':
                $query->orderBy('published_at')->orderBy('created_at');
                break;
            case 'alfabetica':
                $query->orderBy('title');
                break;
            case 'favoritos':
                if ($auth) {
                    $query->withExists([
                        'favorites as is_favorited' => fn ($favoriteQuery) => $favoriteQuery->where('user_id', $auth->id),
                    ])->orderByDesc('is_favorited');
                }
                $query->orderByDesc('published_at')->orderByDesc('created_at');
                break;
            case 'recentes':
            default:
                $query->orderByDesc('published_at')->orderByDesc('created_at');
                break;
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(1, min($perPage, 500));
        $paginator = $query->paginate($perPage);
        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
        return $this->success($paginator->items(), $meta);
    }

    // public function store(Request $request)
    public function store(StoreInformativoRequest $request)
    {

        // Log::info('Creating informativo with data: ', $request);
        $data = $request->validated();

        // Processa todos os arquivos enviados (array ou único)
        $uploadedFiles = is_array($data['files'] ?? null) ? $data['files'] : array_filter([$data['files'] ?? null]);
        unset($data['files']);

        // Support alias field 'unpublish_at' by mapping to 'unpublished_at'
        if (array_key_exists('unpublish_at', $data) && !array_key_exists('unpublished_at', $data)) {
            $data['unpublished_at'] = $data['unpublish_at'];
            unset($data['unpublish_at']);
        }
        // Create via the user's authored relationship (sets author_id automatically)
        // Editors: rascunho|pendente; Admin may also set aprovado to skip review
        $allowedInitial = $request->user()->hasRole('admin')
            ? ['rascunho', 'pendente', 'aprovado']
            : ['rascunho', 'pendente'];
        if (!in_array($data['status'] ?? 'rascunho', $allowedInitial, true)) {
            $data['status'] = 'rascunho';
        }
        if (($data['status'] ?? null) === 'aprovado') {
            $data['rejection_reason'] = null;
        }
        $informativo = $request->user()->authoredInformativos()->create($data);

        foreach ($uploadedFiles as $file) {
            $informativo->files()->create([
                'path' => $file->store('informativos_files', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);
        }

        // Audit log
        Log::create([
            'user_id' => $request->user()->id,
            'action' => 'informativo.store',
            'description' => 'Informativo criado ID '.$informativo->id.' com status '.$informativo->status,
            'created_at' => now(),
        ]);

        // Notify reviewers and admins when submitted for review
        if ($informativo->status === 'pendente') {
            $notifiedUsers = User::role(['revisor', 'admin'])->get();
            NotifyUsersJob::dispatch($notifiedUsers, [
                'informativo_id' => $informativo->id,
                'title' => 'Informativo pendente de revisão',
                'message' => 'O informativo #'.$informativo->id.' necessita de revisão.',
            ]);
        }
        return $this->success($informativo->load(['category','course','year','author','publisher']), status:201);
    }

    public function files(Request $request)
    {
        $auth = $request->user();

        $query = InformativoFile::query();

        $paginator = $query->paginate();
        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
        return $this->success($paginator->items(), $meta);
    }

    public function show(Informativo $informativo)
    {
        $auth = request()->user();

        // Non-staff (leitores): only published items in audience
        if ($auth && !$auth->hasAnyRole(['admin', 'editor', 'revisor'])) {
            if ($informativo->status !== 'publicado') {
                return $this->error('Informativo não disponível.', 'FORBIDDEN', [], 403);
            }

            if (!$informativo->isVisibleTo($auth)) {
                return $this->error('Acesso negado ao informativo.', 'FORBIDDEN', [], 403);
            }
        }

        return $this->success($informativo->load(['category','course','year','author','publisher','reviews','favorites','files']));
    }

    public function update(UpdateInformativoRequest $request, Informativo $informativo)
    {
        $auth = $request->user();
        $data = $request->validated();
        $previousStatus = $informativo->status;

        // Status changes via PUT are limited by InformativoPolicy::updateStatus
        // (approve/reject/schedule use dedicated endpoints)
        if (array_key_exists('status', $data) && $data['status'] !== $informativo->status) {
            if (!Gate::forUser($auth)->allows('updateStatus', [$informativo, $data['status']])) {
                return $this->error(
                    'Transição de status não permitida. Use as ações de revisão ou agendamento.',
                    'FORBIDDEN',
                    [],
                    403
                );
            }
        }

        // Admin can update content; status still gated above
        if ($auth && $auth->hasRole('admin')) {
            if (($data['status'] ?? null) === 'aprovado') {
                $data['rejection_reason'] = null;
            }
            if (($data['status'] ?? null) === 'rascunho' && $previousStatus === 'publicado') {
                $data['published_by'] = null;
                $data['published_at'] = null;
                $data['publish_at'] = null;
                $data['unpublished_at'] = null;
            }
            $informativo->update($data);
            Log::create([
                'user_id' => $auth->id,
                'action' => 'informativo.update',
                'description' => 'Informativo atualizado ID '.$informativo->id,
                'created_at' => now(),
            ]);
            if ($previousStatus === 'publicado' && $informativo->status === 'rascunho') {
                $this->notifyReadersUnpublished($informativo);
            }
            if ($previousStatus !== 'pendente' && $informativo->status === 'pendente') {
                $notifiedUsers = User::role(['revisor', 'admin'])->get();
                NotifyUsersJob::dispatch($notifiedUsers, [
                    'informativo_id' => $informativo->id,
                    'title' => 'Informativo pendente de revisão',
                    'message' => 'O informativo #'.$informativo->id.' necessita de revisão.',
                ]);
            }
            if ($previousStatus !== 'aprovado' && $informativo->status === 'aprovado') {
                $author = User::find($informativo->author_id);
                $admins = User::role('admin')->get();
                $notifiedUsers = collect([$author])->merge($admins)->unique('id');
                NotifyUsersJob::dispatch($notifiedUsers->filter(), [
                    'informativo_id' => $informativo->id,
                    'title' => 'Informativo aprovado',
                    'message' => 'O informativo #'.$informativo->id.' foi aprovado.',
                ]);
            }
            return $this->success($informativo->load(['category','course','year','author','publisher']));
        }

        // Author (including reviewer-authors) can edit own drafts/revisao before reviewer branch
        $isAuthor = ($auth->id === $informativo->author_id);
        if ($isAuthor && in_array($informativo->status, ['rascunho', 'revisao'], true)) {
            FacadeLog::info('Author updating informativo ID '.$informativo->id);
            $informativo->update($data);

            if ($previousStatus !== 'pendente' && $informativo->status === 'pendente') {
                $notifiedUsers = User::role(['revisor', 'admin'])->get();
                NotifyUsersJob::dispatch($notifiedUsers, [
                    'informativo_id' => $informativo->id,
                    'title' => 'Informativo pendente de revisão',
                    'message' => 'O informativo #'.$informativo->id.' necessita de revisão.',
                ]);
            }

            Log::create([
                'user_id' => $auth->id,
                'action' => 'informativo.update',
                'description' => 'Informativo atualizado ID '.$informativo->id,
                'created_at' => now(),
            ]);
            return $this->success($informativo->load(['category','course','year','author','publisher']));
        }

        // Reviewer can only edit content when status is pendente (no status jumps via PUT)
        if ($auth && ($auth->hasRole('revisor') || $auth->can('informativo.review'))) {
            if ($informativo->status !== 'pendente') {
                return $this->error('O revisor só pode editar informativos pendentes.', 'FORBIDDEN', [], 403);
            }
            unset($data['status']);
            $informativo->update($data);
            Log::create([
                'user_id' => $auth->id,
                'action' => 'informativo.update',
                'description' => 'Informativo atualizado pelo revisor ID '.$informativo->id,
                'created_at' => now(),
            ]);
            return $this->success($informativo->load(['category','course','year','author','publisher']));
        }

        return $this->error('Apenas o autor pode editar o informativo.', 'FORBIDDEN', [], 403);
    }

    public function destroy(Informativo $informativo)
    {
        $auth = request()->user();
        if (!$auth || !Gate::forUser($auth)->allows('delete', $informativo)) {
            return $this->error('Sem permissão para remover este informativo.', 'FORBIDDEN', [], 403);
        }

        $informativo->delete();
        Log::create([
            'user_id' => $auth->id,
            'action' => 'informativo.destroy',
            'description' => 'Informativo removido ID '.$informativo->id,
            'created_at' => now(),
        ]);
        return $this->success(['deleted' => true]);
    }

    // Publishment is performed by scheduler; no manual publish/unpublish endpoints
    public function schedule(Request $request, Informativo $informativo)
    {
        $data = $request->validate([
            'publish_at' => ['nullable','date','after_or_equal:today'],
            'unpublished_at' => ['nullable','date','after:publish_at','after_or_equal:today'],
        ]);

        FacadeLog::info('Scheduling informativo ID '.$informativo->id.' for publish at '.$data['publish_at']);
        $auth = $request->user();
        // Allow setting publish_at only when approved; by reviewer or author
        $canSchedule = (($auth->hasRole('revisor') || $auth->hasRole('admin')));
        if (!$canSchedule) {
            return $this->error('Sem permissão para agendar.', 'FORBIDDEN', [], 403);
        }
        if ($informativo->status !== 'aprovado') {
            return $this->error('Agendamento apenas quando o informativo está aprovado.', 'UNPROCESSABLE', [], 422);
        }
        $updateData = [
            'rejection_reason' => null,
        ];
        // Impede atualizar publish_at se já publicado
        if (array_key_exists('publish_at', $data)) {
            if ($informativo->status === 'publicado') {
                return $this->error('Não é possível alterar a data de publicação de um informativo já publicado.', 'FORBIDDEN', [], 403);
            }
            $updateData['publish_at'] = $data['publish_at'];
        }
        // Impede atualizar unpublished_at se já despublicado
        if (array_key_exists('unpublished_at', $data)) {
            if ($informativo->status === 'despublicado') {
                return $this->error('Não é possível alterar a data de despublicação de um informativo já despublicado.', 'FORBIDDEN', [], 403);
            }
            $updateData['unpublished_at'] = $data['unpublished_at'];
        }
        // Se publish_at tem valor, muda status para agendado, senão mantém aprovado
        if (!empty($data['publish_at'])) {
            $updateData['status'] = 'agendado';
        } else {
            $updateData['status'] = 'aprovado';
        }
        $informativo->update($updateData);

        // Notify author and admins
        $author = User::find($informativo->author_id);
        $admins = User::role('admin')->get();
        $notifiedUsers = collect([$author])->merge($admins)->unique('id');
        NotifyUsersJob::dispatch($notifiedUsers->filter(), [
            'informativo_id' => $informativo->id,
            'title' => 'Informativo agendado',
            'message' => 'O informativo #'.$informativo->id.' foi agendado para publicação em '.$data['publish_at'].($data['unpublished_at'] ? (', despublicação em '.$data['unpublished_at']) : ''),
        ]);

        Log::create([
            'user_id' => $auth->id,
            'action' => 'informativo.schedule',
            'description' => 'Publicação agendada para Informativo ID '.$informativo->id.' em '.$data['publish_at'].($data['unpublished_at'] ? (', despublicação em '.$data['unpublished_at']) : ''),
            'created_at' => now(),
        ]);
        return $this->success($informativo->fresh());
    }

    public function reject(Request $request, Informativo $informativo)
    {
        $auth = $request->user();
        if (!($auth->hasRole('revisor') || $auth->hasRole('admin') || $auth->can('informativo.review'))) {
            return $this->error('Sem permissão para rejeitar.', 'FORBIDDEN', [], 403);
        }

        // Revisor: only pendente. Admin: any except rascunho, rejeitado, despublicado
        $allowedFrom = $auth->hasRole('admin')
            ? ['pendente', 'revisao', 'aprovado', 'agendado', 'publicado']
            : ['pendente'];
        if (!in_array($informativo->status, $allowedFrom, true)) {
            return $this->error(
                $auth->hasRole('admin')
                    ? 'Não é possível rejeitar neste estado.'
                    : 'Rejeição apenas em itens pendentes.',
                'UNPROCESSABLE',
                [],
                422
            );
        }

        $request->validate(['reason' => ['required','string']]);
        $wasPublished = $informativo->status === 'publicado';

        $informativo->update([
            'status' => 'rejeitado',
            'rejection_reason' => $request->input('reason'),
            'published_by' => null,
            'published_at' => null,
            'publish_at' => null,
            'unpublished_at' => null,
        ]);

        if ($wasPublished) {
            $this->notifyReadersUnpublished($informativo);
        }

        $author = User::find($informativo->author_id);
        $admins = User::role('admin')->get();
        $notifiedUsers = collect([$author])->merge($admins)->unique('id');
        NotifyUsersJob::dispatch($notifiedUsers->filter(), [
            'informativo_id' => $informativo->id,
            'title' => 'Informativo rejeitado',
            'message' => 'O informativo #'.$informativo->id.' foi rejeitado.',
        ]);

        Log::create([
            'user_id' => $auth->id,
            'action' => 'informativo.reject',
            'description' => 'Informativo rejeitado ID '.$informativo->id,
            'created_at' => now(),
        ]);
        return $this->success($informativo->fresh());
    }

    public function approve(Request $request, Informativo $informativo)
    {
        $auth = $request->user();
        if (!($auth->hasRole('revisor') || $auth->hasRole('admin') || $auth->can('informativo.review'))) {
            return $this->error('Sem permissão para aprovar.', 'FORBIDDEN', [], 403);
        }

        // Revisor: only from pendente. Admin may approve from draft/review to skip the pipeline.
        $allowedFrom = $auth->hasRole('admin')
            ? ['rascunho', 'revisao', 'pendente']
            : ['pendente'];
        if (!in_array($informativo->status, $allowedFrom, true)) {
            return $this->error(
                $auth->hasRole('admin')
                    ? 'Aprovação apenas a partir de rascunho, revisão ou pendente.'
                    : 'Aprovação apenas em itens pendentes.',
                'UNPROCESSABLE',
                [],
                422
            );
        }
        $informativo->update([
            'status' => 'aprovado',
            'rejection_reason' => null
        ]);

        $author = User::find($informativo->author_id);
        $admins = User::role('admin')->get();
        $notifiedUsers = collect([$author])->merge($admins)->unique('id');
        NotifyUsersJob::dispatch($notifiedUsers->filter(), [
            'informativo_id' => $informativo->id,
            'title' => 'Informativo aprovado',
            'message' => 'O informativo #'.$informativo->id.' foi aprovado.',
        ]);

        Log::create([
            'user_id' => $auth->id,
            'action' => 'informativo.approve',
            'description' => 'Informativo aprovado ID '.$informativo->id,
            'created_at' => now(),
        ]);
        return $this->success($informativo->fresh());
    }

    public function requestChanges(Request $request, Informativo $informativo)
    {
        $auth = $request->user();
        if (!$auth || !($auth->hasRole('revisor') || $auth->hasRole('admin') || $auth->can('informativo.review'))) {
            return $this->error('Sem permissão para solicitar revisão.', 'FORBIDDEN', [], 403);
        }

        // Revisor: only pendente. Admin: any except rascunho, revisao, despublicado
        $allowedFrom = $auth->hasRole('admin')
            ? ['pendente', 'aprovado', 'agendado', 'publicado', 'rejeitado']
            : ['pendente'];
        if (!in_array($informativo->status, $allowedFrom, true)) {
            return $this->error(
                $auth->hasRole('admin')
                    ? 'Não é possível solicitar alterações neste estado.'
                    : 'Solicitação de revisão apenas em itens pendentes.',
                'UNPROCESSABLE',
                [],
                422
            );
        }

        $data = $request->validate(['feedback' => ['required','string']]);
        $wasPublished = $informativo->status === 'publicado';

        $informativo->update([
            'status' => 'revisao',
            'rejection_reason' => null,
            'published_by' => null,
            'published_at' => null,
            'publish_at' => null,
            'unpublished_at' => null,
        ]);

        $informativo->reviews()->create([
            'reviewer_id' => $auth->id,
            'decision' => 'revisao',
            'comment' => $data['feedback'],
            'created_at' => now(),
        ]);

        if ($wasPublished) {
            $this->notifyReadersUnpublished($informativo);
        }

        $author = User::find($informativo->author_id);
        $admins = User::role('admin')->get();
        $notifiedUsers = collect([$author])->merge($admins)->unique('id');
        NotifyUsersJob::dispatch($notifiedUsers->filter(), [
            'informativo_id' => $informativo->id,
            'title' => 'Solicitadas mudanças no informativo',
            'message' => 'O informativo #'.$informativo->id.' recebeu uma solicitação de mudanças.',
        ]);

        Log::create([
            'user_id' => $auth->id,
            'action' => 'informativo.request_changes',
            'description' => 'Solicitadas mudanças para Informativo ID '.$informativo->id,
            'created_at' => now(),
        ]);
        return $this->success($informativo->fresh()->load('reviews'));
    }

    /**
     * Admin: pull a published informativo back to draft (unpublish).
     */
    public function revertToDraft(Request $request, Informativo $informativo)
    {
        $auth = $request->user();
        if (!$auth || !$auth->hasRole('admin')) {
            return $this->error('Sem permissão para reverter a rascunho.', 'FORBIDDEN', [], 403);
        }
        if ($informativo->status !== 'publicado') {
            return $this->error('Apenas informativos publicados podem voltar a rascunho.', 'UNPROCESSABLE', [], 422);
        }

        $informativo->update([
            'status' => 'rascunho',
            'rejection_reason' => null,
            'published_by' => null,
            'published_at' => null,
            'publish_at' => null,
            'unpublished_at' => null,
        ]);

        $this->notifyReadersUnpublished($informativo);

        $author = User::find($informativo->author_id);
        $admins = User::role('admin')->get();
        $notifiedUsers = collect([$author])->merge($admins)->unique('id');
        NotifyUsersJob::dispatch($notifiedUsers->filter(), [
            'informativo_id' => $informativo->id,
            'title' => 'Informativo revertido a rascunho',
            'message' => 'O informativo #'.$informativo->id.' foi despublicado e voltou a rascunho.',
        ]);

        Log::create([
            'user_id' => $auth->id,
            'action' => 'informativo.revert_to_draft',
            'description' => 'Informativo ID '.$informativo->id.' revertido de publicado para rascunho',
            'created_at' => now(),
        ]);

        return $this->success($informativo->fresh());
    }

    private function notifyReadersUnpublished(Informativo $informativo): void
    {
        $readers = $informativo->relevantReadersQuery()->get();
        if ($readers->isEmpty()) {
            return;
        }

        NotifyUsersJob::dispatch($readers, [
            'informativo_id' => $informativo->id,
            'title' => 'Informativo despublicado',
            'message' => 'O informativo #'.$informativo->id.' foi despublicado.',
        ]);
    }

    public function toggleFavorite(Request $request, Informativo $informativo)
    {
        // Só permite favoritar se o informativo estiver publicado
        if ($informativo->status !== 'publicado') {
            return $this->error('Só é possível favoritar informativos publicados.', 'FORBIDDEN', [], 403);
        }
        $user = Auth::user();

        $exists = Favorite::where('user_id', $user->id)
            ->where('informativo_id', $informativo->id)
            ->exists();

        if ($exists) {
            Favorite::where('user_id', $user->id)
                ->where('informativo_id', $informativo->id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'removido dos favoritos',
                'is_favorited' => false,
            ]);
        }

        Favorite::create([
            'user_id' => $user->id,
            'informativo_id' => $informativo->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'adicionado aos favoritos',
            'is_favorited' => true,
        ]);
    }
}
