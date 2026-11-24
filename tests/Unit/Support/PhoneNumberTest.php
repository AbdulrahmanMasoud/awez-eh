<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Exceptions\InvalidPhoneNumber;
use App\Support\PhoneNumber;

it('accepts the common ways an Egyptian mobile number is typed', function (string $typed): void {
    $phone = PhoneNumber::from($typed);

    expect($phone->e164())->toBe('+201012345678')
        ->and($phone->national())->toBe('01012345678');
})->with([
    'national' => '01012345678',
    'E.164' => '+201012345678',
    'international with 00' => '00201012345678',
    'country code without plus' => '201012345678',
    'spaces' => '010 1234 5678',
    'dashes' => '010-1234-5678',
    'spaces after the country code' => '+20 10 1234 5678',
    'surrounding whitespace' => "  01012345678\n",
    'no-break spaces' => "010\u{00A0}1234\u{00A0}5678",
    'Arabic-Indic digits' => '٠١٠١٢٣٤٥٦٧٨',
    'Arabic-Indic digits with plus' => '+٢٠١٠١٢٣٤٥٦٧٨',
    'Persian digits' => '۰۱۰۱۲۳۴۵۶۷۸',
    'mixed digits' => '010١٢٣٤٥٦٧٨',
    'right-to-left marks pasted from a chat' => "\u{200F}01012345678\u{200F}",
]);

it('accepts every Egyptian mobile operator prefix', function (string $prefix): void {
    expect(PhoneNumber::from($prefix.'12345678')->national())->toBe($prefix.'12345678');
})->with(['010', '011', '012', '015']);

it('rejects anything that is not an Egyptian mobile number', function (string $typed): void {
    expect(fn (): PhoneNumber => PhoneNumber::from($typed))->toThrow(InvalidPhoneNumber::class);
})->with([
    'empty' => '',
    'letters' => 'my number',
    'too short' => '0101234567',
    'too long' => '010123456789',
    'unknown prefix 013' => '01312345678',
    'unknown prefix 014' => '01412345678',
    'unknown prefix 016' => '01612345678',
    'unknown prefix 019' => '01912345678',
    'Cairo landline' => '0223456789',
    'country code with the trunk zero' => '+2001012345678',
    'no leading zero' => '1012345678',
    'another country' => '+441012345678',
    'a letter inside' => '0101234567a',
    'parentheses' => '(010) 1234 5678',
    'dots' => '010.1234.5678',
    'two numbers' => '01012345678 01112345678',
    'plus in the middle' => '0+1012345678',
]);

it('reports the invalid number as a 422 domain error without the number', function (): void {
    try {
        PhoneNumber::from('0131234567');
        $this->fail('The number was accepted.');
    } catch (InvalidPhoneNumber $exception) {
        expect($exception)->toBeInstanceOf(DomainException::class)
            ->and($exception->errorCode)->toBe('invalid_phone_number')
            ->and($exception->translationKey)->toBe('errors.invalid_phone_number')
            ->and($exception->status)->toBe(422)
            ->and($exception->getMessage())->not->toContain('0131234567')
            ->and(json_encode($exception->context()))->not->toContain('0131234567');
    }
});

it('masks all but the first and last three digits', function (): void {
    expect(PhoneNumber::from('01512345678')->mask())->toBe('015*****678');
});

it('is masked whenever it is turned into a string or dumped', function (): void {
    $phone = PhoneNumber::from('+201012345678');

    expect((string) $phone)->toBe('010*****678')
        ->and("Calling {$phone}")->toBe('Calling 010*****678')
        ->and(print_r($phone, true))->not->toContain('01012345678')->not->toContain('1012345678')
        ->and(json_encode($phone))->not->toContain('1012345678');
});

it('compares by number, whatever the way it was typed', function (): void {
    expect(PhoneNumber::from('+20 10 1234 5678')->equals(PhoneNumber::from('٠١٠١٢٣٤٥٦٧٨')))->toBeTrue()
        ->and(PhoneNumber::from('01012345678')->equals(PhoneNumber::from('01112345678')))->toBeFalse();
});

it('accepts an Egyptian landline, typed in the usual ways, when landlines are allowed', function (string $typed): void {
    $phone = PhoneNumber::fromMobileOrLandline($typed);

    expect($phone->e164())->toBe('+20482345678')
        ->and($phone->national())->toBe('0482345678')
        ->and($phone->isMobile())->toBeFalse();
})->with([
    'national, Menoufia (Sadat City)' => '0482345678',
    'spaces' => '048 234 5678',
    'a dash after the area code' => '048-2345678',
    'E.164' => '+20482345678',
    'international with 00' => '0020482345678',
    'country code without plus' => '20482345678',
    'Arabic-Indic digits' => '٠٤٨٢٣٤٥٦٧٨',
]);

it('knows each area\'s length: eight digits in Cairo and Giza, seven elsewhere', function (string $typed, string $e164): void {
    expect(PhoneNumber::fromMobileOrLandline($typed)->e164())->toBe($e164);
})->with([
    'Cairo' => ['0223456789', '+20223456789'],
    'Giza' => ['0233456789', '+20233456789'],
    'Alexandria' => ['035123456', '+2035123456'],
    'Benha' => ['0133123456', '+20133123456'],
    'Tanta' => ['0403123456', '+20403123456'],
    'Mansoura' => ['0502234567', '+20502234567'],
    'Aswan' => ['0972345678', '+20972345678'],
]);

it('takes mobiles too where landlines are allowed', function (): void {
    $phone = PhoneNumber::fromMobileOrLandline('01012345678');

    expect($phone->e164())->toBe('+201012345678')
        ->and($phone->isMobile())->toBeTrue()
        ->and($phone->equals(PhoneNumber::from('01012345678')))->toBeTrue();
});

it('keeps landlines out where only a mobile will do', function (): void {
    expect(fn (): PhoneNumber => PhoneNumber::from('0482345678'))->toThrow(InvalidPhoneNumber::class);
});

it('rejects what is not an Egyptian landline or mobile', function (string $typed): void {
    expect(fn (): PhoneNumber => PhoneNumber::fromMobileOrLandline($typed))->toThrow(InvalidPhoneNumber::class);
})->with([
    'an area code nobody has (041)' => '0412345678',
    'Menoufia with six digits' => '048234567',
    'Menoufia with eight digits' => '04823456789',
    'Cairo with seven digits' => '022345678',
    'Alexandria with eight digits' => '0351234567',
    'a subscriber number starting with zero' => '0480345678',
    // 015 is a WE mobile: ten digits is a mobile number missing one, not the old 10th of Ramadan code.
    'the old 10th of Ramadan code' => '0153123456',
    'a five-digit hotline' => '19011',
    'parentheses around the area code' => '(048) 2345678',
    'no trunk zero' => '482345678',
]);

it('masks a landline like a mobile, the first and last three digits shown', function (string $typed, string $masked): void {
    expect(PhoneNumber::fromMobileOrLandline($typed)->mask())->toBe($masked)
        ->and((string) PhoneNumber::fromMobileOrLandline($typed))->toBe($masked);
})->with([
    'Menoufia' => ['0482345678', '048****678'],
    'Cairo' => ['0223456789', '022****789'],
    'Alexandria' => ['035123456', '035***456'],
]);
