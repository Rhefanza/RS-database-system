<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\HospitalService;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\QueueSnapshot;
use App\Models\Service;
use App\Models\ServiceDesk;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private HospitalService $hospitalService;

    private ServiceDesk $desk;

    private QueueSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'ADMIN', 'account_status' => 'ACTIVE']);
        $hospital = Hospital::factory()->create(['name' => 'RS Uji Antrean']);
        $service = Service::create(['name' => 'Rawat Jalan']);
        $this->hospitalService = HospitalService::create([
            'hospital_id' => $hospital->id,
            'service_id' => $service->id,
            'initial_service_duration' => 10,
            'availability_status' => 'ACTIVE',
        ]);
        $this->desk = ServiceDesk::create([
            'hospital_service_id' => $this->hospitalService->id,
            'name' => 'Loket 1',
            'desk_status' => 'ACTIVE',
        ]);
        $this->session = QueueSession::create([
            'hospital_service_id' => $this->hospitalService->id,
            'opened_by_user_id' => $this->admin->id,
            'session_date' => today(),
            'started_at' => now(),
            'session_status' => 'OPEN',
        ]);
    }

    public function test_public_user_can_take_and_view_a_queue_ticket(): void
    {
        $response = $this->post(route('queues.store', $this->hospitalService));

        $queue = Queue::firstOrFail();
        $response->assertRedirect(route('queues.show', $queue->public_token));
        $this->assertSame(1, $queue->queue_number);
        $this->assertSame('WAITING', $queue->queue_status);
        $this->assertNotNull($queue->public_token);

        $this->get(route('queues.show', $queue->public_token))
            ->assertOk()
            ->assertSee('Q001')
            ->assertSee('RS Uji Antrean')
            ->assertSee('Rawat Jalan');
    }

    public function test_queue_numbers_are_sequential_within_a_session(): void
    {
        $this->post(route('queues.store', $this->hospitalService));
        $this->post(route('queues.store', $this->hospitalService));

        $this->assertSame([1, 2], Queue::orderBy('queue_number')->pluck('queue_number')->all());
    }

    public function test_admin_can_run_a_queue_through_the_complete_workflow(): void
    {
        $this->post(route('queues.store', $this->hospitalService));
        $this->post(route('queues.store', $this->hospitalService));
        $firstQueue = Queue::orderBy('queue_number')->firstOrFail();
        $secondQueue = Queue::orderBy('queue_number')->skip(1)->firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.queues.call-next', $this->session), [
            'service_desk_id' => $this->desk->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('queues', [
            'id' => $firstQueue->id,
            'queue_status' => 'CALLED',
            'service_desk_id' => $this->desk->id,
        ]);

        $this->patch(route('admin.queues.start', $firstQueue))->assertRedirect();
        $this->assertDatabaseHas('queues', ['id' => $firstQueue->id, 'queue_status' => 'SERVING']);

        $this->patch(route('admin.queues.complete', $firstQueue))->assertRedirect();
        $this->assertDatabaseHas('queues', ['id' => $firstQueue->id, 'queue_status' => 'COMPLETED']);

        $this->post(route('admin.queues.call-next', $this->session), [
            'service_desk_id' => $this->desk->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('queues', ['id' => $secondQueue->id, 'queue_status' => 'CALLED']);
        $this->assertGreaterThanOrEqual(4, QueueSnapshot::count());
    }

    public function test_session_cannot_close_until_all_active_queues_are_resolved(): void
    {
        $this->post(route('queues.store', $this->hospitalService));
        $queue = Queue::firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.queues.sessions.close', $this->session))
            ->assertSessionHasErrors('session');
        $this->assertSame('OPEN', $this->session->fresh()->session_status);

        $this->patch(route('admin.queues.cancel', $queue))->assertRedirect();
        $this->post(route('admin.queues.sessions.close', $this->session))->assertRedirect();

        $this->assertDatabaseHas('queue_sessions', [
            'id' => $this->session->id,
            'session_status' => 'CLOSED',
            'closed_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_cannot_call_another_queue_to_a_busy_desk(): void
    {
        $this->post(route('queues.store', $this->hospitalService));
        $this->post(route('queues.store', $this->hospitalService));

        $this->actingAs($this->admin)->post(route('admin.queues.call-next', $this->session), [
            'service_desk_id' => $this->desk->id,
        ])->assertRedirect();
        $this->post(route('admin.queues.call-next', $this->session), [
            'service_desk_id' => $this->desk->id,
        ])->assertSessionHasErrors('service_desk_id');

        $this->assertSame('CALLED', Queue::findOrFail(1)->queue_status);
        $this->assertSame('WAITING', Queue::findOrFail(2)->queue_status);
    }

    public function test_queue_dashboard_requires_authentication(): void
    {
        auth()->logout();

        $this->get(route('admin.queues.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_open_the_queue_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.queues.index'))
            ->assertOk()
            ->assertSee('Antrean layanan')
            ->assertSee('RS Uji Antrean')
            ->assertSee('Rawat Jalan')
            ->assertSee('Loket 1');
    }

    public function test_officer_only_sees_and_manages_the_assigned_hospital(): void
    {
        $officer = User::factory()->create(['role' => 'OFFICER', 'account_status' => 'ACTIVE']);
        StaffAssignment::create([
            'user_id' => $officer->id,
            'hospital_id' => $this->hospitalService->hospital_id,
            'starts_on' => today(),
            'assignment_status' => 'ACTIVE',
        ]);
        $otherHospital = Hospital::factory()->create(['name' => 'RS Di Luar Penugasan']);
        $otherService = Service::create(['name' => 'Radiologi']);
        $otherHospitalService = HospitalService::create([
            'hospital_id' => $otherHospital->id,
            'service_id' => $otherService->id,
            'initial_service_duration' => 15,
            'availability_status' => 'ACTIVE',
        ]);

        $this->actingAs($officer)->get(route('admin.queues.index'))
            ->assertOk()
            ->assertSee('RS Uji Antrean')
            ->assertDontSee('RS Di Luar Penugasan');
        $this->post(route('admin.queues.sessions.store'), ['hospital_service_id' => $otherHospitalService->id])
            ->assertForbidden();
    }
}
