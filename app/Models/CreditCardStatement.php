<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $source_id
 * @property Carbon $reference_month
 * @property Carbon $closing_at
 * @property Carbon $due_at
 * @property string $status
 * @property int $total_amount
 * @property Carbon|null $paid_at
 * @property int|null $payment_source_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Source $source
 * @property-read Source|null $paymentSource
 * @property-read Collection<int, Expense> $expenses
 */
#[Fillable([
    'source_id',
    'reference_month',
    'closing_at',
    'due_at',
    'status',
    'total_amount',
    'paid_at',
    'payment_source_id',
])]
class CreditCardStatement extends Model
{
    /** @use HasFactory<\Database\Factories\CreditCardStatementFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_PAID = 'paid';

    /** @var array<string, string> */
    protected $casts = [
        'reference_month' => 'date',
        'closing_at' => 'date',
        'due_at' => 'date',
        'paid_at' => 'datetime',
        'total_amount' => 'integer',
    ];

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /** @return BelongsTo<Source, $this> */
    public function paymentSource(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'payment_source_id');
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'credit_card_statement_id');
    }
}
