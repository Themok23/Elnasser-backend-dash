<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BranchLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BranchLocationController extends Controller
{
    /**
     * Get nearest branch to user's location.
     *
     * Request body:
     * - latitude (required)
     * - longitude (required)
     * - limit (optional, default 1) number of nearest branches to return
     */
    public function nearest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => \App\CentralLogics\Helpers::error_processor($validator)], 403);
        }

        $lat = (float) $request->input('latitude');
        $lng = (float) $request->input('longitude');
        $limit = (int) ($request->input('limit', 1));

        $totalBranches = BranchLocation::query()->count();
        if ($totalBranches === 0) {
            return response()->json([
                'user_location' => ['latitude' => $lat, 'longitude' => $lng],
                'nearest' => null,
                'branches' => [],
                'branches_total' => 0,
                'message' => 'No branches found. Run migrations and sync branch_locations in production.',
            ], 200);
        }

        // Haversine formula (km)
        $distanceSql = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';

        $branches = BranchLocation::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('branch_locations.*')
            ->selectRaw($distanceSql . ' as distance_km', [$lat, $lng, $lat])
            ->orderBy('distance_km')
            ->limit($limit)
            ->get();

        $nearest = $branches->first();

        return response()->json([
            'user_location' => ['latitude' => $lat, 'longitude' => $lng],
            'nearest' => $nearest,
            'branches' => $branches,
            'branches_total' => $totalBranches,
        ]);
    }
}


