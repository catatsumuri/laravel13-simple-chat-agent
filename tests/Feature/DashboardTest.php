<?php

namespace Tests\Feature;

use App\Models\Scenario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        Scenario::query()->create([
            'title' => '初回提案の切り返し練習',
            'company_name' => 'サンプル商事',
            'industry' => '商社',
            'customer_persona' => '購買担当',
            'difficulty' => '初級',
            'sort_order' => 1,
            'summary' => '価格以外の価値を伝える練習用シナリオ。',
            'goal' => '次回商談設定を獲得する。',
        ]);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk()
            ->assertSee('初回提案の切り返し練習');
    }
}
