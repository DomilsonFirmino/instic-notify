<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageUploadController extends ApiController
{
    /**
     * Upload image for editor
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadEditorImage(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|max:5120', // 5MB max
        ], [
            'image.required' => 'Imagem é obrigatória',
            'image.image' => 'O ficheiro deve ser uma imagem',
            'image.mimes' => 'Apenas JPEG, PNG, GIF e WebP são permitidos',
            'image.max' => 'O tamanho máximo permitido é 5MB',
        ]);

        try {
            if (!$request->hasFile('image')) {
                return $this->error(
                    'Imagem não encontrada no pedido',
                    'NO_FILE',
                    [],
                    400
                );
            }

            $file = $request->file('image');

            if (!$file->isValid()) {
                return $this->error(
                    'Ficheiro inválido ou danificado',
                    'INVALID_FILE',
                    [],
                    400
                );
            }

            // Store image in editors_images directory
            $path = $file->store('editors_images', 'public');

            if (!$path) {
                return $this->error(
                    'Falha ao armazenar ficheiro. Verifique as permissões da pasta.',
                    'STORAGE_ERROR',
                    [],
                    500
                );
            }

            // Get the public URL
            $url = asset("storage/{$path}");

            return $this->success([
                'url' => $url,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
            ], status: 201);
        } catch (\Exception $e) {
            \Log::error('Image upload error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'Erro ao fazer upload da imagem: ' . $e->getMessage(),
                'UPLOAD_ERROR',
                [],
                500
            );
        }
    }

    /**
     * Delete image file from storage
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteEditorImage(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        try {
            $path = $request->input('path');

            // Ensure path is safe (must start with editors_images/)
            if (!str_starts_with($path, 'editors_images/')) {
                return $this->error(
                    'Caminho de ficheiro inválido',
                    'INVALID_PATH',
                    [],
                    400
                );
            }

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            return $this->success([
                'message' => 'Imagem removida com sucesso',
            ]);
        } catch (\Exception $e) {
            return $this->error(
                'Erro ao remover imagem: ' . $e->getMessage(),
                'DELETE_ERROR',
                [],
                500
            );
        }
    }
}
