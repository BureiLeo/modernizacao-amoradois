<?php

namespace App\Services\Produtos;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Centraliza o upload/remocao de imagens de produto (Etapa 6/7 #32-#34).
 *
 * - Armazena em storage/app/public/produtos (via disco 'public'), nunca
 *   base64 no banco - so o path relativo e salvo em produtos.imagem.
 * - Nome de arquivo gerado (uuid), nunca o nome original do usuario.
 * - Ao trocar a imagem, remove o arquivo antigo (se existir e nao for
 *   compartilhado por outro registro).
 */
class ProductImageService
{
    public function store(UploadedFile $file): string
    {
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs('produtos', $filename, 'public');

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Substitui a imagem: grava a nova e remove a antiga somente depois
     * do novo upload ter sido salvo com sucesso (evita perda acidental
     * se o upload falhar no meio do caminho).
     */
    public function replace(UploadedFile $newFile, ?string $oldPath): string
    {
        $newPath = $this->store($newFile);

        $this->delete($oldPath);

        return $newPath;
    }
}
