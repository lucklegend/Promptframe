<?php

namespace App\Models;

use App\Generations\GenerationStatus;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One image a user asked for, and what it cost.
 *
 * Status changes go through the methods below rather than plain attribute
 * writes. Each one re-reads the row under a lock, so the submit job and a
 * webhook arriving at the same moment can't overwrite each other.
 */
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory;

    protected $fillable = [
        'idempotency_key',
        'status',
        'prompt',
        'size',
        'quality',
        'reference_images',
        'estimated_min_charge',
        'estimated_max_charge',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'usage' => 'array',
            'estimated_min_charge' => 'integer',
            'estimated_max_charge' => 'integer',
            'provider_cost' => 'integer',
            'charge' => 'integer',
            'image_url_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Pixelforge accepted the generation. Its id and rates are kept even if a
     * webhook already moved the status on, since the webhook may have
     * arrived before this response did.
     *
     * @param  array<string, string>  $rates
     */
    public function recordAccepted(string $pixelforgeId, array $rates, GenerationStatus $reported): void
    {
        $this->updateLocked(function (self $generation) use ($pixelforgeId, $rates, $reported): void {
            $generation->pixelforge_id ??= $pixelforgeId;
            $generation->rates ??= $rates;
            $generation->submitted_at ??= now();

            // A replayed request can report a later status than queued;
            // succeeded and failed carry results, so the webhook sets those.
            if (! $reported->isFinal() && $generation->status->canMoveTo($reported)) {
                $generation->status = $reported;
            }
        });
    }

    /**
     * The generation won't produce an image. Failed generations are free.
     */
    public function markFailed(string $code, string $message): void
    {
        $this->updateLocked(function (self $generation) use ($code, $message): void {
            if (! $generation->status->canMoveTo(GenerationStatus::Failed)) {
                return;
            }

            $generation->status = GenerationStatus::Failed;
            $generation->error_code = $code;
            $generation->error_message = $message;
            $generation->provider_cost = 0;
            $generation->charge = 0;
            $generation->completed_at = now();
        });
    }

    /**
     * @param  callable(self): void  $change
     */
    private function updateLocked(callable $change): void
    {
        DB::transaction(function () use ($change): void {
            $fresh = self::query()->lockForUpdate()->findOrFail($this->getKey());
            $change($fresh);
            $fresh->save();
            $this->setRawAttributes($fresh->getAttributes(), true);
        });
    }
}
