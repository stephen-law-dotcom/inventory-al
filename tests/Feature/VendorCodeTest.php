<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\MasterDataTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_codes_are_generated_sequentially_and_cannot_be_changed(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('vendors.store'), [
                'code' => '999',
                'name' => 'Vendor Pertama',
            ])
            ->assertSessionHas('success');

        $firstVendor = Vendor::where('name', 'Vendor Pertama')->firstOrFail();
        $this->assertSame('001', $firstVendor->code);

        $this->actingAs($admin)
            ->put(route('vendors.update', $firstVendor), [
                'code' => '999',
                'name' => 'Vendor Pertama Diperbarui',
            ])
            ->assertSessionHas('success');

        $this->assertSame('001', $firstVendor->fresh()->code);

        $this->actingAs($admin)
            ->delete(route('vendors.destroy', $firstVendor))
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->post(route('vendors.store'), ['name' => 'Vendor Kedua'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vendors', [
            'name' => 'Vendor Kedua',
            'code' => '002',
        ]);
    }

    public function test_vendor_import_generates_codes_for_new_rows_and_preserves_existing_codes(): void
    {
        $transfer = app(MasterDataTransferService::class);

        $transfer->import('vendors', collect([[
            'code' => '',
            'name' => 'Vendor Import',
            'contact_person' => 'Rina',
            'phone' => '081200000000',
            'address' => 'Samarinda',
        ]]));

        $vendor = Vendor::where('name', 'Vendor Import')->firstOrFail();
        $this->assertSame('001', $vendor->code);

        $transfer->import('vendors', collect([[
            'code' => '001',
            'name' => 'Vendor Import Diperbarui',
            'contact_person' => 'Doni',
            'phone' => '081211111111',
            'address' => 'Balikpapan',
        ]]));

        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'code' => '001',
            'name' => 'Vendor Import Diperbarui',
        ]);
        $this->assertSame(1, Vendor::count());
    }

    private function createAdmin(): User
    {
        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin',
            'permissions' => ['*'],
            'can_see_prices' => true,
            'is_admin' => true,
        ]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
