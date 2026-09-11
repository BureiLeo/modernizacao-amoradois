<?php

namespace App\Models;

use Database\Factories\ProdutoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends Model
{
    /** @use HasFactory<ProdutoFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'preco_venda' => 'decimal:2',
            'custo_referencia' => 'decimal:6',
            'estoque_minimo' => 'decimal:3',
            'ativo' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function variacoes(): HasMany
    {
        return $this->hasMany(ProdutoVariacao::class);
    }

    public function bom(): HasMany
    {
        return $this->hasMany(ProdutoBom::class);
    }

    public function vendaItens(): HasMany
    {
        return $this->hasMany(VendaItem::class);
    }

    public function perdas(): HasMany
    {
        return $this->hasMany(Perda::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
