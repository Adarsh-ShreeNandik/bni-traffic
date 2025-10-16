<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Relevant;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ImportController extends Controller
{
    public const CODE = 'Import';

    public function importUsers(Request $request)
    {
        try {
            $input = $request->all();

            $validation = Validator::make($input, [
                'file' => "required|mimes:xlsx,xls,csv",
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validation->errors()
                ], 200);
            }

            $file = $request->file('file')->getPathname();

            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Convert header row to lowercase with underscores for DB columns
            $header = array_map(function ($h) {
                return strtolower(str_replace(' ', '_', $h));
            }, $rows[0]);

            unset($rows[0]); // remove header row

            foreach ($rows as $row) {
                $data = array_combine($header, $row);

                // Skip row if any required field is null
                if (empty($data['name']) || empty($data['email']) || empty($data['first_name']) || empty($data['last_name'])) {
                    continue;
                }

                // Check if user already exists by name
                $exists = User::where('name', $data['name'])->orWhere('email', $data['email'])->exists();
                if ($exists) {
                    continue;
                }

                // If your users table has these columns, map them
                User::create([
                    'first_name'  => $data['first_name'] ?? null,
                    'last_name'   => $data['last_name'] ?? null,
                    'name'        => $data['name'] ?? null,
                    'email'       => $data['email'] ?? null,
                    'phone'       => $data['phone'] ?? null,
                    'join_date'   => $this->formatExcelDate($data['join_date'] ?? null),
                    'chapter'     => $data['chapter_name'] ?? null,
                    'region_name' => $data['region_name'] ?? null,
                    'password'    => Hash::make('Ashv@2025'), // fallback password
                    'role_id'     => 2,
                ]);
            }


            return response(['status' => true, 'message' => 'Users import successfully']);
        } catch (\Exception $e) {

            $err = [
                'code' => self::CODE . '01',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];

            Log::info(" ERROR :: ", $err);

            return response()->json([
                'status' => 'false',
                'message' => $e->getMessage()
            ], 200);
        }
    }

    public function importEvents(Request $request)
    {
        try {
            $input = $request->all();

            // Validate file
            $validation = Validator::make($input, [
                'file' => 'required|mimes:xlsx,xls,csv',
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validation->errors()
                ], 200);
            }

            $file = $request->file('file')->getPathname();

            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Convert header row to DB-friendly format
            $header = array_map(function ($h) {
                return strtolower(str_replace(' ', '_', $h));
            }, $rows[0]);

            unset($rows[0]); // Remove header row

            foreach ($rows as $row) {
                $data = array_combine($header, $row);

                // Skip row if critical fields are missing
                // Skip row if any required field is null
                if (empty($data['name']) || empty($data['email']) || empty($data['first_name']) || empty($data['last_name'])) {
                    continue;
                }

                // Check if user already exists by name
                $user = User::where('name', $data['name'])->first();
                // Skip row if user does not exist
                if (!$user) {
                    continue;
                }

                // Prevent duplicate events
                $eventExists = Event::where('user_id', $user->id)
                    ->where('event_date', $this->formatExcelDate($data['event_date'] ?? null))
                    ->where('event_type', $data['event_type'] ?? null)
                    ->exists();

                if ($eventExists) {
                    continue; // Skip duplicate
                }

                // Insert into events table
                Event::create([
                    'user_id'       => $user->id,
                    'name'          => $data['name'] ?? null,
                    'email'         => $data['email'] ?? null,
                    'phone'         => $data['phone'] ?? null,
                    'first_name'    => $data['first_name'] ?? null,
                    'last_name'     => $data['last_name'] ?? null,
                    'region_name'   => $data['region_name'] ?? null,
                    'chapter_name'  => $data['chapter_name'] ?? null,
                    'event_date'    => $this->formatExcelDate($data['event_date'] ?? null),
                    'event_type'    => $data['event_type'] ?? null,
                    'join_date'     => $this->formatExcelDate($data['join_date'] ?? null),
                    'induction_date' => $this->formatExcelDate($data['induction_date'] ?? null),
                ]);
            }

            return response()->json(['status' => true, 'message' => 'Events imported successfully']);
        } catch (\Exception $e) {

            $err = [
                'code' => 'EVENT_IMPORT_01',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];

            Log::error('Event Import Error:', $err);

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 200);
        }
    }

    public function importRelevant(Request $request)
    {
        try {
            $input = $request->all();

            // Validate file
            $validation = Validator::make($input, [
                'file' => 'required|mimes:xlsx,xls,csv',
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validation->errors()
                ], 200);
            }

            $file = $request->file('file')->getPathname();
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Convert header row to DB-friendly format
            $header = array_map(function ($h) {
                return strtolower(str_replace([' ', '-'], ['_', '_'], $h));
            }, $rows[0]);

            unset($rows[0]); // Remove header row

            // Optional: Delete old data before import
            // Relevant::truncate();

            foreach ($rows as $row) {
                $data = array_combine($header, $row);

                // Skip row if critical field is missing (e.g., name)
                if (empty($data['name'])) {
                    continue;
                }

                if (Relevant::where('name', $data['name'])->exists()) {
                    continue;
                }

                // Check if user already exists by name
                $user = User::where('name', $data['name'])->first();
                // Skip row if user does not exist
                if (!$user) {
                    continue;
                }

                // Insert into database
                Relevant::create([
                    'user_id' => $user->id,
                    'name'   => $data['name'] ?? null,
                    'p'      => $data['p'] ?? null,
                    'a'      => $data['a'] ?? null,
                    'l'      => $data['l'] ?? null,
                    'm'      => $data['m'] ?? null,
                    's'      => $data['s'] ?? null,
                    'rgi'    => $data['rgi'] ?? null,
                    'rgo'    => $data['rgo'] ?? null,
                    'rri'    => $data['rri'] ?? null,
                    'rro'    => $data['rro'] ?? null,
                    'v'      => $data['v'] ?? null,
                    '1_2_1'  => $data['1_2_1'] ?? null,
                    'tyfcb'  => $data['tyfcb'] ?? null,
                    'ceu'    => $data['ceu'] ?? null,
                    't'      => $data['t'] ?? null,
                ]);
            }

            return response()->json(['status' => true, 'message' => 'Relevant data imported successfully']);
        } catch (\Exception $e) {
            Log::error('Relevant Import Error:', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    function formatExcelDate($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            // Handle Excel numeric dates
            if (is_numeric($value)) {
                return Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
                )->format('Y-m-d');
            }

            // Handle text dates like "1-Sep-21"
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            // If parsing fails, return null instead of crashing
            return null;
        }
    }
}
