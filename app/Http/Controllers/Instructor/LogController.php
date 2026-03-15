<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Log;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();

        $logs = Log::where('user_id', auth()->id())
            ->with('user')
            ->when($search, fn ($query, $term) => $query->where('description', 'like', "%{$term}%"))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Instructor/Logs/Index', [
            'logs' => $logs,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
