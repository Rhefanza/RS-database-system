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
use App\Models\SpecialServiceSchedule;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_complete_repeatable_dummy_data(): void
    {
        $this->seed();
        $this->assertDummyDataCounts();

        $this->seed();
        $this->assertDummyDataCounts();

        $this->assertDatabaseHas('queues', ['queue_number' => 1, 'queue_status' => 'COMPLETED']);
        $this->assertDatabaseHas('queues', ['queue_number' => 3, 'queue_status' => 'SERVING']);
        $this->assertDatabaseHas('queues', ['queue_number' => 4, 'queue_status' => 'CALLED']);
        $this->assertDatabaseHas('queues', ['queue_number' => 5, 'queue_status' => 'WAITING']);
        $this->assertDatabaseHas('queues', ['queue_number' => 8, 'queue_status' => 'CANCELLED']);
    }

    private function assertDummyDataCounts(): void
    {
        $this->assertSame(6, District::count());
        $this->assertSame(6, Hospital::count());
        $this->assertSame(6, Service::count());
        $this->assertSame(33, HospitalService::count());
        $this->assertSame(30, ServiceSchedule::count());
        $this->assertSame(6, SpecialServiceSchedule::count());
        $this->assertSame(39, ServiceDesk::count());
        $this->assertSame(6, QueueSession::count());
        $this->assertSame(48, Queue::count());
        $this->assertSame(6, QueueSnapshot::count());
        $this->assertSame(6, User::where('role', 'OFFICER')->count());
        $this->assertSame(5, User::where('role', 'PUBLIC')->count());
        $this->assertSame(6, StaffAssignment::count());
        $this->assertSame(5, SavedLocation::count());
    }
}
