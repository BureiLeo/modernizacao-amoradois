<?php

namespace App\Models;

use Database\Factories\ProdutoVariacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProdutoVariacao extends Model
{
    /** @use HasFactory<ProdutoVariacaoFactory> */
    use HasFactory;

    protected $table = 'produto_variacoes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'preco_venda' => 'decimal:2',
            'custo_referencia' => 'decimal:6',
            'ativo' => 'boolean',
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function vendaItens(): HasMany
    {
        return $this->hasMany(VendaItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
