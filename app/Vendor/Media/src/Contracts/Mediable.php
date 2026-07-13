<?php

namespace Local\Media\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Implemented by models that carry media. The Traits\HasMedia trait provides
 * the implementation; this contract is what MediaService (and any consumer)
 * types against, so "a model with media" is a checkable type rather than a
 * bare Model that happens to have a media() method at runtime.
 *
 *     class Post extends Model implements Mediable
 *     {
 *         use HasMedia;
 *     }
 *
 * @phpstan-require-extends Model
 */
interface Mediable
{
    public function media(): MorphMany;
}
