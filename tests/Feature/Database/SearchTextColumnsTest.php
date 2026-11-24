<?php

declare(strict_types=1);

use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Places\Models\Place;
use App\Domains\RealEstate\Models\Property;
use App\Domains\Requests\Models\ServiceRequest;
use App\Domains\Services\Models\Provider;
use Illuminate\Database\Eloquent\Model;

it('fills search_text with what residents search in each kind of content', function (Closure $create, string $searchText): void {
    /** @var Model $model */
    $model = $create();

    expect($model::query()->findOrFail($model->getKey())->getAttribute('search_text'))->toBe($searchText);
})->with([
    'a property: its title and description' => [
        fn (): Property => Property::factory()->create(['title' => 'شقّة للإيجار', 'description' => 'قريبة من الجامعة']),
        'شقه للايجار قريبه من الجامعه',
    ],
    'a provider: its name and bio' => [
        fn (): Provider => Provider::factory()->create(['display_name' => 'الأسطى محمود', 'bio' => 'سبّاك، خبرة ١٥ سنة']),
        'الاسطي محمود سباك، خبره 15 سنه',
    ],
    'a place: its name and description' => [
        fn (): Place => Place::factory()->create(['name' => 'صيدليّة الشفاء', 'description' => null]),
        'صيدليه الشفاء',
    ],
    'a used item: its title and description' => [
        fn (): UsedItem => UsedItem::factory()->create(['title' => 'غسّالة توشيبا', 'description' => 'حالة ممتازة']),
        'غساله توشيبا حاله ممتازه',
    ],
    'a request: its body' => [
        // Hamza on yeh and alef maqsura both become yeh, as the search term will.
        fn (): ServiceRequest => ServiceRequest::factory()->create(['body' => 'محتاج كهربائى ضرورى']),
        'محتاج كهربايي ضروري',
    ],
]);
