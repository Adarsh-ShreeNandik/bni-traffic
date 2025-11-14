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
            ini_set('max_execution_time', 300);
            $input = $request->all();

            $validation = Validator::make($input, [
                'file' => "required",
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
            ini_set('max_execution_time', 300);
            $input = $request->all();

            // Validate file
            $validation = Validator::make($request->all(), [
                'file' => 'required',
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

            // // Convert header row to DB-friendly format
            // $header = array_map(function ($h) {
            //     return strtolower(str_replace(' ', '_', $h));
            // }, $rows[9]);

            // unset($rows[0]); // Remove header row
            // ✅ Header is on row 9 (index 8, since array starts at 0)
            $headerRowIndex = 9;
            $header = array_map(function ($h) {
                return strtolower(str_replace(' ', '_', trim($h)));
            }, $rows[$headerRowIndex]);

            // ✅ Define the required columns
            $requiredColumns = ['email', 'first_name', 'last_name', 'event_date', 'event_type', 'phone', 'join_date', 'chapter_name', 'region_name', 'induction_date'];

            // ✅ Check if all required columns exist in header
            $missingColumns = array_diff($requiredColumns, $header);

            if (!empty($missingColumns)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid file format. Missing columns: ' . implode(', ', $missingColumns)
                ], 200);
            }

            // ✅ Remove everything before row 9 (headers + junk rows)
            $rows = array_slice($rows, $headerRowIndex + 1);
            // Event::truncate();

            foreach ($rows as $row) {
                $data = array_combine($header, $row);

                // Skip row if critical fields are missing
                // Skip row if any required field is null
                if (empty($data['email']) || empty($data['first_name']) || empty($data['last_name'])) {
                    continue;
                }

                $name = $data['first_name'] . ' ' . $data['last_name'];

                // Check if user already exists by name
                $user = User::where('name', $name)->orWhere('email', $data['email'])->first();

                if (!$user) {
                    // If your users table has these columns, map them
                    $user = User::create([
                        'first_name'  => $data['first_name'] ?? null,
                        'last_name'   => $data['last_name'] ?? null,
                        'name'        => $name ?? null,
                        'email'       => $data['email'] ?? null,
                        'phone'       => $data['phone'] ?? null,
                        'join_date'   => $this->formatDate($data['join_date'] ?? null),
                        'chapter'     => $data['chapter_name'] ?? null,
                        'region_name' => $data['region_name'] ?? null,
                        'password'    => Hash::make('Ashv@2025'), // fallback password
                        'role_id'     => 2,
                    ]);
                } else {
                    // ✅ If user already exists, update their join_date
                    $user->update([
                        'join_date' => $this->formatDate($data['join_date'] ?? null),
                    ]);
                }

                // Prevent duplicate events
                // $eventExists = Event::where('user_id', $user->id)
                //     ->where('event_date', $this->formatExcelDate($data['event_date'] ?? null))
                //     ->where('event_type', $data['event_type'] ?? null)
                //     ->exists();

                // if ($eventExists) {
                //     continue; // Skip duplicate
                // }

                // Insert into events table
                // Event::create([
                //     'user_id'       => $user->id,
                //     'name'          => $name ?? null,
                //     'email'         => $data['email'] ?? null,
                //     'phone'         => $data['phone'] ?? null,
                //     'first_name'    => $data['first_name'] ?? null,
                //     'last_name'     => $data['last_name'] ?? null,
                //     'region_name'   => $data['region_name'] ?? null,
                //     'chapter_name'  => $data['chapter_name'] ?? null,
                //     'event_date'    => $this->formatExcelDate($data['event_date'] ?? null),
                //     'event_type'    => $data['event_type'] ?? null,
                //     'join_date'     => $this->formatDate($data['join_date'] ?? null),
                //     'induction_date' => $this->formatDate($data['induction_date'] ?? null),
                // ]);

                $eventExists = Event::where('user_id', $user->id)
                    ->where('event_date', $this->formatExcelDate($data['event_date'] ?? null))
                    ->where('event_type', $data['event_type'] ?? null)
                    ->first();

                $eventData = [
                    'user_id'        => $user->id,
                    'name'           => $name ?? null,
                    'email'          => $data['email'] ?? null,
                    'phone'          => $data['phone'] ?? null,
                    'first_name'     => $data['first_name'] ?? null,
                    'last_name'      => $data['last_name'] ?? null,
                    'region_name'    => $data['region_name'] ?? null,
                    'chapter_name'   => $data['chapter_name'] ?? null,
                    'event_date'     => $this->formatExcelDate($data['event_date'] ?? null),
                    'event_type'     => $data['event_type'] ?? null,
                    'join_date'      => $this->formatDate($data['join_date'] ?? null),
                    'induction_date' => $this->formatDate($data['induction_date'] ?? null),
                ];

                // ✅ If event exists → update; else → create new
                if ($eventExists) {
                    $eventExists->update($eventData);
                } else {
                    Event::create($eventData);
                }
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
            ini_set('max_execution_time', 300);
            $input = $request->all();

            // Validate file
            $validation = Validator::make($input, [
                'file' => 'required',
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

            // ✅ Step 1: Validate targeted date
            $targeted_date = $rows[6][10] ?? null;

            if (empty($targeted_date)) {
                return response()->json([
                    'status' => false,
                    'message' => 'To date not found in the expected column (row 6, column 10).'
                ], 200);
            }

            if ($targeted_date) {
                // The format is day/month/year with 2-digit year
                $date = \DateTime::createFromFormat('d/m/y', $targeted_date);

                if ($date) {
                    $targeted_date = $date->format('Y-m-d'); // convert to YYYY-mm-dd
                }
            }
            // Convert header row to DB-friendly format
            // $header = array_map(function ($h) {
            //     return strtolower(str_replace([' ', '-'], ['_', '_'], $h));
            // }, $rows[7]);

            // unset($rows[7]); // Remove header row

            // 10-11-2025
            // ✅ Convert header row to DB-friendly format
            $headerRowIndex = 7;
            if (!isset($rows[$headerRowIndex])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid file format: header row not found.'
                ], 200);
            }

            $header = array_map(function ($h) {
                return strtolower(str_replace([' ', '-'], ['_', '_'], trim($h)));
            }, $rows[$headerRowIndex]);

            // ✅ Define required columns
            $requiredColumns = [
                'first_name',
                'last_name',
                'p',
                'a',
                'l',
                'm',
                's',
                'rgi',
                'rgo',
                'rri',
                'rro',
                'v',
                '1_2_1',
                'tyfcb',
                'ceu',
                't'
            ];

            // ✅ Check if required columns exist
            $missingColumns = array_diff($requiredColumns, $header);
            if (!empty($missingColumns)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid file format. Missing columns: ' . implode(', ', $missingColumns)
                ], 200);
            }

            unset($rows[$headerRowIndex]); // Remove header row

            $totalRows = count($rows);

            // Optional: Delete old data before import
            // Relevant::truncate();

            // Start from row 9 (index 8)
            for ($i = 8; $i < $totalRows; $i++) {
                $row = $rows[$i];
                $data = array_combine($header, $row);

                // Skip row if critical fields are missing
                if (empty($data['first_name']) || empty($data['last_name'])) {
                    continue;
                }

                $name = $data['first_name'] . ' ' . $data['last_name'];

                // Check if user exists by first_name and last_name
                $user = User::where('name', $name)
                    ->first();

                if (!$user) {
                    continue; // Skip if user not found
                }

                // Prevent duplicate relevant entry for the same user
                // if (Relevant::where('user_id', $user->id)->exists()) {
                //     continue;
                // }

                $relevant = Relevant::where([
                    'user_id' => $user->id,
                    'targeted_date' => $targeted_date
                ])->first();

                // Insert into relevant table
                if (!$relevant) {
                    Relevant::create([
                        'user_id' => $user->id,
                        'name'    => $user->name, // optional: store full name
                        'first_name' => $data['first_name'] ?? null,
                        'last_name' => $data['last_name'] ?? null,
                        'p'       => $data['p'] ?? null,
                        'a'       => $data['a'] ?? null,
                        'l'       => $data['l'] ?? null,
                        'm'       => $data['m'] ?? null,
                        's'       => $data['s'] ?? null,
                        'rgi'     => $data['rgi'] ?? null,
                        'rgo'     => $data['rgo'] ?? null,
                        'rri'     => $data['rri'] ?? null,
                        'rro'     => $data['rro'] ?? null,
                        'v'       => $data['v'] ?? null,
                        '1_2_1'   => $data['1_2_1'] ?? null,
                        'tyfcb'   => $data['tyfcb'] ?? null,
                        'ceu'     => $data['ceu'] ?? null,
                        't'       => $data['t'] ?? null,
                        'targeted_date' => $targeted_date,
                    ]);
                } else {
                    $relevant->update([
                        'p'       => $data['p'] ?? null,
                        'a'       => $data['a'] ?? null,
                        'l'       => $data['l'] ?? null,
                        'm'       => $data['m'] ?? null,
                        's'       => $data['s'] ?? null,
                        'rgi'     => $data['rgi'] ?? null,
                        'rgo'     => $data['rgo'] ?? null,
                        'rri'     => $data['rri'] ?? null,
                        'rro'     => $data['rro'] ?? null,
                        'v'       => $data['v'] ?? null,
                        '1_2_1'   => $data['1_2_1'] ?? null,
                        'tyfcb'   => $data['tyfcb'] ?? null,
                        'ceu'     => $data['ceu'] ?? null,
                        't'       => $data['t'] ?? null,
                    ]);
                }
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

    function formatDate($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            // ✅ If numeric, try to interpret as a timestamp (Excel serials will be ignored)
            if (is_numeric($value)) {
                // Skip Excel-style numeric serials; just ignore
                return null;
            }

            // ✅ Normalize separators (replace . or - with /)
            $cleanValue = str_replace(['.', '-'], '/', trim($value));

            // ✅ Try these formats explicitly
            $formats = [
                'd/m/y',
                'd/m/Y',
                'j/n/y',
                'j/n/Y',
            ];

            foreach ($formats as $format) {
                try {
                    $parsed = Carbon::createFromFormat($format, $cleanValue);
                    if ($parsed !== false) {
                        return $parsed->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    // Try next format
                }
            }

            // ✅ Fallback (only if Carbon can parse automatically)
            // This helps handle cases like 2024/03/01
            return Carbon::parse(str_replace('/', '-', $cleanValue))->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
