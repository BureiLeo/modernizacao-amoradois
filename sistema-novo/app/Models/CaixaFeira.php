<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaixaFeira extends Model
{
    protected $table = 'caixas_feira';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'aberto' => 'boolean',
            'fechado_em' => 'datetime',
        ];
    }

    public function vendas(): HasMany
    {
        return $this->hasMany(Venda::class);
    }

    public function transferencias(): HasMany
    {
        return $this->hasMany(TransferenciaFeira::class);
    }
}
