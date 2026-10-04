<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $contract_submission_id
 * @property string $name
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property-read ContractSubmission $submission
 */
class ContractSubmissionFile extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /** @return BelongsTo<ContractSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(ContractSubmission::class, 'contract_submission_id');
    }
}
