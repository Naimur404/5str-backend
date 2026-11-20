<?php

namespace Tests\Integration;

use App\Models\Attraction;
use App\Models\AttractionGallery;
use App\Support\R2Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * cover_image_url and gallery_count are appended to every serialized
 * attraction. With the gallery eager loaded they must not query per row.
 */
#[Group('integration')]
class AttractionSerializationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAttraction(string $name): Attraction
    {
        return Attraction::create([
            'name' => $name,
            'description' => 'Test attraction',
            'latitude' => 23.7189,
            'longitude' => 90.3882,
            'address' => 'Dhaka',
            'city' => 'Dhaka',
        ]);
    }

    private function addImage(Attraction $attraction, string $file, bool $cover = false): void
    {
        AttractionGallery::create([
            'attraction_id' => $attraction->id,
            'image_url' => "https://images.example.com/{$file}",
            'is_cover' => $cover,
        ]);
    }

    public function test_appended_gallery_attributes_use_eager_loaded_relations(): void
    {
        $withCover = $this->makeAttraction('With cover');
        $this->addImage($withCover, 'a1.jpg');
        $this->addImage($withCover, 'a2.jpg', cover: true);

        $noCover = $this->makeAttraction('No cover');
        $this->addImage($noCover, 'b1.jpg');
        $this->addImage($noCover, 'b2.jpg');

        $empty = $this->makeAttraction('No images');

        // Reference values from the query fallback path (no eager loading).
        $expected = Attraction::orderBy('id')->get()
            ->mapWithKeys(fn ($a) => [$a->id => [$a->gallery_count, $a->cover_image_url]])
            ->all();

        $attractions = Attraction::with(['gallery', 'coverImage'])->orderBy('id')->get();

        DB::enableQueryLog();
        $serialized = $attractions->toArray();
        $galleryQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'attraction_galleries'));
        DB::disableQueryLog();

        $this->assertCount(0, $galleryQueries, 'Serializing eager-loaded attractions should not query attraction_galleries per row.');

        $actual = collect($serialized)->mapWithKeys(fn ($a) => [$a['id'] => [$a['gallery_count'], $a['cover_image_url']]])->all();
        $this->assertSame($expected, $actual);

        $this->assertSame(2, $actual[$withCover->id][0]);
        $this->assertStringEndsWith('a2.jpg', (string) $actual[$withCover->id][1]); // the cover image
        $this->assertStringEndsWith('b1.jpg', (string) $actual[$noCover->id][1]);   // falls back to the first image
        $this->assertSame([0, null], $actual[$empty->id]);
    }

    public function test_thumbnail_existence_check_is_cached(): void
    {
        $disk = Storage::fake(R2Storage::active());

        $attraction = $this->makeAttraction('Thumbs');
        $image = AttractionGallery::create([
            'attraction_id' => $attraction->id,
            'image_url' => 'https://images.example.com/c1.jpg',
            'image_path' => 'attractions/c1.jpg',
        ]);
        $disk->put('attractions/thumbs/c1_thumb.jpg', 'thumb');

        $first = $image->thumbnail_url;
        $this->assertStringContainsString('attractions/thumbs/c1_thumb.jpg', (string) $first);

        // Cached: removing the file does not trigger a new storage check.
        $disk->delete('attractions/thumbs/c1_thumb.jpg');
        $this->assertSame($first, $image->fresh()->thumbnail_url);

        // A missing thumbnail is cached too, and falls back to the full image.
        $other = AttractionGallery::create([
            'attraction_id' => $attraction->id,
            'image_url' => 'https://images.example.com/c2.jpg',
            'image_path' => 'attractions/c2.jpg',
        ]);
        $this->assertSame($other->full_image_url, $other->thumbnail_url);
        $disk->put('attractions/thumbs/c2_thumb.jpg', 'thumb');
        $this->assertSame($other->full_image_url, $other->fresh()->thumbnail_url);
    }
}
