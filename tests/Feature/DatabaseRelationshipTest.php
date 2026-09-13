<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Hospital;
use App\Models\HospitalService;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\QueueSnapshot;
use App\Models\SavedLocation;
use App\Models\Service;
use App\Models\ServiceDesk;
use App\Models\ServiceSchedule;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_hospital_service_and_queue_relationships_are_connected(): void
    {
        $district = District::create(['name' => 'Wonokromo']);
        $hospital = Hospital::factory()->create(['district_id' => $district->id]);
        $service = Service::create(['name' => 'Poliklinik Umum']);
        $hospitalService = HospitalService::create([
            'hospital_id' => $hospital->id,
            'service_id' => $service->id,
            'initial_service_duration' => 15,
            'availability_status' => 'ACTIVE',
        ]);
        $schedule = ServiceSchedule::create([
            'hospital_service_id' => $hospitalService->id,
            'day' => 'MONDAY',
            'opens_at' => '08:00',
            'closes_at' => '14:00',
            'quota' => 50,
        ]);
        $desk = ServiceDesk::create([
            'hospital_service_id' => $hospitalService->id,
            'name' => 'Loket A',
        ]);
        $officer = User::factory()->create(['role' => 'OFFICER']);
        $assignment = StaffAssignment::create([
            'user_id' => $officer->id,
            'hospital_id' => $hospital->id,
            'starts_on' => now()->toDateString(),
        ]);
        $session = QueueSession::create([
            'hospital_service_id' => $hospitalService->id,
            'opened_by_user_id' => $officer->id,
            'session_date' => now()->toDateString(),
            'started_at' => now(),
        ]);
        $queue = Queue::create([
            'queue_session_id' => $session->id,
            'queue_number' => 1,
            'service_desk_id' => $desk->id,
        ]);
        $snapshot = QueueSnapshot::create([
            'hospital_service_id' => $hospitalService->id,
            'waiting_count' => 1,
            'active_desk_count' => 1,
        ]);
        $location = SavedLocation::create([
            'user_id' => $officer->id,
            'label' => 'Rumah',
            'latitude' => -7.2575,
            'longitude' => 112.7521,
            'is_primary' => true,
        ]);

        $this->assertTrue($district->hospitals->contains($hospital));
        $this->assertTrue($hospital->services->contains($service));
        $this->assertTrue($hospitalService->schedules->contains($schedule));
        $this->assertTrue($hospitalService->desks->contains($desk));
        $this->assertTrue($hospitalService->queueSessions->contains($session));
        $this->assertTrue($hospitalService->queueSnapshots->contains($snapshot));
        $this->assertTrue($officer->staffAssignments->contains($assignment));
        $this->assertTrue($officer->savedLocations->contains($location));
        $this->assertTrue($session->queues->contains($queue));
        $this->assertTrue($desk->queues->contains($queue));
    }

    public function test_queue_number_must_be_unique_inside_one_session(): void
    {
        $hospital = Hospital::factory()->create();
        $service = Service::create(['name' => 'IGD']);
        $hospitalService = HospitalService::create(['hospital_id' => $hospital->id, 'service_id' => $service->id]);
        $officer = User::factory()->create();
        $session = QueueSession::create([
            'hospital_service_id' => $hospitalService->id,
            'opened_by_user_id' => $officer->id,
            'session_date' => now()->toDateString(),
            'started_at' => now(),
        ]);

        Queue::create(['queue_session_id' => $session->id, 'queue_number' => 1]);

        $this->expectException(QueryException::class);
        Queue::create(['queue_session_id' => $session->id, 'queue_number' => 1]);
    }
}
