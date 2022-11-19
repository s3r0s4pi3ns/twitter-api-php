<?php

namespace Domain\Shared\Models\QueryBuilders;

use Domain\Shared\Models\User;
use Illuminate\Database\Eloquent\Builder;

class UserQueryBuilder extends Builder
{
    public function withFollowersCount(): self
    {
        return User::withCount([
            'followers',
            'followers as followers_count' => function (Builder $query) {
                $query->wherePivot('accepted', true);
            },
        ]);
    }

    public function withFollowingCount(): self
    {
        return User::withCount([
            'following',
            'following as following_count' => function (Builder $query) {
                $query->wherePivot('accepted', true);
            },
        ]);
    }

    public function withPendingFollowersRequest(): self
    {
        return User::with([
            'followers' => function (Builder $query) {
                $query->wherePivot('accepted', false);
            },
        ]);
    }
}
