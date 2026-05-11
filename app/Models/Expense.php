<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Facades\Date;
use Carbon\Carbon;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $title
 * @property float $amount
 * @property int $user_id
 * @property string $status
 * @property string $type
 * @property string $origin_type
 * @property string $occurrence_type
 * @property Carbon|null $payment_date
 * @property Carbon|null $purchase_date
 * @property Carbon|null $due_date
 * @property int|null $category_id
 * @property int|null $source_id
 * @property int|null $credit_card_statement_id
 * @property string|null $installment_group_id
 * @property int|null $installment_number
 * @property int|null $installment_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Category|null $category
 * @property-read Source|null $source
 * @property-read CreditCardStatement|null $creditCardStatement
 */
#[Fillable([
    'title',
    'amount',
    'user_id',
    'status',
    'type',
    'origin_type',
    'occurrence_type',
    'payment_date',
    'purchase_date',
    'due_date',
    'category_id',
    'source_id',
    'credit_card_statement_id',
    'installment_group_id',
    'installment_number',
    'installment_total',
])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    public const ORIGIN_DIRECT = 'direct';

    public const ORIGIN_CREDIT_CARD = 'credit_card';

    public const OCCURRENCE_DIRECT = 'direct';

    public const OCCURRENCE_PURCHASE = 'purchase';

    public const OCCURRENCE_INVOICE_PAYMENT = 'invoice_payment';

    /** @var array<string, string> */
    protected $casts = [
        'payment_date' => 'datetime',
        'purchase_date' => 'date',
        'due_date' => 'date',
        'installment_number' => 'integer',
        'installment_total' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function getAmountAttribute(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        return ((float) $value) / 100;
    }

    protected function setAmountAttribute(mixed $value): void
    {
        if (is_int($value)) {
            $this->attributes['amount'] = (int) $value;

            return;
        }

        $stringValue = is_string($value) ? $value : (is_float($value) ? (string) $value : '');

        if ($stringValue !== '' && is_numeric($stringValue) && ! str_contains($stringValue, '.') && ! str_contains($stringValue, ',')) {
            $this->attributes['amount'] = (int) $stringValue;

            return;
        }

        $clean = preg_replace('/[^\d.,]/', '', $stringValue) ?? '';

        if (str_contains((string) $clean, ',')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }

        $this->attributes['amount'] = (int) round(((float) $clean) * 100);
    }

    protected static function booted(): void
    {
        static::saving(function (self $expense): void {
            if ($expense->payment_date === null && $expense->status === 'paid') {
                $expense->payment_date = Date::now();
            }
        });
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /** @return BelongsTo<CreditCardStatement, $this> */
    public function creditCardStatement(): BelongsTo
    {
        return $this->belongsTo(CreditCardStatement::class, 'credit_card_statement_id');
    }
}
