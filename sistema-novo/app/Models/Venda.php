<?php

namespace App\Models;

use App\Enums\VendaStatus;
use Database\Factories\VendaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venda extends Model
{
    /** @use HasFactory<VendaFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data' => 'datetime',
            'subtotal' => 'decimal:2',
            'desconto' => 'decimal:2',
            'total' => 'decimal:2',
            'custo_total' => 'decimal:2',
            'lucro_total' => 'decimal:2',
            'pago' => 'boolean',
            'status' => VendaStatus::class,
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(VendaItem::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }
}
