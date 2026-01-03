<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\V1\StoreInformativoRequest;
use App\Http\Requests\Api\V1\UpdateInformativoRequest;
use App\Models\Favorite;
use App\Models\Informativo;
use App\Models\Log;
use App\Models\Notification as UserNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as FacadeLog;

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
        $userRole = $auth->role ?? "leitor";
        $with = ['category','course','year','department'];
        if ($userRole !== 'leitor') {
            $with[] = 'reviews';
            $with[] = 'author';
            $with[] = 'publisher';
        }
        $query = Informativo::query()->with($with);


        switch ($userRole) {
            case 'leitor':
                $query->where('status', 'publicado');
                // $query->where(function($q) use ($auth) {
                //     $q->whereNull('course_id')->orWhere('course_id', $auth->course_id);
                // });
                // $query->where(function($q) use ($auth) {
                //     $q->whereNull('year_id')->orWhere('year_id', $auth->year_id);
                // });
                // $query->where(function($q) use ($auth) {
                //     $q->whereNull('department_id')->orWhere('department_id', $auth->department_id);
                // });
                break;
            case 'revisor':
                $query->where('status', 'pendente')
                ->orWhere('status', 'aprovado')
                ->orWhere('status', 'agendado')
                ->orWhere('status', 'publicado');
                break;
            // Adicione outros cases para outros roles se necessário
            default:
                // Nenhum filtro extra para outros roles
                break;
        }

        $paginator = $query->paginate();
        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
        return $this->success($paginator->items(), $meta);
    }

    public function store(StoreInformativoRequest $request)
    {
        $data = $request->validated();
        // Support alias field 'unpublish_at' by mapping to 'unpublished_at'
        if (array_key_exists('unpublish_at', $data) && !array_key_exists('unpublished_at', $data)) {
            $data['unpublished_at'] = $data['unpublish_at'];
            unset($data['unpublish_at']);
        }
        // Create via the user's authored relationship (sets author_id automatically)
        // Ensure initial status is only 'rascunho' or 'pendente'
        if (!in_array($data['status'] ?? 'rascunho', ['rascunho','pendente'], true)) {
            $data['status'] = 'rascunho';
        }
        $informativo = $request->user()->authoredInformativos()->create($data);

        // Audit log
        Log::create([
            'user_id' => $request->user()->id,
            'action' => 'informativo.store',
            'description' => 'Informativo criado ID '.$informativo->id.' com status '.$informativo->status,
            'created_at' => now(),
        ]);

        // Notify reviewers when submitted for review
        if ($informativo->status === 'pendente') {
            $reviewers = User::where('role', 'revisor')->get();
            foreach ($reviewers as $rev) {
                UserNotification::create([
                    'user_id' => $rev->id,
                    'title' => 'Informativo pendente de revisão',
                    'message' => 'O informativo #'.$informativo->id.' necessita de revisão.',
                    'created_at' => now(),
                ]);
            }
        }
        return $this->success($informativo->load(['category','course','year','author','publisher']), status:201);
    }

    public function show(Informativo $informativo)
    {
        return $this->success($informativo->load(['category','course','year','author','publisher','reviews']));
    }

    public function update(UpdateInformativoRequest $request, Informativo $informativo)
    {
        $auth = $request->user();
        // Admin bypasses all checks
        if ($auth && $auth->hasRole('admin')) {
            $informativo->update($request->validated());
            Log::create([
                'user_id' => $auth->id,
                'action' => 'informativo.update',
                'description' => 'Informativo atualizado ID '.$informativo->id,
                'created_at' => now(),
            ]);
            return $this->success($informativo->load(['category','course','year','author','publisher']));
        }

        // Reviewer can only edit when status is pendente
        if ($auth && ($auth->hasRole('revisor'))) {
            if ($informativo->status !== 'pendente') {
                return $this->error('O revisor só pode editar informativos pendentes.', 'FORBIDDEN', [], 403);
            }
            $informativo->update($request->validated());
            Log::create([
                'user_id' => $auth->id,
                'action' => 'informativo.update',
                'description' => 'Informativo atualizado pelo revisor ID '.$informativo->id,
                'created_at' => now(),
            ]);
            return $this->success($informativo->load(['category','course','year','author','publisher']));
        }

        // Author can edit only when status is rascunho or revisao
        $isAuthor = ($auth->id === $informativo->author_id);
        if(!$isAuthor) {
            return $this->error('Apenas o autor pode editar o informativo.', 'FORBIDDEN', [], 403);
        }

        if (!in_array($informativo->status, ['rascunho','revisao'], true)) {
            return $this->error('Só se pode editar em rascunho ou revisão.', 'FORBIDDEN', [], 403);
        }

        $data = $request->validated();
        // Se o autor está editando, só pode mudar status para 'rascunho' ou 'pendente'
        if (isset($data['status']) && !in_array($data['status'], ['rascunho', 'pendente'], true)) {
            return $this->error('O autor só pode definir o status como rascunho ou pendente.', 'FORBIDDEN', [], 403);
        }
        $informativo->update($data);

        Log::create([
            'user_id' => $auth->id,
            'action' => 'informativo.update',
            'description' => 'Informativo atualizado ID '.$informativo->id,
            'created_at' => now(),
        ]);
        return $this->success($informativo->load(['category','course','year','author','publisher']));
    }

    public function destroy(Informativo $informativo)
    {
        $auth = request()->user();
        // Only allow delete by author while in rascunho
        if (!$auth || $auth->id !== $informativo->author_id || $informativo->status !== 'rascunho') {
            return $this->error('A remoção só é permitida em rascunho pelo autor.', 'FORBIDDEN', [], 403);
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
        if (!($auth->hasRole('revisor') || $auth->hasRole('admin') || $auth->can('informativos.review'))) {
            return $this->error('Sem permissão para rejeitar.', 'FORBIDDEN', [], 403);
        }
        if ($informativo->status !== 'pendente') {
            return $this->error('Rejeição apenas em itens pendentes.', 'UNPROCESSABLE', [], 422);
        }
        $request->validate(['reason' => ['required','string']]);
        $informativo->update(['status' => 'rejeitado', 'rejection_reason' => $request->input('reason'), 'published_by' => null, 'published_at' => null]);

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
        if (!($auth->hasRole('revisor') || $auth->hasRole('admin') || $auth->can('informativos.review'))) {
            return $this->error('Sem permissão para aprovar.', 'FORBIDDEN', [], 403);
        }
        if ($informativo->status !== 'pendente') {
            return $this->error('Aprovação apenas em itens pendentes.', 'UNPROCESSABLE', [], 422);
        }
        $informativo->update([
            'status' => 'aprovado',
            'rejection_reason' => null
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
        // Admins always have access
        if ($auth && $auth->hasRole('admin')) {
            $data = $request->validate(['feedback' => ['required','string']]);
            $informativo->update(['status' => 'revisao', 'rejection_reason' => null]);
            $informativo->reviews()->create([
                'reviewer_id' => $auth->id,
                'decision' => 'revisao',
                'comment' => $data['feedback'],
                'created_at' => now(),
            ]);
            Log::create([
                'user_id' => $auth->id,
                'action' => 'informativo.request_changes',
                'description' => 'Solicitadas mudanças (admin) para Informativo ID '.$informativo->id,
                'created_at' => now(),
            ]);
            return $this->success($informativo->fresh()->load('reviews'));
        }
        if (!$auth || !($auth->hasRole('revisor') || $auth->can('informativos.review'))) {
            return $this->error('Sem permissão para solicitar revisão.', 'FORBIDDEN', [], 403);
        }
        if ($informativo->status !== 'pendente') {
            return $this->error('Solicitação de revisão apenas em itens pendentes.', 'UNPROCESSABLE', [], 422);
        }
        $data = $request->validate(['feedback' => ['required','string']]);

        $informativo->update(['status' => 'revisao', 'rejection_reason' => null]);

        // Log review entry
        $informativo->reviews()->create([
            'reviewer_id' => $auth->id,
            'decision' => 'revisao',
            'comment' => $data['feedback'],
            'created_at' => now(),
        ]);

        Log::create([
            'user_id' => $auth->id,
            'action' => 'informativo.request_changes',
            'description' => 'Solicitadas mudanças para Informativo ID '.$informativo->id,
            'created_at' => now(),
        ]);
        return $this->success($informativo->fresh()->load('reviews'));
    }

    public function toggleFavorite(Request $request, Informativo $informativo)
    {
        // Só permite favoritar se o informativo estiver publicado
        if ($informativo->status !== 'publicado') {
            return $this->error('Só é possível favoritar informativos publicados.', 'FORBIDDEN', [], 403);
        }
        $userId = $request->user()?->id ?? $request->input('user_id');
        $fav = Favorite::where(['user_id'=>$userId,'informativo_id'=>$informativo->id])->first();
        if ($fav) { $fav->delete(); return $this->success(['favorite' => false]); }
        Favorite::create(['user_id'=>$userId,'informativo_id'=>$informativo->id,'created_at'=>now()]);
        return $this->success(['favorite' => true]);
    }
}
