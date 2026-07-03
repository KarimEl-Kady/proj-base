<?php

namespace Local\Media\Tests;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Local\Media\Services\MediaService;
use Local\Media\Traits\HasMedia;
use Tests\TestCase;

class MediaTestModel extends User
{
    use HasMedia;

    protected $table = 'users';
}

class MediaPackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media.disk' => 'public']);
    }

    protected function makeModel(): MediaTestModel
    {
        return MediaTestModel::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'secret123',
        ]);
    }

    public function test_add_media_stores_file_and_record(): void
    {
        $model = $this->makeModel();

        $media = $model->addMedia(
            UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
            'documents',
            ['alt' => 'Q1 report']
        );

        Storage::disk('public')->assertExists($media->path);
        $this->assertTrue(Str::isUuid($media->uuid));
        $this->assertSame('documents', $media->collection);
        $this->assertSame(['alt' => 'Q1 report'], $media->custom_properties);
        $this->assertSame($media->id, $model->getFirstMedia('documents')?->id);
    }

    public function test_clear_media_removes_records_and_files(): void
    {
        $model = $this->makeModel();
        $media = $model->addMedia(UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'));

        $model->clearMedia();

        Storage::disk('public')->assertMissing($media->path);
        $this->assertCount(0, $model->getMedia());
    }

    public function test_disallowed_mime_type_is_rejected(): void
    {
        $model = $this->makeModel();
        config(['media.allowed_mime_types' => ['image/png']]);

        $this->expectException(InvalidArgumentException::class);

        app(MediaService::class)->store(
            UploadedFile::fake()->create('script.sh', 1, 'text/x-shellscript'),
            $model
        );
    }

    public function test_oversized_file_is_rejected(): void
    {
        $model = $this->makeModel();
        config(['media.max_file_size' => 1]); // 1 KB

        $this->expectException(InvalidArgumentException::class);

        $model->addMedia(UploadedFile::fake()->create('big.pdf', 10, 'application/pdf'));
    }
}
