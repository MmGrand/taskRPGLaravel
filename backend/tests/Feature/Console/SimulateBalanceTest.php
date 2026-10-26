<?php

declare(strict_types=1);

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('plays the progression on the catalog and sums it up', function () {
    // Act & Assert
    $this->artisan('balance:simulate', ['--tasks' => 5, '--days' => 120])
        ->expectsOutputToContain('Bought Зелёная бандана (30)')
        ->expectsOutputToContain('Level 10 on day')
        ->expectsOutputToContain('Whole catalog bought by day')
        ->assertSuccessful();
});

it('says when the days are not enough for the catalog', function () {
    // Act & Assert
    $this->artisan('balance:simulate', ['--tasks' => 1, '--days' => 3])
        ->expectsOutputToContain('Catalog not finished in 3 days')
        ->assertSuccessful();
});

it('refuses options it cannot play', function (array $options, string $message) {
    // Act & Assert
    $this->artisan('balance:simulate', $options)
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    'no tasks' => [['--tasks' => 0], 'must be whole numbers'],
    'days as words' => [['--days' => 'week'], 'must be whole numbers'],
    'more days than ten years' => [['--days' => 3651], '--days at most 3650'],
    'unknown difficulty' => [['--difficulty' => 'epic'], 'must be easy, medium or hard'],
]);

it('asks for the catalog when there is none', function () {
    // Arrange
    Item::query()->delete();

    // Act & Assert
    $this->artisan('balance:simulate')
        ->expectsOutputToContain('Run the migrations first')
        ->assertFailed();
});
