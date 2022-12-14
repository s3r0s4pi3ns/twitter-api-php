<?php

namespace Tests\Feature\Tweets\API\Controllers;

use Domain\Shared\Models\User;
use Domain\Tweets\Events\TweetCreatedEvent;
use Domain\Tweets\Models\Tweet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\delete;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class)->group('api');

it('should retrieve the users that likes the selected tweet paginated', function () {
    actingAsApiUser();

    $tweet = Tweet::factory()->has(User::factory()->count(5), 'likes')->create();

    getJson(route('api.tweet-likes', ['tweet' => $tweet->id]))
        ->assertOk()
        ->assertJson([
            'data' => $tweet->likes->pluck('id')->map(fn ($id) => compact('id'))->all(),
        ])->assertPaginated(
            route('api.tweet-likes', ['tweet' => $tweet->id]),
            1,
            1,
            1,
            $tweet->likes()->count()
        );
});

it('should retrieve the users that likes the selected tweet paginated even when is empty', function () {
    actingAsApiUser();

    $tweet = Tweet::factory()->create();

    getJson(route('api.tweet-likes', ['tweet' => $tweet->id]))
        ->assertOk()
        ->assertJson(['data' => []])
        ->assertPaginated(route('api.tweet-likes', ['tweet' => $tweet->id]), 1, 1, 1, 0);
});

it('should throw validation errors when data is wrong on tweet creation', function () {
    actingAsApiUser();
    assertDatabaseCount('tweets', 0);

    postJson(route('api.process-tweet'), [
        'text' => fake()->realText(170),
        'reply_settings' => 'TO THE WORLD',
        'visible_for' => ['superman id', 'spiderman id'],
    ])->assertUnprocessable()
        ->assertInvalid([
            'text' => 'The text must not be greater than 140 characters.',
            'reply_settings' => 'The selected reply settings is invalid.',
            'visible_for.0' => [
                'The selected visible_for.0 is invalid.',
            ],
            'visible_for.1' => [
                'The selected visible_for.1 is invalid.',
            ],
        ]);

    assertDatabaseCount('tweets', 0);
});

it('should create a new tweet succesfully', function () {
    Event::fake([TweetCreatedEvent::class]);

    $user = User::factory()->create();

    actingAsApiUser($user);

    assertDatabaseMissing('tweets', ['author_id' => $user->id]);

    postJson(route('api.process-tweet'), [
        'text' => 'This tweet is not a tweet',
        'lang' => 'en',
        'replying_to' => [],
        'visible_for' => [],
    ])->assertOk();

    Event::assertDispatched(fn (TweetCreatedEvent $event) => $event->tweet->author_id === $user->id);

    assertDatabaseHas('tweets', ['author_id' => $user->id]);
});

it('should update a tweet succesfully if exists', function () {
    $user = User::factory()->create();

    actingAsApiUser($user);

    $tweet = Tweet::factory()->create(['author_id' => $user->id]);

    assertDatabaseCount('tweets', 1);

    postJson(route('api.process-tweet'), [
        'id' => $tweet->id,
        'text' => 'This tweet is not a tweet',
        'lang' => 'en',
        'replying_to' => [],
        'visible_for' => [],
    ])->assertOk();

    assertDatabaseCount('tweets', 2);
});

it('should toggle the like for the selected tweet from specific user', function () {
    $user = User::factory()->create();

    actingAsApiUser($user);

    $tweet = Tweet::factory()->create();

    assertFalse($tweet->likes->contains($user));

    putJson(route('api.tweet-like', ['tweet' => $tweet->id]))
        ->assertOk();

    assertTrue($tweet->fresh('likes')->likes->contains($user));

    putJson(route('api.tweet-like', ['tweet' => $tweet->id]))
        ->assertOk();

    assertFalse($tweet->fresh('likes')->likes->contains($user));
});

it('should delete the like for the selected tweet', function () {
    $user = User::factory()->create();

    $tweet = Tweet::factory()->create();
    $tweet->likes()->attach($user->id);

    actingAsApiUser($user);

    delete(route('api.delete-tweet-like', ['tweet' => $tweet->id]))
        ->assertNoContent();

    assertFalse($tweet->fresh('likes')->likes->contains($user));
});
