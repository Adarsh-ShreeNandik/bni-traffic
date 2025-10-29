<?php

namespace App\Http\Controllers;

use App\Mail\ForgetPasswordMail;
use App\Models\Relevant;
use App\Models\User;
use App\Models\VerifyOtp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    public const CODE = 'A';

    public function login(Request $request)
    {
        try {
            $input = $request->all();

            $validation = Validator::make($input, [
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validation->errors()
                ], 200);
            }

            $credentials = $request->only('email', 'password');

            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            $user = Auth::user();
            $token = $user->createToken('API Token')->accessToken; // Passport token
            // dd($token);
            return response()->json([
                'status' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ]);
        } catch (\Exception $e) {
            $err = [
                'code' => 'LOGIN_01',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];

            Log::error("Login Error :: ", $err);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong during login'
            ], 500);
        }
    }

    public function fetchUsers()
    {
        try {
            // Fetch all users except role_id = 1
            $users = User::where('role_id', '!=', 1)->select('id', 'first_name', 'last_name', 'email', 'phone', 'chapter', 'region_name', 'join_date')->get();

            // Structure data by key (for example, user ID or region)
            $data = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'chapter' => $user->chapter,
                    'region_name' => $user->region_name,
                    'join_date' => $user->join_date,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Fetch users successfully!',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            $err = [
                'code' => 'LOGIN_01',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];

            Log::error("Login Error :: ", $err);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong during login'
            ], 500);
        }
    }

    public function userReport()
    {
        try {
            $user = Auth::user();
            $userId = $user->id;
            $relevant = Relevant::with('user')->select(
                'user_id',
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
                't',
                'targeted_date'
            )->where('user_id', $userId)->first();

            if (!$relevant) {
                $date = new \DateTime();
                $targetDate = \Carbon\Carbon::parse($date);
                $targeted_date = $date->format('M-y');
                return response()->json([
                    'status' => false,
                    'message' => 'No relevant record found.',
                    'performance' => [],
                    'need_to_do' => [],
                    'user_info' => [
                        'chapter' => $user->chapter,
                        'target_month' => $targeted_date,
                        'name' => $user->name,
                        'total_score' => 0,
                        'wednesdayCount' => 0,
                        'weeks' => 0
                    ]
                ], 200);
            }

            $trainingCount = 0;
            if ($relevant && $relevant->user) {
                $targetDate = \Carbon\Carbon::parse($relevant->targeted_date);
                $fromDate = $targetDate->copy()->subMonths(6);
                $toDate = $targetDate->copy();
                // Now filter trainings dynamically
                $trainingCount = $relevant->user->trainings()
                    ->whereBetween('event_date', [$fromDate, $toDate])
                    ->count();

                $join_date = $relevant->user->join_date;
            }

            $targeted_date = $relevant->targeted_date ?? null;
            $created_at = $relevant->created_at ?? null;
            $wednesdayCount = 0;
            if ($targeted_date) {
                // Create DateTime object from your current format (dd/mm/yy)
                $date = new \DateTime($targeted_date);  // create DateTime object
                $targetDate = \Carbon\Carbon::parse($targeted_date);
                $targeted_date = $date->format('M-y'); // format as "Sep-25"
                // $currentDate = \Carbon\Carbon::parse($created_at);
                $currentDate = \Carbon\Carbon::parse($join_date);
                $period = \Carbon\CarbonPeriod::create($currentDate, $targetDate);

                $wednesdayCount = min(
                    collect($period)
                        ->filter(fn($date) => $date->isWednesday())
                        ->count(),
                    26
                );
            } else {
                $targeted_date = null;
            }

            $Absenteeism = (int)$relevant->a;
            $referral = (int)$relevant->rgi + $relevant->rgo;
            $visitor = $relevant->v;
            $ariving_on_time = $relevant->l;
            $tyfcb = (int)$relevant->tyfcb;
            $testimonial = (int)$relevant->t;
            $training = $trainingCount;

            $Absenteeism_color_codes = [
                0  => '1', // Grey
                5  => '2', // Red
                10 => '3', // Yellow
                15 => '4', // Green
            ];

            $arriving_on_time_color_codes = [
                0 => '2', // Red
                5 => '4', // Green
            ];

            $referral_color_codes = [
                0  => '1', // Grey
                5  => '1', // Grey
                10 => '2', // Red
                15 => '3', // Yellow
                20 => '4', // Green
            ];

            $visitor_color_codes = [
                0  => '1', // Grey
                5  => '2', // Red
                10 => '3', // Yellow
                15 => '4', // Green
                20 => '4', // Green
            ];

            $tyfcb_color_codes = [
                0  => '1', // Grey
                5  => '2', // Red
                10 => '3', // Yellow
                15 => '4', // Green
            ];

            $testimonial_color_codes = [
                0  => '2', // Red
                5  => '3', // Yellow
                10 => '4', // Green
            ];

            $training_color_codes = [
                0  => '1', // Grey
                5  => '2', // Red
                10 => '3', // Yellow
                15 => '4', // Green
            ];

            // $weeks = $relevant->p + $relevant->a + $relevant->l + $relevant->m + $relevant->s + $wednesdayCount; Not use at this time because calculation wrong
            $weeks = $wednesdayCount;
            $referralPointsArray = [
                ['points' => 5, 'multiplier' => 0.5],
                ['points' => 10, 'multiplier' => 0.75],
                ['points' => 15, 'multiplier' => 1],
                ['points' => 20, 'multiplier' => 1.2],
            ];

            $referralResult = $this->calculateCategoryPoints($weeks, $referralPointsArray);

            $visitorPointsArray = [
                ['points' => 5, 'multiplier' => 0.10],
                ['points' => 10, 'multiplier' => 0.25],
                ['points' => 15, 'multiplier' => 0.50],
                ['points' => 20, 'multiplier' => 0.75],
            ];

            $visitorResult = $this->calculateCategoryPoints($weeks, $visitorPointsArray);

            $testimonialPointsArray = [
                ['points' => 5, 'multiplier' => 0.0000001],
                ['points' => 10, 'multiplier' => 0.074],
            ];

            $testimonialResult = $this->calculateCategoryPoints($weeks, $testimonialPointsArray);

            $tyfcbPointsArray = [
                ['points' => 5, 'value' => 500000],
                ['points' => 10, 'value' => 1000000],
                ['points' => 15, 'value' => 2000000],
            ];

            $traningPointsArray = [
                ['points' => 5, 'value' => 1],
                ['points' => 10, 'value' => 2],
                ['points' => 15, 'value' => 3],
            ];

            // Build performance array
            $performance[] = [
                'name' => 'absenteeism',
                'current_score' => $this->calculateScore(15, 5, $Absenteeism), // total=15, deduct=5 per late
                'current_data' => $Absenteeism,
                'color_code' => $Absenteeism_color_codes[$this->calculateScore(15, 5, $Absenteeism)]
                    ?? '1', // default to Grey if score not found
            ];

            $performance[] = [
                'name' => 'arriving on time',
                'current_score' => $this->calculateArrivingOnTime(5, $ariving_on_time),
                'current_data' => $ariving_on_time,
                'color_code' => $arriving_on_time_color_codes[$this->calculateArrivingOnTime(5, $ariving_on_time)]
                    ?? '1', // default to Grey if score not found
            ];

            $performance[] = [
                'name' => 'visitor',
                'current_score' => $this->getReferralPoints($visitor, $visitorResult),
                'current_data' => $visitor, // or some calculation
                'color_code' => $visitor_color_codes[$this->getReferralPoints($visitor, $visitorResult)]
                    ?? '1', // default to Grey if score not found
            ];

            $performance[] = [
                'name' => 'referrals',
                'current_score' => $this->getReferralPoints($referral, $referralResult),
                'current_data' => $referral,
                'color_code' => $referral_color_codes[$this->getReferralPoints($referral, $referralResult)]
                    ?? '1', // default to Grey if score not found
            ];

            $performance[] = [
                'name' => 'tyfcb',
                'current_score' => $this->getReferralPoints($tyfcb, $tyfcbPointsArray),
                'current_data' => $tyfcb,
                'color_code' => $tyfcb_color_codes[$this->getReferralPoints($tyfcb, $tyfcbPointsArray)]
                    ?? '1', // default to Grey if score not found
            ];

            $performance[] = [
                'name' => 'testimonial',
                'current_score' => $this->getReferralPoints($testimonial, $testimonialResult),
                'current_data' => $testimonial,
                'color_code' => $testimonial_color_codes[$this->getReferralPoints($testimonial, $testimonialResult)]
                    ?? '1', // default to Grey if score not found
            ];

            $performance[] = [
                'name' => 'training',
                'current_score' => $this->getReferralPoints($training, $traningPointsArray),
                'current_data' => $training,
                'color_code' => $training_color_codes[$this->getReferralPoints($training, $traningPointsArray)]
                    ?? '1', // default to Grey if score not found
            ];

            // $needToDo[] = [
            //     'name' => 'absenteeism',
            //     '5_points' => "-",
            //     '10_points' => "-",
            //     '15_points' => "-",
            //     '20_points' => "-",
            // ];

            // $needToDo[] = [
            //     'name' => 'arriving on time',
            //     '5_points' => "-",
            //     '10_points' => "-",
            //     '15_points' => "-",
            //     '20_points' => "-",
            // ];

            // dd($referralResult);
            $needToDo[] = $this->calculateNeedToDo('visitor', $visitor, $visitorResult);
            $needToDo[] = $this->calculateNeedToDo('referrals', $referral, $referralResult);
            $needToDo[] = $this->calculateNeedToDo('tyfcb', $tyfcb, $tyfcbPointsArray);
            $needToDo[] = $this->calculateNeedToDo('testimonial', $testimonial, $testimonialResult);
            $needToDo[] = $this->calculateNeedToDo('training', $training, $traningPointsArray);
            $totalScore = array_sum(array_column($performance, 'current_score'));
            // Define all points keys that must exist
            $allKeys = ['5_points', '10_points', '15_points', '20_points'];

            // Normalize each needToDo item
            foreach ($needToDo as &$item) {
                foreach ($allKeys as $key) {
                    if (!array_key_exists($key, $item)) {
                        $item[$key]['value'] = '-'; // fill missing key with '-'
                        $item[$key]['color_code'] = 4; // fill missing key with '-'
                    }
                }
                // Optional: sort keys so order is consistent
                // ksort($item);
            }
            return response()->json([
                'status' => true,
                'message' => 'Fetch users successfully!',
                'performance' => $performance,
                'need_to_do' => $needToDo,
                'user_info' => [
                    'chapter' => $relevant?->user?->chapter,
                    'target_month' => $targeted_date,
                    'name' => $relevant?->user?->name,
                    'total_score' => $totalScore,
                    'wednesdayCount' => $wednesdayCount,
                    'weeks' => $weeks
                ]
            ]);
        } catch (\Exception $e) {
            $err = [
                'code' => 'LOGIN_01',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];

            Log::error("Login Error :: ", $err);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong during login'
            ], 500);
        }
    }

    // public function userReport()
    // {
    //     try {
    //         $user = Auth::user();
    //         $userId = $user->id;
    //         $relevant = Relevant::with('user')->select(
    //             'user_id',
    //             'p',
    //             'a',
    //             'l',
    //             'm',
    //             's',
    //             'rgi',
    //             'rgo',
    //             'rri',
    //             'rro',
    //             'v',
    //             '1_2_1',
    //             'tyfcb',
    //             'ceu',
    //             't',
    //             'targeted_date'
    //         )->where('user_id', $userId)->first();

    //         if (!$relevant) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'No relevant record found.'
    //             ], 404);
    //         }

    //         $trainingCount = 0;
    //         if ($relevant && $relevant->user) {
    //             $targetDate = \Carbon\Carbon::parse($relevant->targeted_date);
    //             $fromDate = $targetDate->copy()->subMonths(6);
    //             $toDate = $targetDate->copy();
    //             // Now filter trainings dynamically
    //             $trainingCount = $relevant->user->trainings()
    //                 ->whereBetween('event_date', [$fromDate, $toDate])
    //                 ->count();

    //             $join_date = $relevant->user->join_date;
    //         }

    //         $targeted_date = $relevant->targeted_date ?? null;
    //         $created_at = $relevant->created_at ?? null;
    //         $wednesdayCount = 0;
    //         if ($targeted_date) {
    //             // Create DateTime object from your current format (dd/mm/yy)
    //             $date = new \DateTime($targeted_date);  // create DateTime object
    //             $targetDate = \Carbon\Carbon::parse($targeted_date);
    //             $targeted_date = $date->format('M-y'); // format as "Sep-25"
    //             // $currentDate = \Carbon\Carbon::parse($created_at);
    //             $currentDate = \Carbon\Carbon::parse($join_date);
    //             $period = \Carbon\CarbonPeriod::create($currentDate, $targetDate);

    //             $wednesdayCount = min(
    //                 collect($period)
    //                     ->filter(fn($date) => $date->isWednesday())
    //                     ->count(),
    //                 26
    //             );
    //         } else {
    //             $targeted_date = null;
    //         }

    //         $Absenteeism = (int)$relevant->a;
    //         $referral = (int)$relevant->rgi + $relevant->rgo;
    //         $visitor = $relevant->v;
    //         $ariving_on_time = $relevant->l;
    //         $tyfcb = (int)$relevant->tyfcb;
    //         $testimonial = (int)$relevant->t;
    //         $training = $trainingCount;
    //         // $weeks = $relevant->p + $relevant->a + $relevant->l + $relevant->m + $relevant->s + $wednesdayCount; Not use at this time because calculation wrong
    //         $weeks = $wednesdayCount;
    //         $referralPointsArray = [
    //             ['points' => 5, 'multiplier' => 0.5],
    //             ['points' => 10, 'multiplier' => 0.75],
    //             ['points' => 15, 'multiplier' => 1],
    //             ['points' => 20, 'multiplier' => 1.2],
    //         ];

    //         $referralResult = $this->calculateCategoryPoints($weeks, $referralPointsArray);

    //         $visitorPointsArray = [
    //             ['points' => 5, 'multiplier' => 0.10],
    //             ['points' => 10, 'multiplier' => 0.25],
    //             ['points' => 15, 'multiplier' => 0.50],
    //             ['points' => 20, 'multiplier' => 0.75],
    //         ];

    //         $visitorResult = $this->calculateCategoryPoints($weeks, $visitorPointsArray);

    //         $testimonialPointsArray = [
    //             ['points' => 5, 'multiplier' => 0.0000001],
    //             ['points' => 10, 'multiplier' => 0.074],
    //         ];

    //         $testimonialResult = $this->calculateCategoryPoints($weeks, $testimonialPointsArray);

    //         $tyfcbPointsArray = [
    //             ['points' => 5, 'value' => 500000],
    //             ['points' => 10, 'value' => 1000000],
    //             ['points' => 15, 'value' => 2000000],
    //         ];

    //         $traningPointsArray = [
    //             ['points' => 5, 'value' => 1],
    //             ['points' => 10, 'value' => 2],
    //             ['points' => 15, 'value' => 3],
    //         ];

    //         // Build performance array
    //         $performance[] = [
    //             'name' => 'absenteeism',
    //             'current_score' => $this->calculateScore(15, 5, $Absenteeism), // total=15, deduct=5 per late
    //             'current_data' => $Absenteeism,
    //             'percentage' => 15 > 0
    //                 ? round(($this->calculateScore(15, 5, $Absenteeism) / 15) * 100, 2)
    //                 : 0,
    //             'color_code' => $this->getColorByPercentage(
    //                 15 > 0
    //                     ? round(($this->calculateScore(15, 5, $Absenteeism) / 15) * 100, 2)
    //                     : 0
    //             ),
    //         ];

    //         $performance[] = [
    //             'name' => 'arriving on time',
    //             'current_score' => $this->calculateArrivingOnTime(5, $ariving_on_time),
    //             'current_data' => $ariving_on_time,
    //             'percentage' => 5 > 0
    //                 ? round(($this->calculateArrivingOnTime(5, $ariving_on_time) / 5) * 100, 2)
    //                 : 0,
    //             'color_code' => $this->getColorByPercentage(
    //                 5 > 0
    //                     ? round(($this->calculateArrivingOnTime(5, $ariving_on_time) / 5) * 100, 2)
    //                     : 0
    //             ),
    //         ];

    //         $performance[] = [
    //             'name' => 'visitor',
    //             'current_score' => $this->getReferralPoints($visitor, $visitorResult),
    //             'current_data' => $visitor, // or some calculation
    //             'percentage' => collect($visitorResult)->max('points') > 0
    //                 ? round(($this->getReferralPoints($visitor, $visitorResult) / collect($visitorResult)->max('points')) * 100, 2)
    //                 : 0,
    //             'color_code' => $this->getColorByPercentage(
    //                 collect($visitorResult)->max('points') > 0
    //                     ? round(($this->getReferralPoints($visitor, $visitorResult) / collect($visitorResult)->max('points')) * 100, 2)
    //                     : 0
    //             ),
    //         ];

    //         $performance[] = [
    //             'name' => 'referrals',
    //             'current_score' => $this->getReferralPoints($referral, $referralResult),
    //             'current_data' => $referral,
    //             'percentage' => ($max = collect($referralPointsArray)->max('points')) > 0
    //                 ? round(($score = $this->getReferralPoints($referral, $referralResult)) / $max * 100, 2)
    //                 : 0,
    //             'color_code' => ($max > 0)
    //                 ? $this->getColorByPercentage(round($score / $max * 100, 2))
    //                 : $this->getColorByPercentage(0),
    //         ];

    //         $performance[] = [
    //             'name' => 'tyfcb',
    //             'current_score' => $this->getReferralPoints($tyfcb, $tyfcbPointsArray),
    //             'current_data' => $tyfcb,
    //             'percentage' => collect($tyfcbPointsArray)->max('points') > 0
    //                 ? round(($this->getReferralPoints($tyfcb, $tyfcbPointsArray) / collect($tyfcbPointsArray)->max('points')) * 100, 2)
    //                 : 0,
    //             'color_code' => $this->getColorByPercentage(
    //                 collect($tyfcbPointsArray)->max('points') > 0
    //                     ? round(($this->getReferralPoints($tyfcb, $tyfcbPointsArray) / collect($tyfcbPointsArray)->max('points')) * 100, 2)
    //                     : 0
    //             ),
    //         ];

    //         $performance[] = [
    //             'name' => 'testimonial',
    //             'current_score' => $this->getReferralPoints($testimonial, $testimonialResult),
    //             'current_data' => $testimonial,
    //             'percentage' => collect($testimonialResult)->max('points') > 0
    //                 ? round(($this->getReferralPoints($testimonial, $testimonialResult) / collect($testimonialResult)->max('points')) * 100, 2)
    //                 : 0,
    //             'color_code' => $this->getColorByPercentage(
    //                 collect($testimonialResult)->max('points') > 0
    //                     ? round(($this->getReferralPoints($testimonial, $testimonialResult) / collect($testimonialResult)->max('points')) * 100, 2)
    //                     : 0
    //             ),
    //         ];

    //         $performance[] = [
    //             'name' => 'training',
    //             'current_score' => $this->getReferralPoints($training, $traningPointsArray),
    //             'current_data' => $training,
    //             'percentage' => collect($traningPointsArray)->max('points') > 0
    //                 ? round(($this->getReferralPoints($training, $traningPointsArray) / collect($traningPointsArray)->max('points')) * 100, 2)
    //                 : 0,
    //             'color_code' => $this->getColorByPercentage(
    //                 collect($traningPointsArray)->max('points') > 0
    //                     ? round(($this->getReferralPoints($training, $traningPointsArray) / collect($traningPointsArray)->max('points')) * 100, 2)
    //                     : 0
    //             ),
    //         ];

    //         $needToDo[] = [
    //             'name' => 'absenteeism',
    //             '5_points' => "-",
    //             '10_points' => "-",
    //             '15_points' => "-",
    //             '20_points' => "-",
    //         ];

    //         $needToDo[] = [
    //             'name' => 'arriving on time',
    //             '5_points' => "-",
    //             '10_points' => "-",
    //             '15_points' => "-",
    //             '20_points' => "-",
    //         ];

    //         // dd($referralResult);
    //         $needToDo[] = $this->calculateNeedToDo('visitor', $visitor, $visitorResult);
    //         $needToDo[] = $this->calculateNeedToDo('referrals', $referral, $referralResult);
    //         $needToDo[] = $this->calculateNeedToDo('tyfcb', $tyfcb, $tyfcbPointsArray);
    //         $needToDo[] = $this->calculateNeedToDo('testimonial', $testimonial, $testimonialResult);
    //         $needToDo[] = $this->calculateNeedToDo('training', $training, $traningPointsArray);
    //         $totalScore = array_sum(array_column($performance, 'current_score'));
    //         // Define all points keys that must exist
    //         $allKeys = ['5_points', '10_points', '15_points', '20_points'];

    //         // Normalize each needToDo item
    //         foreach ($needToDo as &$item) {
    //             foreach ($allKeys as $key) {
    //                 if (!array_key_exists($key, $item)) {
    //                     $item[$key] = '-'; // fill missing key with '-'
    //                 }
    //             }
    //             // Optional: sort keys so order is consistent
    //             // ksort($item);
    //         }
    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Fetch users successfully!',
    //             'performance' => $performance,
    //             'need_to_do' => $needToDo,
    //             'user_info' => [
    //                 'chapter' => $relevant?->user?->chapter,
    //                 'target_month' => $targeted_date,
    //                 'name' => $relevant?->user?->name,
    //                 'total_score' => $totalScore,
    //                 'wednesdayCount' => $wednesdayCount,
    //                 'weeks' => $weeks
    //             ]
    //         ]);
    //     } catch (\Exception $e) {
    //         $err = [
    //             'code' => 'LOGIN_01',
    //             'message' => $e->getMessage(),
    //             'file' => $e->getFile(),
    //             'line' => $e->getLine()
    //         ];

    //         Log::error("Login Error :: ", $err);

    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Something went wrong during login'
    //         ], 500);
    //     }
    // }

    public function userProfile()
    {
        try {
            // Get the currently logged-in user
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $data = [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'chapter' => $user->chapter,
                'region_name' => $user->region_name,
                'join_date' => date('d-m-Y', strtotime($user->join_date)),
                'name' => $user->name,
                'gst' => $user->gst,
                'company_name' => $user->company_name,
                'address' => $user->address,
            ];

            return response()->json([
                'status' => true,
                'message' => 'User profile fetched successfully!',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error("User Profile Error :: ", [
                'code' => 'USER_PROFILE_01',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while fetching user profile.'
            ], 500);
        }
    }

    public function updateUserProfile(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            // Validate input
            $validation = Validator::make($request->all(), [
                'phone'         => 'required|string|max:15',
                'gst'           => 'required|string|max:100',
                'company_name'  => 'required|string|max:100',
                'address'       => 'required|string|max:255',
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validation->errors()
                ], 422);
            }

            // Update only these fields if provided
            $user->update(array_filter([
                'phone'         => $request->phone,
                'gst'           => $request->gst,
                'company_name'  => $request->company_name,
                'address'       => $request->address,
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Profile updated successfully!',
                'data' => $user->only(['id', 'phone', 'gst', 'company_name', 'address'])
            ]);
        } catch (\Exception $e) {
            Log::error("User Profile Update Error :: ", [
                'code' => 'USER_PROFILE_UPDATE_01',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while updating profile.'
            ], 500);
        }
    }

    public function userTableReport()
    {
        try {
            $user = Auth::user();
            $userId = $user->id;

            // Fetch last 6 months' relevant data
            $relevants = Relevant::with('user')
                ->where('user_id', $userId)
                ->whereBetween('targeted_date', [
                    now()->subMonths(6)->startOfDay(),
                    now()->endOfDay()
                ])
                ->orderBy('targeted_date', 'desc')
                ->get();

            if ($relevants->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No relevant records found in the last 6 months.'
                ], 404);
            }

            $monthlyReports = [];
            $totalScore = 0;

            foreach ($relevants as $relevant) {
                // Calculate training count for the past 6 months relative to this record
                $targetDate = \Carbon\Carbon::parse($relevant->targeted_date);
                $fromDate = $targetDate->copy()->subMonths(6);
                $toDate = $targetDate->copy();

                $trainingCount = $relevant->user->trainings()
                    ->whereBetween('event_date', [$fromDate, $toDate])
                    ->count();

                // Format month
                $targeted_date = (new \DateTime($relevant->targeted_date))->format('M-y');

                // Basic variables
                $Absenteeism = (int)$relevant->a;
                $referral = (int)$relevant->rgi + $relevant->rgo;
                $visitor = $relevant->v;
                $ariving_on_time = $relevant->l;
                $tyfcb = (int)$relevant->tyfcb;
                $testimonial = (int)$relevant->t;
                $training = $trainingCount;
                $weeks = $relevant->p + $relevant->a + $relevant->l + $relevant->m + $relevant->s;

                $referralPointsArray = [
                    ['points' => 5, 'multiplier' => 0.5],
                    ['points' => 10, 'multiplier' => 0.75],
                    ['points' => 15, 'multiplier' => 1],
                    ['points' => 20, 'multiplier' => 1.2],
                ];

                $referralResult = $this->calculateCategoryPoints($weeks, $referralPointsArray);

                $visitorPointsArray = [
                    ['points' => 5, 'multiplier' => 0.10],
                    ['points' => 10, 'multiplier' => 0.25],
                    ['points' => 15, 'multiplier' => 0.50],
                    ['points' => 20, 'multiplier' => 0.75],
                ];

                $visitorResult = $this->calculateCategoryPoints($weeks, $visitorPointsArray);

                $testimonialPointsArray = [
                    ['points' => 5, 'multiplier' => 0.0000001],
                    ['points' => 10, 'multiplier' => 0.074],
                ];

                $testimonialResult = $this->calculateCategoryPoints($weeks, $testimonialPointsArray);

                $tyfcbPointsArray = [
                    ['points' => 5, 'value' => 500000],
                    ['points' => 10, 'value' => 1000000],
                    ['points' => 15, 'value' => 2000000],
                ];

                $traningPointsArray = [
                    ['points' => 5, 'value' => 1],
                    ['points' => 10, 'value' => 2],
                    ['points' => 15, 'value' => 3],
                ];

                // Build performance array
                $performance[] = [
                    'name' => 'absenteeism',
                    'current_score' => $this->calculateScore(15, 5, $Absenteeism), // total=15, deduct=5 per late
                    'current_data' => $Absenteeism,
                ];

                $performance[] = [
                    'name' => 'arriving on time',
                    'current_score' => $this->calculateArrivingOnTime(5, $ariving_on_time),
                    'current_data' => $ariving_on_time,
                ];

                $performance[] = [
                    'name' => 'visitor',
                    'current_score' => $this->getReferralPoints($visitor, $visitorResult),
                    'current_data' => $visitor, // or some calculation
                ];

                $performance[] = [
                    'name' => 'referrals',
                    'current_score' => $this->getReferralPoints($referral, $referralResult),
                    'current_data' => $referral,
                ];

                $performance[] = [
                    'name' => 'tyfcb',
                    'current_score' => $this->getReferralPoints($tyfcb, $tyfcbPointsArray),
                    'current_data' => $tyfcb,
                ];

                $performance[] = [
                    'name' => 'testimonial',
                    'current_score' => $this->getReferralPoints($testimonial, $testimonialResult),
                    'current_data' => $testimonial,
                ];

                $performance[] = [
                    'name' => 'training',
                    'current_score' => $this->getReferralPoints($training, $traningPointsArray),
                    'current_data' => $training,
                ];

                // $needToDo[] = [
                //     'name' => 'absenteeism',
                //     '5_points' => "-",
                //     '10_points' => "-",
                //     '15_points' => "-",
                //     '20_points' => "-",
                // ];

                // $needToDo[] = [
                //     'name' => 'arriving on time',
                //     '5_points' => "-",
                //     '10_points' => "-",
                //     '15_points' => "-",
                //     '20_points' => "-",
                // ];

                // // dd($referralResult);
                // $needToDo[] = $this->calculateNeedToDo('visitor', $visitor, $visitorResult);
                // $needToDo[] = $this->calculateNeedToDo('referrals', $referral, $referralResult);
                // $needToDo[] = $this->calculateNeedToDo('tyfcb', $tyfcb, $tyfcbPointsArray);
                // $needToDo[] = $this->calculateNeedToDo('testimonial', $testimonial, $testimonialResult);
                // $needToDo[] = $this->calculateNeedToDo('training', $training, $traningPointsArray);
                // $totalScore = array_sum(array_column($performance, 'current_score'));
                // // Define all points keys that must exist
                // $allKeys = ['5_points', '10_points', '15_points', '20_points'];

                // // Normalize each needToDo item
                // foreach ($needToDo as &$item) {
                //     foreach ($allKeys as $key) {
                //         if (!array_key_exists($key, $item)) {
                //             $item[$key] = '-'; // fill missing key with '-'
                //         }
                //     }
                //     // Optional: sort keys so order is consistent
                //     // ksort($item);
                // }

                $monthScore = array_sum(array_column($performance, 'current_score'));
                $totalScore += $monthScore;

                $monthlyReports[] = [
                    'month' => $targeted_date,
                    'performance' => $performance,
                    'total_score' => $monthScore,
                ];
            }

            return response()->json([
                'status' => true,
                'message' => 'Fetched last 6 months user performance successfully!',
                'data' => [
                    'user_info' => [
                        'name' => $user->name,
                        'chapter' => $user->chapter,
                    ],
                    // 'summary' => [
                    //     'months' => $monthlyReports->count() ?? count($monthlyReports),
                    //     'total_score' => $totalScore,
                    //     'average_score' => round($totalScore / count($monthlyReports), 2),
                    // ],
                    'reports' => $monthlyReports,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("UserReport Error :: ", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while generating report.'
            ], 500);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            // Validate request
            $validation = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8',
                'confirm_password' => 'required|string|same:new_password',
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validation->errors()
                ], 200);
            }

            $user = auth()->user();

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Current password is incorrect.'
                ], 400);
            }

            // Prevent reusing the same password
            if (Hash::check($request->new_password, $user->password)) {
                return response()->json([
                    'status' => false,
                    'message' => 'New password cannot be the same as the current password.'
                ], 400);
            }

            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'status' => true,
                'message' => 'Password changed successfully!'
            ], 200);
        } catch (\Exception $e) {
            Log::error("Change Password Error :: ", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while changing password.'
            ], 500);
        }
    }

    public function forgetPassword(Request $request)
    {
        try {
            // ✅ Validate email directly using Laravel's built-in validator
            $validated = $request->validate([
                'email' => 'required|email',
            ], [
                'email.required' => 'Email field is required.',
                'email.email' => 'Invalid Email.',
            ]);

            // ✅ Find user by email
            $user = User::where('email', $validated['email'])->first();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email does not exist.'
                ], 404);
            }

            // ✅ Generate a unique 6-digit OTP
            do {
                $otp = random_int(100000, 999999);
            } while (VerifyOtp::where('otp', $otp)->exists());

            // ✅ Store OTP in database
            VerifyOtp::create([
                'otp' => $otp,
                'email' => $validated['email'],
                'is_verified' => false,
                'type' => 2, // 1: register business, 2: forget password
                'expires_at' => now()->addMinutes(10),
            ]);

            // ✅ Send the email (safe mail sending)
            try {
                // Mail::to($user->email)->send(new ForgetPasswordMail($user->name, $otp));
                Mail::to("anuj@shreenandik.in")->send(new ForgetPasswordMail($user->name, $otp));
            } catch (\Exception $mailException) {
                Log::warning("Failed to send password reset email: " . $mailException->getMessage());
            }

            // ✅ Success response
            return response()->json([
                'status' => true,
                'message' => 'OTP has been sent to your email.',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors
            return response()->json([
                'status' => false,
                'message' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error("Forget password error: " . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        try {
            // ✅ Use Laravel built-in validation instead of manual service if possible
            $validated = $request->validate([
                'email' => 'required|email',
                'otp' => 'required|digits:6',
                'type' => 'required|integer|in:1,2', // 1: register business, 2: forget password
            ], [
                'email.required' => 'Email field is required.',
                'email.email' => 'Please enter a valid email.',
                'otp.required' => 'OTP is required.',
                'otp.digits' => 'Please enter a 6-digit OTP.',
                'type.required' => 'Type is required.',
                'type.in' => 'Invalid type value.',
            ]);

            // ✅ Check if user exists
            $user = User::where('email', $validated['email'])->first();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email does not exist.',
                ], 404);
            }

            // ✅ Fetch latest unverified OTP for that email & type
            $otpRecord = VerifyOtp::where('email', $validated['email'])
                ->where('otp', $validated['otp'])
                ->where('type', $validated['type'])
                ->where('is_verified', 0)
                ->latest('id')
                ->first();

            if (!$otpRecord) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid OTP or already verified.',
                ], 422);
            }

            // ✅ Check OTP expiration
            if (now()->greaterThan($otpRecord->expires_at)) {
                return response()->json([
                    'status' => false,
                    'message' => 'OTP has expired. Please request a new one.',
                ], 410);
            }

            // ✅ Mark OTP as verified
            $otpRecord->update([
                'is_verified' => 1,
                'verify_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'OTP verified successfully.',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Verify OTP error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong. Please try again later.',
            ], 500);
        }
    }

    public function updatePassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string|min:8',
                'cpassword' => 'required|same:password',
            ], [
                'email.required' => 'Email field is required.',
                'email.email' => 'Please enter a valid email address.',
                'password.required' => 'Password field is required.',
                'password.string' => 'Password must be a valid string.',
                'password.min' => 'Password must be at least 8 characters long.',
                'cpassword.required' => 'Confirm Password field is required.',
                'cpassword.same' => 'Confirm Password must match the Password field.',
            ]);

            // ✅ Find user
            $user = User::where('email', $validated['email'])->first();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email does not exist.'
                ], 404);
            }

            // ✅ Update password
            $user->update([
                'password' => Hash::make($validated['password']),
            ]);

            // ✅ Return success
            return response()->json([
                'status' => true,
                'message' => 'Password changed successfully.',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // ❌ Validation errors
            return response()->json([
                'status' => false,
                'message' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // ❌ General exceptions
            Log::error("Update password error: " . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong. Please try again later.',
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            if ($request->user()) {
                $request->user()->token()->revoke();

                return response()->json([
                    'status' => true,
                    'message' => 'Logged out successfully'
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => 'User not authenticated'
            ], 401);
        } catch (\Exception $e) {
            $err = [
                'code' => 'LOGOUT_01',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];

            Log::error("Logout Error :: ", $err);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong during logout'
            ], 500);
        }
    }

    private function calculateCategoryPoints(int|float $value, array $rules): array
    {
        $results = [];

        foreach ($rules as $rule) {
            $results[] = [
                'points' => $rule['points'],
                'value' => isset($rule['multiplier']) ? ceil($value * $rule['multiplier']) : null,
            ];
        }

        return $results;
    }

    private function getReferralPoints(float $referralValue, array $referralRules): int
    {
        $points = 0;

        foreach ($referralRules as $rule) {
            if ($referralValue >= $rule['value']) {
                $points = $rule['points']; // keep updating until max matched value
            }
        }

        return $points;
    }

    private function calculateScore(int $totalPoints, int $deductPerLate, int $lateCount): int
    {
        $score = $totalPoints - ($deductPerLate * $lateCount);

        return max($score, 0); // Ensure minimum is 0
    }

    private function calculateArrivingOnTime(int $totalPoints, int $issueCount): int
    {
        return $issueCount > 0 ? 0 : $totalPoints;
    }

    // private function calculateNeedToDo(string $name, int|float $currentData, array $calculatedResults): array
    // {
    //     $needToDo = ['name' => $name];

    //     foreach ($calculatedResults as $rule) {
    //         $pointKey = ($rule['point'] ?? $rule['points']) . '_points';
    //         // Use the calculated multiplier value as current value
    //         $needToDo[$pointKey] = max(round(($rule['multiplier'] ?? $rule['value']) - $currentData), 0);
    //     }

    //     return $needToDo;
    // }
    private function calculateNeedToDo(string $name, int|float $currentData, array $calculatedResults): array
    {
        $needToDo = ['name' => $name];
        // '1'; // Gray
        // '2'; // Red
        // '3'; // Yellow
        // '4'; // Green
        $temp_static_color = [
            "visitor" => [
                '5_points' => 2,
                '10_points' => 3,
                '15_points' => 4,
                '20_points' => 4,
            ],
            "referrals" => [
                '5_points' => 1,
                '10_points' => 2,
                '15_points' => 3,
                '20_points' => 4,
            ],
            "tyfcb" => [
                '5_points' => 2,
                '10_points' => 3,
                '15_points' => 4,
                '20_points' => 4,
            ],
            "testimonial" => [
                '5_points' => 3,
                '10_points' => 4,
                '15_points' => 4,
                '20_points' => 4,
            ],
            "training" => [
                '5_points' => 2,
                '10_points' => 3,
                '15_points' => 4,
                '20_points' => 4,
            ]
        ];
        foreach ($calculatedResults as $rule) {
            $pointKey = ($rule['point'] ?? $rule['points']) . '_points';

            // Determine the target value
            $target = $rule['multiplier'] ?? $rule['value'] ?? null;

            // If target is null, set '-' otherwise subtract currentData and round up
            $needToDo[$pointKey]['value'] = is_null($target) ? '-' : max(round($target - $currentData), 0);
            if (isset($temp_static_color[$name][$pointKey])) {
                $needToDo[$pointKey]['color_code'] = $temp_static_color[$name][$pointKey];
            }
        }

        return $needToDo;
    }

    private static function getColorByPercentage($percentage)
    {
        if ($percentage >= 70) {
            return '4'; // Green
        } elseif ($percentage >= 50) {
            return '3'; // Yellow
        } elseif ($percentage >= 30) {
            return '2'; // Red
        } else {
            return '1'; // Gray
        }
    }
}
