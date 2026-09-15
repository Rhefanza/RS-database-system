<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_page(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_admin_can_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('admin.index'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_invalid_password_does_not_authenticate_user(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_account_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'account_status' => 'INACTIVE',
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_officer_is_sent_to_queues_and_cannot_access_admin_master_data(): void
    {
        $officer = User::factory()->create([
            'password' => Hash::make('password'),
            'role' => 'OFFICER',
            'account_status' => 'ACTIVE',
        ]);

        $this->post('/login', ['email' => $officer->email, 'password' => 'password'])
            ->assertRedirect(route('admin.queues.index'));
        $this->get(route('admin.index'))->assertForbidden();
        $this->get(route('admin.master.index'))->assertForbidden();
        $this->get(route('admin.queues.index'))->assertOk();
    }
}
