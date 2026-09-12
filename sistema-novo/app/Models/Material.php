<?php

namespace App\Models;

use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'materiais';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'estoque' => 'decimal:3',
            'estoque_minimo' => 'decimal:3',
            'custo_medio' => 'decimal:6',
            'essencial' => 'boolean',
        ];
    }

    public function getStatusAttribute(): string
    {
        return $this->stockStatus();
    }

    public function stockStatus(): string
    {
        $estoque = (float) $this->estoque;
        $minimo = (float) ($this->estoque_minimo ?? 0);

        if ($estoque <= 0) {
            return 'sem_estoque';
        }

        if ($minimo > 0 && $estoque <= $minimo) {
            return 'baixo';
        }

        return 'normal';
    }

    public function stockStatusLabel(): string
    {
        return match ($this->stockStatus()) {
            'sem_estoque' => 'Sem estoque',
            'baixo' => 'Baixo',
            default => 'Normal',
        };
    }

    public function produtoBom(): HasMany
    {
        return $this->hasMany(ProdutoBom::class);
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
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
