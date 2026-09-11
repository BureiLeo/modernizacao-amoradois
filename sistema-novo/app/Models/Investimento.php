<?php

namespace App\Models;

use Database\Factories\InvestimentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Investimento extends Model
{
    /** @use HasFactory<InvestimentoFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor' => 'decimal:2',
            'quitado' => 'boolean',
        ];
    }
}
