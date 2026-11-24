<?php

declare(strict_types=1);

use App\Support\Text\ArabicNormalizer;
use App\Support\Text\HasSearchText;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

#[Unguarded]
#[Table(name: 'search_text_probes')]
#[WithoutTimestamps]
final class SearchTextProbe extends Model
{
    use HasSearchText;

    protected function searchableAttributes(): array
    {
        return ['name', 'description'];
    }
}

beforeEach(function (): void {
    Schema::create('search_text_probes', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->string('phone')->nullable();
        $table->text('search_text');
    });
});

/**
 * Whether a resident's search term finds the probe, as search on search_text will match it:
 * the normalized term against the best-matching words of the text (DECISIONS.md).
 */
function searchFinds(string $term): bool
{
    return SearchTextProbe::query()
        ->whereRaw('strict_word_similarity(?, search_text) >= ?', [
            new ArabicNormalizer()->normalize($term),
            config('awez.search.similarity_threshold'),
        ])
        ->exists();
}

it('fills search_text with the searchable attributes, normalized, when a row is created', function (): void {
    $probe = SearchTextProbe::query()->create([
        'name' => 'صيدليّة الشفاء',
        'description' => "أدوية  ومستلزمات\nطبية",
        'phone' => '01012345678',
    ]);

    expect($probe->search_text)->toBe('صيدليه الشفاء ادويه ومستلزمات طبيه')
        ->and(SearchTextProbe::query()->findOrFail($probe->id)->search_text)->toBe('صيدليه الشفاء ادويه ومستلزمات طبيه');
});

it('leaves out a searchable attribute that is empty', function (): void {
    $probe = SearchTextProbe::query()->create(['name' => 'صيدليّة الشفاء', 'description' => null]);

    expect($probe->search_text)->toBe('صيدليه الشفاء');
});

it('refreshes search_text when a searchable attribute changes', function (): void {
    $probe = SearchTextProbe::query()->create(['name' => 'صيدليّة الشفاء']);

    $probe->update(['description' => 'مفتوحة ٢٤ ساعة']);

    expect(SearchTextProbe::query()->findOrFail($probe->id)->search_text)->toBe('صيدليه الشفاء مفتوحه 24 ساعه');
});

it('finds صيدليّة when a resident types صيدليه, or a typo of it', function (string $term): void {
    SearchTextProbe::query()->create(['name' => 'صيدليّة الشفاء', 'description' => 'أدوية ومستلزمات طبية وتوصيل للمنازل']);

    expect(searchFinds($term))->toBeTrue();
})->with([
    'without the shadda, with taa marbuta as haa' => 'صيدليه',
    'a missing letter' => 'صيدلة',
]);

it('does not find it for an unrelated word', function (string $term): void {
    SearchTextProbe::query()->create(['name' => 'صيدليّة الشفاء', 'description' => 'أدوية ومستلزمات طبية وتوصيل للمنازل']);

    expect(searchFinds($term))->toBeFalse();
})->with(['مطعم', 'سباك', 'شقة']);
