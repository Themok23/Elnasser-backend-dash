<?php

namespace Database\Seeders;

use App\Models\SupportTicketType;
use Illuminate\Database\Seeder;

class SupportTicketSeeder extends Seeder
{
    public function run(): void
    {
        // Ticket Types (Reasons) - hierarchical
        $technical = SupportTicketType::query()->updateOrCreate(
            ['name' => 'Technical issue'],
            ['parent_id' => null, 'is_active' => true, 'created_by_admin_id' => null]
        );
        $points = SupportTicketType::query()->updateOrCreate(
            ['name' => 'Points issue'],
            ['parent_id' => $technical->id, 'is_active' => true, 'created_by_admin_id' => null]
        );
        $appIssue = SupportTicketType::query()->updateOrCreate(
            ['name' => 'App issue'],
            ['parent_id' => $technical->id, 'is_active' => true, 'created_by_admin_id' => null]
        );

        $orders = SupportTicketType::query()->updateOrCreate(
            ['name' => 'Order issue'],
            ['parent_id' => null, 'is_active' => true, 'created_by_admin_id' => null]
        );
        $payment = SupportTicketType::query()->updateOrCreate(
            ['name' => 'Payment issue'],
            ['parent_id' => $orders->id, 'is_active' => true, 'created_by_admin_id' => null]
        );
        $delivery = SupportTicketType::query()->updateOrCreate(
            ['name' => 'Delivery issue'],
            ['parent_id' => $orders->id, 'is_active' => true, 'created_by_admin_id' => null]
        );

        $service = SupportTicketType::query()->updateOrCreate(
            ['name' => 'Service feedback'],
            ['parent_id' => null, 'is_active' => true, 'created_by_admin_id' => null]
        );
        $complaint = SupportTicketType::query()->updateOrCreate(
            ['name' => 'Store/Branch complaint'],
            ['parent_id' => $service->id, 'is_active' => true, 'created_by_admin_id' => null]
        );
        $suggestion = SupportTicketType::query()->updateOrCreate(
            ['name' => 'Suggestion'],
            ['parent_id' => $service->id, 'is_active' => true, 'created_by_admin_id' => null]
        );

        // Ticket data seeding removed - only ticket types structure is seeded
        // You can create tickets manually through the API or admin panel
    }

}


