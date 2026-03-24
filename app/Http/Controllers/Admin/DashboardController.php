<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiTokenUsage;
use Carbon\Carbon;
use App\Models\Department;
use App\Models\Log;
use App\Models\Professor;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'professors' => Professor::count(),
            'students' => User::where('role', 'student')->count(),
            'departments' => Department::count(),
            'sections' => Section::count(),
            'subjects' => Subject::count(),
        ];

        $logs = Log::with('user')->latest()->take(5)->get();

        $from = request()->query('from');
        $to = request()->query('to');

        $aiUsage = $this->buildDailyUsage($from, $to);

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'logs' => $logs,
            'aiUsage' => $aiUsage,
        ]);
    }

    protected function buildDailyUsage(?string $from, ?string $to): array
    {
        $providers = ['openai', 'gemini', 'groq'];

        $defaultTo = now()->toDateString();
        $defaultFrom = now()->subDays(29)->toDateString(); // last 30 days

        try {
            $fromDate = Carbon::parse($from ?? $defaultFrom)->startOfDay();
            $toDate = Carbon::parse($to ?? $defaultTo)->startOfDay();
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

        return [
            'labels' => $labels,
            'series' => $series,
            'meta' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
        ];
    }
}

