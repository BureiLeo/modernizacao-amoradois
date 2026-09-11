<?php

namespace App\Models;

use App\Enums\PerdaTipo;
use Database\Factories\PerdaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Perda extends Model
{
    /** @use HasFactory<PerdaFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data' => 'datetime',
            'tipo' => PerdaTipo::class,
            'quantidade' => 'decimal:3',
            'custo_total' => 'decimal:2',
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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
