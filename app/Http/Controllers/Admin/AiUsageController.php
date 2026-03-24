<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiTokenUsage;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AiUsageController extends Controller
{
    /**
     * Daily aggregated token usage for charting.
     *
     * Query params:
     * - from: YYYY-MM-DD
     * - to: YYYY-MM-DD
     */
    public function daily(Request $request)
    {
        $providers = ['openai', 'gemini', 'groq'];

        $defaultTo = now()->toDateString();
        $defaultFrom = now()->subDays(29)->toDateString(); // last 30 days

        try {
            $from = $request->query('from', $defaultFrom);
            $to = $request->query('to', $defaultTo);

            $fromDate = Carbon::parse($from)->startOfDay();
            $toDate = Carbon::parse($to)->startOfDay();
        } catch (\Throwable) {
            $fromDate = Carbon::parse($defaultFrom)->startOfDay();
            $toDate = Carbon::parse($defaultTo)->startOfDay();
        }

        if ($fromDate->greaterThan($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        // Keep the chart light: cap at 60 days.
        $maxDays = 60;
        $diffDays = $fromDate->diffInDays($toDate);
        if ($diffDays > $maxDays) {
            $toDate = $fromDate->copy()->addDays($maxDays);
        }

        $labels = [];
        $series = [];
        foreach ($providers as $provider) {
            $series[$provider] = [];
        }

        $cursor = $fromDate->copy();
        while ($cursor->lessThanOrEqualTo($toDate)) {
            $labels[] = $cursor->toDateString();
            foreach ($providers as $provider) {
                $series[$provider][] = 0;
            }
            $cursor->addDay();
        }

        $rows = AiTokenUsage::query()
            ->selectRaw('provider, DATE(created_at) as day, SUM(total_tokens) as total_tokens')
            ->whereIn('provider', $providers)
            ->whereBetween('created_at', [$fromDate->toDateTimeString(), $toDate->toDateTimeString()])
            ->groupBy('provider', 'day')
            ->get();

        $labelIndex = array_flip($labels);

        foreach ($rows as $row) {
            $provider = (string) $row->provider;
            $day = (string) $row->day;

            if (!isset($labelIndex[$day]) || !isset($series[$provider])) {
                continue;
            }

            $series[$provider][$labelIndex[$day]] = (int) $row->total_tokens;
        }

        return response()->json([
            'labels' => $labels,
            'series' => $series,
            'meta' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
        ]);
    }
}

