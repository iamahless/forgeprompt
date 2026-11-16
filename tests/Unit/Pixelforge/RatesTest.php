<?php

use App\Pixelforge\Rates;

test('usage is costed at each token type\'s own rate', function () {
    $rates = Rates::fromArray([
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ]);

    $cost = $rates->cost([
        'input_text_tokens' => 12,
        'input_image_tokens' => 765,
        'output_tokens' => 6450,
        'reasoning_tokens' => 3184,
        'total_tokens' => 10411,
    ]);

    // 0.000060 + 0.007650 + 0.258000 + 0.039800
    expect($cost)->toBe(305_510_000);
});

test('one token at one dollar per million costs a thousand nanodollars', function () {
    $rates = Rates::fromArray([
        'input_text_tokens' => '1.00',
        'input_image_tokens' => '0.00',
        'output_tokens' => '0.00',
        'reasoning_tokens' => '0.00',
    ]);

    expect($rates->cost([
        'input_text_tokens' => 1,
        'input_image_tokens' => 0,
        'output_tokens' => 0,
        'reasoning_tokens' => 0,
    ]))->toBe(1_000);
});

test('a rate that would need a fraction of a nanodollar per token is rejected', function () {
    Rates::fromArray([
        'input_text_tokens' => '5.0005',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ]);
})->throws(InvalidArgumentException::class);
