<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $reference
 * @property string $application_reference
 * @property int $amount_minor
 * @property string $currency
 * @property string $status
 */
class SimulatedPayment extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }
}
