<?php

namespace App\Models;

use App\Enums\FormaPagamento;
use App\Enums\StatusPagamento;
use Database\Factories\PagamentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pagamento extends Model
{
    /** @use HasFactory<PagamentoFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'forma' => FormaPagamento::class,
            'valor' => 'decimal:2',
            'status' => StatusPagamento::class,
            'pago_em' => 'datetime',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }
}
