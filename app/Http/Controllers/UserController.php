<?php

namespace App\Http\Controllers;

use App\Models\Relevant;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
                return response()->json([
                    'status' => false,
                    'message' => 'No relevant record found.'
                ], 404);
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
            }

            $targeted_date = $relevant->targeted_date ?? null;
            if ($targeted_date) {
                // Create DateTime object from your current format (dd/mm/yy)
                $date = new \DateTime($targeted_date);  // create DateTime object
                $targetDate = \Carbon\Carbon::parse($targeted_date);
                $targeted_date = $date->format('M-y'); // format as "Sep-25"
                $currentDate = \Carbon\Carbon::now()->startOfDay();
                $period = \Carbon\CarbonPeriod::create($currentDate, $targetDate);

                $wednesdayCount = collect($period)->filter(function ($date) {
                    return $date->isWednesday();
                })->count();
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

            $needToDo[] = [
                'name' => 'absenteeism',
                '5_points' => "-",
                '10_points' => "-",
                '15_points' => "-",
                '20_points' => "-",
            ];

            $needToDo[] = [
                'name' => 'arriving on time',
                '5_points' => "-",
                '10_points' => "-",
                '15_points' => "-",
                '20_points' => "-",
            ];

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
                        $item[$key] = '-'; // fill missing key with '-'
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
                    'wednesdayCount' => $wednesdayCount
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

            // Select only required fields
            $userData = User::where('id', $user->id)
                ->select('id', 'first_name', 'last_name', 'email', 'phone', 'chapter', 'region_name', 'join_date')
                ->first();

            if (!$userData) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'User profile fetched successfully!',
                'data' => $userData
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


    // public function userReport()
    // {
    //     try {
    //         $user = Auth::user();
    //         $userId = $user->id;

    //         // Fetch last 6 months' relevant data
    //         $relevants = Relevant::with('user')
    //             ->where('user_id', $userId)
    //             ->whereBetween('targeted_date', [
    //                 now()->subMonths(6)->startOfDay(),
    //                 now()->endOfDay()
    //             ])
    //             ->orderBy('targeted_date', 'desc')
    //             ->get();

    //         if ($relevants->isEmpty()) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'No relevant records found in the last 6 months.'
    //             ], 404);
    //         }

    //         $monthlyReports = [];
    //         $totalScore = 0;

    //         foreach ($relevants as $relevant) {
    //             // Calculate training count for the past 6 months relative to this record
    //             $targetDate = \Carbon\Carbon::parse($relevant->targeted_date);
    //             $fromDate = $targetDate->copy()->subMonths(6);
    //             $toDate = $targetDate->copy();

    //             $trainingCount = $relevant->user->trainings()
    //                 ->whereBetween('event_date', [$fromDate, $toDate])
    //                 ->count();

    //             // Format month
    //             $targeted_date = (new \DateTime($relevant->targeted_date))->format('M-y');

    //             // Basic variables
    //             $Absenteeism = (int)$relevant->a;
    //             $referral = (int)$relevant->rgi + $relevant->rgo;
    //             $visitor = $relevant->v;
    //             $ariving_on_time = $relevant->l;
    //             $tyfcb = (int)$relevant->tyfcb;
    //             $testimonial = (int)$relevant->t;
    //             $training = $trainingCount;
    //             $weeks = $relevant->p + $relevant->a + $relevant->l + $relevant->m + $relevant->s;

    //             $referralPointsArray = [
    //                 ['points' => 5, 'multiplier' => 0.5],
    //                 ['points' => 10, 'multiplier' => 0.75],
    //                 ['points' => 15, 'multiplier' => 1],
    //                 ['points' => 20, 'multiplier' => 1.2],
    //             ];

    //             $referralResult = $this->calculateCategoryPoints($weeks, $referralPointsArray);

    //             $visitorPointsArray = [
    //                 ['points' => 5, 'multiplier' => 0.10],
    //                 ['points' => 10, 'multiplier' => 0.25],
    //                 ['points' => 15, 'multiplier' => 0.50],
    //                 ['points' => 20, 'multiplier' => 0.75],
    //             ];

    //             $visitorResult = $this->calculateCategoryPoints($weeks, $visitorPointsArray);

    //             $testimonialPointsArray = [
    //                 ['points' => 5, 'multiplier' => 0.0000001],
    //                 ['points' => 10, 'multiplier' => 0.074],
    //             ];

    //             $testimonialResult = $this->calculateCategoryPoints($weeks, $testimonialPointsArray);

    //             $tyfcbPointsArray = [
    //                 ['points' => 5, 'value' => 500000],
    //                 ['points' => 10, 'value' => 1000000],
    //                 ['points' => 15, 'value' => 2000000],
    //             ];

    //             $traningPointsArray = [
    //                 ['points' => 5, 'value' => 1],
    //                 ['points' => 10, 'value' => 2],
    //                 ['points' => 15, 'value' => 3],
    //             ];

    //             // Build performance array
    //             $performance[] = [
    //                 'name' => 'absenteeism',
    //                 'current_score' => $this->calculateScore(15, 5, $Absenteeism), // total=15, deduct=5 per late
    //                 'current_data' => $Absenteeism,
    //             ];

    //             $performance[] = [
    //                 'name' => 'arriving on time',
    //                 'current_score' => $this->calculateArrivingOnTime(5, $ariving_on_time),
    //                 'current_data' => $ariving_on_time,
    //             ];

    //             $performance[] = [
    //                 'name' => 'visitor',
    //                 'current_score' => $this->getReferralPoints($visitor, $visitorResult),
    //                 'current_data' => $visitor, // or some calculation
    //             ];

    //             $performance[] = [
    //                 'name' => 'referrals',
    //                 'current_score' => $this->getReferralPoints($referral, $referralResult),
    //                 'current_data' => $referral,
    //             ];

    //             $performance[] = [
    //                 'name' => 'tyfcb',
    //                 'current_score' => $this->getReferralPoints($tyfcb, $tyfcbPointsArray),
    //                 'current_data' => $tyfcb,
    //             ];

    //             $performance[] = [
    //                 'name' => 'testimonial',
    //                 'current_score' => $this->getReferralPoints($testimonial, $testimonialResult),
    //                 'current_data' => $testimonial,
    //             ];

    //             $performance[] = [
    //                 'name' => 'training',
    //                 'current_score' => $this->getReferralPoints($training, $traningPointsArray),
    //                 'current_data' => $training,
    //             ];

    //             $needToDo[] = [
    //                 'name' => 'absenteeism',
    //                 '5_points' => "-",
    //                 '10_points' => "-",
    //                 '15_points' => "-",
    //                 '20_points' => "-",
    //             ];

    //             $needToDo[] = [
    //                 'name' => 'arriving on time',
    //                 '5_points' => "-",
    //                 '10_points' => "-",
    //                 '15_points' => "-",
    //                 '20_points' => "-",
    //             ];

    //             // dd($referralResult);
    //             $needToDo[] = $this->calculateNeedToDo('visitor', $visitor, $visitorResult);
    //             $needToDo[] = $this->calculateNeedToDo('referrals', $referral, $referralResult);
    //             $needToDo[] = $this->calculateNeedToDo('tyfcb', $tyfcb, $tyfcbPointsArray);
    //             $needToDo[] = $this->calculateNeedToDo('testimonial', $testimonial, $testimonialResult);
    //             $needToDo[] = $this->calculateNeedToDo('training', $training, $traningPointsArray);
    //             $totalScore = array_sum(array_column($performance, 'current_score'));
    //             // Define all points keys that must exist
    //             $allKeys = ['5_points', '10_points', '15_points', '20_points'];

    //             // Normalize each needToDo item
    //             foreach ($needToDo as &$item) {
    //                 foreach ($allKeys as $key) {
    //                     if (!array_key_exists($key, $item)) {
    //                         $item[$key] = '-'; // fill missing key with '-'
    //                     }
    //                 }
    //                 // Optional: sort keys so order is consistent
    //                 // ksort($item);
    //             }

    //             $monthScore = array_sum(array_column($performance, 'current_score'));
    //             $totalScore += $monthScore;

    //             $monthlyReports[] = [
    //                 'month' => $targeted_date,
    //                 'performance' => $performance,
    //                 'need_to_do' => $needToDo,
    //                 'total_score' => $monthScore,
    //             ];
    //         }

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Fetched last 6 months user performance successfully!',
    //             'data' => [
    //                 'user_info' => [
    //                     'name' => $user->name,
    //                     'chapter' => $user->chapter,
    //                 ],
    //                 // 'summary' => [
    //                 //     'months' => $monthlyReports->count() ?? count($monthlyReports),
    //                 //     'total_score' => $totalScore,
    //                 //     'average_score' => round($totalScore / count($monthlyReports), 2),
    //                 // ],
    //                 'reports' => $monthlyReports,
    //             ],
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error("UserReport Error :: ", [
    //             'message' => $e->getMessage(),
    //             'file' => $e->getFile(),
    //             'line' => $e->getLine(),
    //         ]);

    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Something went wrong while generating report.'
    //         ], 500);
    //     }
    // }


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

        foreach ($calculatedResults as $rule) {
            $pointKey = ($rule['point'] ?? $rule['points']) . '_points';

            // Determine the target value
            $target = $rule['multiplier'] ?? $rule['value'] ?? null;

            // If target is null, set '-' otherwise subtract currentData and round up
            $needToDo[$pointKey] = is_null($target) ? '-' : max(round($target - $currentData), 0);
        }

        return $needToDo;
    }
}
