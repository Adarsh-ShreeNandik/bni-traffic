<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Relevant;
use Illuminate\Support\Facades\Log;

class RelevantController extends Controller
{
    public function index()
    {
        try {
            // Fetch only selected columns
            $relevants = Relevant::select(
                'name',
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
            )
                ->get();

            // Structure data
            $data = $relevants->map(function ($relevant) {
                return [
                    'name'   => $relevant->name,
                    'p'      => $relevant->p,
                    'a'      => $relevant->a,
                    'l'      => $relevant->l,
                    'm'      => $relevant->m,
                    's'      => $relevant->s,
                    'rgi'    => $relevant->rgi,
                    'rgo'    => $relevant->rgo,
                    'rri'    => $relevant->rri,
                    'rro'    => $relevant->rro,
                    'v'      => $relevant->v,
                    '1_2_1'  => $relevant->{'1_2_1'},
                    'tyfcb'  => $relevant->tyfcb,
                    'ceu'    => $relevant->ceu,
                    't'      => $relevant->t,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Relevant data fetched successfully!',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            Log::error("Fetch Relevant Error :: ", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while fetching relevant data.'
            ], 500);
        }
    }
}
