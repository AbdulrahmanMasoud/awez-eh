<?php

declare(strict_types=1);

namespace App\Support\Database;

use App\Exceptions\AppendOnlyViolation;
use Illuminate\Database\Eloquent\Model;

/**
 * For the model of an append-only table (AppendOnly::enable): saving an existing row or deleting
 * one throws here, so the mistake shows before the database refuses it. Rows record when they
 * were created, never an update.
 *
 * @phpstan-require-extends Model
 */
trait IsAppendOnly
{
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw AppendOnlyViolation::on($this->getTable(), 'updated');
        }

        return parent::save($options);
    }

    public function delete(): never
    {
        throw AppendOnlyViolation::on($this->getTable(), 'deleted');
    }

    public function getUpdatedAtColumn(): ?string
    {
        return null;
    }
}
