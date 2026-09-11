<?php

namespace App\Models;

use Database\Factories\ProdutoBomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoBom extends Model
{
    /** @use HasFactory<ProdutoBomFactory> */
    use HasFactory;

    protected $table = 'produto_bom';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:4',
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
