<?php

namespace Tests\Feature;

use App\Filament\Resources\MediaAssetResource\Pages\ManageMediaAssets;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MediaAssetAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_record_can_be_deleted_and_restored_without_removing_the_linked_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('library/catalogue.pdf', 'catalogue contents');
        $asset = MediaAsset::create(['name' => 'Каталог', 'file' => 'library/catalogue.pdf']);
        $this->actingAs(User::factory()->create(['role' => 'editor']));

        Livewire::test(ManageMediaAssets::class)
            ->assertCanSeeTableRecords([$asset])
            ->callTableAction('delete', $asset)
            ->assertHasNoTableActionErrors()
            ->assertCanNotSeeTableRecords([$asset])
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$asset]);

        $this->assertSoftDeleted($asset);
        Storage::disk('public')->assertExists($asset->file);

        Livewire::test(ManageMediaAssets::class)
            ->filterTable('trashed', false)
            ->callTableAction('restore', $asset)
            ->assertHasNoTableActionErrors()
            ->assertCanNotSeeTableRecords([$asset]);

        $this->assertNotSoftDeleted($asset);
        Storage::disk('public')->assertExists($asset->file);
    }
}
