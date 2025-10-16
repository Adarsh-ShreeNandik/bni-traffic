<?php

namespace App\Http\Controllers;

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
                'data' => [
                    'users' => $data,
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
}
