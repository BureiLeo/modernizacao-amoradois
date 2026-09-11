<?php

namespace App\Models;

use App\Enums\CaixaTipo;
use Database\Factories\CaixaMovimentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaixaMovimento extends Model
{
    /** @use HasFactory<CaixaMovimentoFactory> */
    use HasFactory;

    protected $table = 'caixa_movimentos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data' => 'datetime',
            'tipo' => CaixaTipo::class,
            'valor' => 'decimal:2',
        ];
    }
}
