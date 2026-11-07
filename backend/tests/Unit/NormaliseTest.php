<?php

use App\Support\Names;
use App\Support\Phone;

it('normalises phones to E.164', function (?string $raw, ?string $want) {
    expect(Phone::normalize($raw))->toBe($want);
})->with([
    ['+971501234567', '+971501234567'], ['+971 (50) 123-4567', '+971501234567'], ['00971501234567', '+971501234567'],
    ['971501234567', '+971501234567'], ['050 123 4567', '+971501234567'], ['501234567', '+971501234567'],
    ['04 223 1180', '+97142231180'], ['+971 050 123 4567', '+971501234567'], ['04 2?? 1180', null],
    ['1234567', null], ['', null], [null, null], ['+966 55 123 4567', '+966551234567'], ['0044 20 7946 0958', '+442079460958'],
]);

it('builds name keys', function () {
    expect(Names::key('Kérastase'))->toBe('kerastase')
        ->and(Names::key('&honey'))->toBe('and honey')
        ->and(Names::squash('250 ML'))->toBe('250ml')
        ->and(Names::key('كيراستاس'))->toBe('كيراستاس')
        ->and(Names::company('Gulf Beauty Trading L.L.C.'))->toBe('gulf beauty')
        ->and(Names::company('Star Perfumes FZE'))->toBe('star perfumes');
});

it('compares areas and company similarity', function () {
    expect(Names::sameArea('Naif', 'Naif, Deira'))->toBeTrue()
        ->and(Names::sameArea('Deira, Dubai', 'Al Qusais'))->toBeFalse()
        ->and(Names::similarity('Al Noor Cosmetics', 'Al-Noor Cosmetics LLC'))->toBe(1.0)
        ->and(Names::similarity('Al Noor Cosmetics', 'Alnoor Cosmetic'))->toBeGreaterThan(0.85)
        ->and(Names::similarity('Al Noor Cosmetics', 'Gulf Beauty'))->toBeLessThan(0.5);
});
