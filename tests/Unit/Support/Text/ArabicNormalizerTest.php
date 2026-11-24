<?php

declare(strict_types=1);

use App\Support\Text\ArabicNormalizer;

it('makes the ways people spell the same word equal', function (string $typed, string $other, string $normalized): void {
    $normalizer = new ArabicNormalizer;

    expect($normalizer->normalize($typed))->toBe($normalized)
        ->and($normalizer->normalize($other))->toBe($normalized);
})->with([
    'shadda and taa marbuta' => ['صيدليّة', 'صيدليه', 'صيدليه'],
    'alef with hamza below' => ['إيجار', 'ايجار', 'ايجار'],
    'taa marbuta and Arabic-Indic digits' => ['شقة ٣ غرف', 'شقه 3 غرف', 'شقه 3 غرف'],
    'alef maqsura' => ['مستشفى', 'مستشفي', 'مستشفي'],
]);

it('folds every alef form into a bare alef', function (string $alef): void {
    expect((new ArabicNormalizer)->normalize($alef.'حمد'))->toBe('احمد');
})->with(['أ', 'إ', 'آ', 'ٱ', 'ا']);

it('maps the letters that people type interchangeably', function (string $input, string $expected): void {
    expect((new ArabicNormalizer)->normalize($input))->toBe($expected);
})->with([
    'taa marbuta to haa' => ['مدرسة', 'مدرسه'],
    'alef maqsura to yaa' => ['على', 'علي'],
    'hamza on waw to waw' => ['مؤجر', 'موجر'],
    'hamza on yaa to yaa' => ['بيئة', 'بييه'],
]);

it('removes diacritics, the dagger alef and tatweel', function (string $input, string $expected): void {
    expect((new ArabicNormalizer)->normalize($input))->toBe($expected);
})->with([
    'fatha, damma, kasra and sukun' => ['مَدْرَسِةُ', 'مدرسه'],
    'shadda and tanween' => ['سبّاكٌ', 'سباك'],
    'tanween fath and kasr' => ['شكرًا جدٍّ', 'شكرا جد'],
    'dagger alef' => ['هٰذا', 'هذا'],
    'tatweel' => ['مـــطـعـم', 'مطعم'],
    'combining hamza and madda' => ["\u{0627}\u{0654}\u{0645}\u{0627}\u{0653}\u{0646}", 'امان'],
]);

it('turns Arabic-Indic and Persian digits into Western digits', function (string $input, string $expected): void {
    expect((new ArabicNormalizer)->normalize($input))->toBe($expected);
})->with([
    'Arabic-Indic' => ['٠١٢٣٤٥٦٧٨٩', '0123456789'],
    'Persian' => ['۰۱۲۳۴۵۶۷۸۹', '0123456789'],
    'mixed with Western digits' => ['شقة ١٢0 متر', 'شقه 120 متر'],
]);

it('collapses whitespace, trims it and drops invisible marks', function (string $input, string $expected): void {
    expect((new ArabicNormalizer)->normalize($input))->toBe($expected);
})->with([
    'spaces, tabs and newlines' => ["  شقة \t للبيع\n\nفي   السادات  ", 'شقه للبيع في السادات'],
    'no-break space' => ["شقة\u{00A0}للبيع", 'شقه للبيع'],
    'right-to-left and zero-width marks' => ["\u{200F}شقة\u{200C}\u{200D} للبيع\u{FEFF}", 'شقه للبيع'],
]);

it('lowercases Latin letters and leaves Arabic alone', function (): void {
    expect((new ArabicNormalizer)->normalize('Pharmacy صيدلية EL-SADAT'))->toBe('pharmacy صيدليه el-sadat');
});

it('returns already normalized text unchanged', function (string $text): void {
    $normalizer = new ArabicNormalizer;

    expect($normalizer->normalize($text))->toBe($text)
        ->and($normalizer->normalize($normalizer->normalize($text)))->toBe($text);
})->with(['شقه 3 غرف', 'pharmacy', '']);
