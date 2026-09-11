<?php

namespace App\Models;

use Database\Factories\VendaItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendaItem extends Model
{
    /** @use HasFactory<VendaItemFactory> */
    use HasFactory;

    protected $table = 'venda_itens';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'integer',
            'preco_unitario' => 'decimal:2',
            'custo_unitario_calculado' => 'decimal:6',
            'lucro_unitario' => 'decimal:6',
            'subtotal' => 'decimal:2',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function produtoVariacao(): BelongsTo
    {
        return $this->belongsTo(ProdutoVariacao::class);
    }
}
