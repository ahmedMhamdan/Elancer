<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property-read Project $project
 */
class ProjectAttachment extends Model
{
    /** Q11 and plan 023: what one brief may carry. */
    public const LIMIT = 3;

    public const KILOBYTES = 5120;

    /**
     * A name's ending and the type its content must be detected as. Plain text is detected under
     * several text types (a note with commas reads as text/csv), so any text type is taken for .txt.
     */
    public const TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf', 'txt' => 'text/'];

    public $timestamps = false;

    protected $guarded = ['*'];

    // The stored location never leaves the server.
    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
