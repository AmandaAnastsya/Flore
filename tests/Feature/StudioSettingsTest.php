<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\DeliverySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function valid(array $override = []): array
    {
        return array_merge([
            'store__name' => 'Floré', 'store__phone' => '081299990000', 'store__email' => 'halo@flore.id',
            'studio__address' => 'Jl. Senopati No. 8, Jakarta Selatan',
            'payment__bank' => 'Mandiri', 'payment__account_number' => '123-000-777', 'payment__account_name' => 'Floré Studio', 'payment__timeout_hours' => 12,
            'delivery__lead_hours' => 4, 'delivery__max_days' => 45,
            'reminders__time' => '09:30', 'inventory__shelf_life_days' => 6,
        ], $override);
    }

    public function test_settings_are_admin_only(): void
    {
        $this->get('/admin/settings')->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/admin/settings')->assertForbidden();
        $this->actingAs(User::factory()->create())->put('/admin/settings', $this->valid())->assertForbidden();
        $this->assertSame(0, Setting::count());
    }

    public function test_admin_saves_settings_and_they_apply_everywhere(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Studio Settings')->assertSee('value="BCA"', false);

        $this->put('/admin/settings', $this->valid())->assertRedirect('/admin/settings')->assertSessionHas('success');

        $this->assertSame('Mandiri', config('flore.payment.bank'));
        $this->assertSame(4, DeliverySchedule::leadHours());
        $this->assertSame(45, DeliverySchedule::maxDays());
        $this->assertSame('09:30', config('flore.reminders.time'));
        $this->assertSame('Mandiri', Setting::find('payment.bank')->value);

        // toko & halaman customer memakai nilai baru
        $this->get('/')->assertSee('Transfer bank Mandiri')->assertSee('wa.me/6281299990000', false)->assertSee('halo@flore.id');
        $this->get('/admin/logistics')->assertOk();
    }

    public function test_validation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->put('/admin/settings', $this->valid([
            'store__phone' => '12345', 'payment__account_number' => 'abc', 'delivery__lead_hours' => 0,
            'reminders__time' => '25:00', 'studio__address' => '',
        ]))->assertSessionHasErrors(['store__phone', 'payment__account_number', 'delivery__lead_hours', 'reminders__time', 'studio__address']);

        $this->assertSame(0, Setting::count());
        $this->assertSame(DeliverySchedule::LEAD_HOURS, DeliverySchedule::leadHours());
    }

    public function test_checkout_respects_max_days_setting(): void
    {
        config(['flore.delivery.max_days' => 7]);
        $this->assertSame(['delivery_date', 'Pengiriman maksimal 7 hari ke depan.'],
            DeliverySchedule::problem(now()->addDays(10)->toDateString(), 'morning', collect()));
    }
}
