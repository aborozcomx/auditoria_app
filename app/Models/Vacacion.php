<?php

namespace App\Models;

use Database\Factories\VacacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vacacion extends Model
{
    /** @use HasFactory<VacacionFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'vacaciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'categoria_id',
        'anios',
        'dias',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'categoria_id' => 'integer',
            'anios' => 'integer',
            'dias' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }
}
