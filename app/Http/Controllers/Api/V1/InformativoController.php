<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\V1\StoreInformativoRequest;
use App\Http\Requests\Api\V1\UpdateInformativoRequest;
use App\Jobs\NotifyUsersJob;
use App\Models\Favorite;
use App\Models\Informativo;
use App\Models\Log;
use App\Models\Notification as UserNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $userRole = $auth->role ?? "leitor";
        $with = ['category','course','year','department','author','files'];
        if ($userRole !== 'leitor') {
            $with[] = 'reviews';
            $with[] = 'publisher';
        }

        $query = Informativo::query()->with($with);

        switch ($userRole) {
            case 'leitor':
                $query->where('status', 'publicado');
                // $query->where(function($q) use ($auth) {
                //     $q->whereNull('course_id')->orWhere('course_id', $auth->course_id)
                //       ->orWhereNull('year_id')->orWhere('year_id', $auth->year_id)
                //       ->orWhereNull('department_id')->orWhere('department_id', $auth->department_id);
                // });
                $query->where(function($q) use ($auth) {
                    $q->orWhere('course_id', $auth->course_id)
                      ->orWhere('year_id', $auth->year_id)
                      ->orWhere('department_id', $auth->department_id)
                      ->orWhere(function($sub) {
                          $sub->whereNull('course_id')
                              ->whereNull('year_id')
                              ->whereNull('department_id');
                      });
                });
                break;
            case 'revisor':
                $query->whereIn('status', ['pendente', 'aprovado', 'agendado', 'publicado']);
                break;
            // Adicione outros cases para outros roles se necessário
            default:
                // Nenhum filtro extra para outros roles
                break;
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
        // Ensure initial status is only 'rascunho' or 'pendente'
        if (!in_array($data['status'] ?? 'rascunho', ['rascunho','pendente'], true)) {
            $data['status'] = 'rascunho';
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
            $notifiedUsers = User::whereIn('role', ['revisor', 'admin'])->get();
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
        return $this->success($informativo->load(['category','course','year','author','publisher','reviews','favorites','files']));
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
            if ($informativo->status === 'pendente') {
                $notifiedUsers = User::whereIn('role', ['revisor', 'admin'])->get();
                NotifyUsersJob::dispatch($notifiedUsers, [
                    'informativo_id' => $informativo->id,
                    'title' => 'Informativo pendente de revisão',
                    'message' => 'O informativo #'.$informativo->id.' necessita de revisão.',
                ]);
            }
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
        FacadeLog::info('Author updating informativo ID '.$informativo->id);
        // Se o autor está editando, só pode mudar status para 'rascunho' ou 'pendente'
        if (isset($data['status']) && !in_array($data['status'], ['rascunho', 'pendente'], true)) {
            return $this->error('O autor só pode definir o status como rascunho ou pendente.', 'FORBIDDEN', [], 403);
        }
        $informativo->update($data);

        // Se o status foi alterado para 'pendente', notifica revisores e admins
        if ($informativo->status === 'pendente') {
            $notifiedUsers = User::whereIn('role', ['revisor', 'admin'])->get();
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

        // Notify author and admins
        $author = User::find($informativo->author_id);
        $admins = User::where('role', 'admin')->get();
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
        if (!($auth->hasRole('revisor') || $auth->hasRole('admin') || $auth->can('informativos.review'))) {
            return $this->error('Sem permissão para rejeitar.', 'FORBIDDEN', [], 403);
        }
        if ($informativo->status !== 'pendente') {
            return $this->error('Rejeição apenas em itens pendentes.', 'UNPROCESSABLE', [], 422);
        }
        $request->validate(['reason' => ['required','string']]);
        $informativo->update(['status' => 'rejeitado', 'rejection_reason' => $request->input('reason'), 'published_by' => null, 'published_at' => null]);

        // Notify author and admins
        $author = User::find($informativo->author_id);
        $admins = User::where('role', 'admin')->get();
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

        // Notify author and admins
        $author = User::find($informativo->author_id);
        $admins = User::where('role', 'admin')->get();
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
            $author = User::find($informativo->author_id);
            $admins = User::where('role', 'admin')->get();
            $notifiedUsers = collect([$author])->merge($admins)->unique('id');
            NotifyUsersJob::dispatch($notifiedUsers->filter(), [
                'informativo_id' => $informativo->id,
                'title' => 'Solicitadas mudanças no informativo',
                'message' => 'O informativo #'.$informativo->id.' recebeu uma solicitação de mudanças.',
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

        // Notify author and admins
        $author = User::find($informativo->author_id);
        $admins = User::where('role', 'admin')->get();
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
