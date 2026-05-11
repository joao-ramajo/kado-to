<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $type
 * @property string $color
 * @property bool $is_default
 * @property bool $allow_negative
 * @property int|null $credit_limit
 * @property int|null $statement_closing_day
 * @property int|null $statement_due_day
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CreditCardStatement> $statements
 */
class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory;

    public const TYPE_CASH_LIKE = 'cash_like';

    public const TYPE_CREDIT_CARD = 'credit_card';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'color',
        'is_default',
        'allow_negative',
        'credit_limit',
        'statement_closing_day',
        'statement_due_day',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_default' => 'boolean',
        'allow_negative' => 'boolean',
        'credit_limit' => 'integer',
        'statement_closing_day' => 'integer',
        'statement_due_day' => 'integer',
    ];

    public function statements(): HasMany
    {
        return $this->hasMany(CreditCardStatement::class);
    }

    public function isCreditCard(): bool
    {
        return $this->type === self::TYPE_CREDIT_CARD;
    }
}
