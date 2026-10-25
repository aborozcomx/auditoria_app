<?php

namespace App\Models;

use Database\Factories\UmaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Uma extends Model
{
    /** @use HasFactory<UmaFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'anio',
        'saldo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'saldo' => 'decimal:2',
        ];
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->saldo;
    }
}
