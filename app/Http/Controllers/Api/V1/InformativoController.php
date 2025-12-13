<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\V1\StoreInformativoRequest;
use App\Http\Requests\Api\V1\UpdateInformativoRequest;
use App\Models\Favorite;
use App\Models\Informativo;
use Illuminate\Http\Request;

class InformativoController extends ApiController
{

    public function index(Request $request)
    {
        $query = Informativo::query()->with(['category','course','year','author','publisher']);

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
        $informativo = Informativo::create($request->validated());
        return $this->success($informativo->load(['category','course','year','author','publisher']), status:201);
    }

    public function show(Informativo $informativo)
    {
        return $this->success($informativo->load(['category','course','year','author','publisher','files']));
    }

    public function update(UpdateInformativoRequest $request, Informativo $informativo)
    {
        $informativo->update($request->validated());
        return $this->success($informativo->load(['category','course','year','author','publisher']));
    }

    public function destroy(Informativo $informativo)
    {
        $informativo->delete();
        return $this->success(['deleted' => true]);
    }

    public function publish(Request $request, Informativo $informativo)
    {
        $informativo->update([
            'status' => 'published',
            'published_by' => $request->user()?->id ?? $request->input('published_by'),
            'published_at' => now(),
            'rejection_reason' => null,
        ]);
        return $this->success($informativo->fresh());
    }

    public function unpublish(Informativo $informativo)
    {
        $informativo->update(['status' => 'draft', 'published_by' => null, 'published_at' => null]);
        return $this->success($informativo->fresh());
    }

    public function reject(Request $request, Informativo $informativo)
    {
        $request->validate(['reason' => ['required','string']]);
        $informativo->update(['status' => 'rejected', 'rejection_reason' => $request->input('reason'), 'published_by' => null, 'published_at' => null]);
        return $this->success($informativo->fresh());
    }

    public function toggleFavorite(Request $request, Informativo $informativo)
    {
        $userId = $request->user()?->id ?? $request->input('user_id');
        $fav = Favorite::where(['user_id'=>$userId,'informativo_id'=>$informativo->id])->first();
        if ($fav) { $fav->delete(); return $this->success(['favorite' => false]); }
        Favorite::create(['user_id'=>$userId,'informativo_id'=>$informativo->id,'created_at'=>now()]);
        return $this->success(['favorite' => true]);
    }
}
