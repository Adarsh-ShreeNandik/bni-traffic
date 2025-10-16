<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index()
    {
        try {
            // Fetch all events with selected fields
            $events = Event::select(
                'id',
                'name',
                'email',
                'phone',
                'first_name',
                'last_name',
                'region_name',
                'chapter_name',
                'event_date',
                'event_type',
                'join_date',
                'induction_date'
            )
                ->orderBy('event_date', 'desc')
                ->get();

            $data = $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'first_name' => $event->first_name,
                    'last_name' => $event->last_name,
                    'email' => $event->email,
                    'phone' => $event->phone,
                    'region_name' => $event->region_name,
                    'join_date' => $event->join_date,
                    'chapter_name' => $event->chapter_name,
                    'event_date' => $event->event_date,
                    'event_type' => $event->event_type,
                    'induction_date' => $event->induction_date
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Events fetched successfully!',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            Log::error("Fetch Events Error :: ", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while fetching events.'
            ], 500);
        }
    }
}
