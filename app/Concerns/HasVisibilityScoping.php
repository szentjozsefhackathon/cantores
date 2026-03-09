<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasVisibilityScoping
{
    public function scopePublic(Builder $query): Builder
    {
        $table = $this->getTable();

        return $query->where("{$table}.{$this->getVisibilityField()}", $this->getVisibilityPublicValue());
    }

    public function scopePrivate(Builder $query): Builder
    {
        $table = $this->getTable();

        return $query->where("{$table}.{$this->getVisibilityField()}", ! $this->getVisibilityPublicValue());
    }

    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $userId = $user?->id;
        $table = $this->getTable();

        if (! $userId) {
            return $this->scopePublic($query);
        }

        return $query->where(function (Builder $q) use ($userId, $table) {
            $q->where("{$table}.{$this->getVisibilityField()}", $this->getVisibilityPublicValue())
                ->orWhere("{$table}.{$this->getOwnerField()}", $userId);
        });
    }

    public function scopeWithVisibleRelation(Builder $query, string $relation, ?User $user = null): Builder
    {
        return $query->whereHas($relation, function (Builder $q) use ($user) {
            /** @var Model|HasVisibilityScoping $model */
            $model = $q->getModel();

            if (in_array(HasVisibilityScoping::class, class_uses_recursive($model))) {
                $model->scopeVisibleTo($q, $user);
            }
        });
    }

    public function isVisibleTo(?User $user = null): bool
    {
        $isPublic = $this->getAttribute($this->getVisibilityField()) === $this->getVisibilityPublicValue();

        if ($isPublic) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $this->getAttribute($this->getOwnerField()) === $user->id;
    }

    public function getVisibilityField(): string
    {
        return 'is_private';
    }

    public function getOwnerField(): string
    {
        return 'user_id';
    }

    public function getVisibilityPublicValue(): bool
    {
        return false;
    }
}
