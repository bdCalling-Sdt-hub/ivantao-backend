<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getDashboardStatistics(Request $request)
    {
        $period = $request->input('period', 'weekly');
        $now = now();

        switch ($period) {
            case 'monthly':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfMonth();
                $prevStart = $now->copy()->subYear()->startOfYear();
                $prevEnd = $now->copy()->subYear()->endOfMonth();
                break;
            case 'yearly':
                $years = Order::selectRaw('YEAR(created_at) as year')->distinct()->orderBy('year')->pluck('year')->toArray();
                $currentYear = end($years);
                $prevYear = count($years) > 1 ? $years[count($years) - 2] : null;

                $start = Carbon::parse($currentYear . '-01-01')->startOfYear();
                $end = Carbon::parse($currentYear . '-12-31')->endOfYear();
                $prevStart = $prevYear ? Carbon::parse($prevYear . '-01-01')->startOfYear() : null;
                $prevEnd = $prevYear ? Carbon::parse($prevYear . '-12-31')->endOfYear() : null;
                break;
            default: // weekly
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                $prevStart = $now->copy()->subWeek()->startOfWeek();
                $prevEnd = $now->copy()->subWeek()->endOfWeek();
        }

        $providers = User::where('role', 'provider')->whereBetween('created_at', [$start, $end])->count();
        $consumers = User::where('role', 'user')->whereBetween('created_at', [$start, $end])->count();
        $earnings = Order::whereBetween('created_at', [$start, $end])->sum('amount') ?? 0;

        $prevProviders = $prevStart ? User::where('role', 'provider')->whereBetween('created_at', [$prevStart, $prevEnd])->count() : 0;
        $prevConsumers = $prevStart ? User::where('role', 'user')->whereBetween('created_at', [$prevStart, $prevEnd])->count() : 0;
        $prevEarnings = $prevStart ? Order::whereBetween('created_at', [$prevStart, $prevEnd])->sum('amount') ?? 0 : 0;

        $pGrowth = $prevProviders ? (($providers - $prevProviders) / $prevProviders) * 100 : 0;
        $cGrowth = $prevConsumers ? (($consumers - $prevConsumers) / $prevConsumers) * 100 : 0;
        $eGrowth = $prevEarnings ? (($earnings - $prevEarnings) / $prevEarnings) * 100 : 0;

        $pStatus = $pGrowth >= 0 ? 'up' : 'down';
        $cStatus = $cGrowth >= 0 ? 'up' : 'down';
        $eStatus = $eGrowth >= 0 ? 'up' : 'down';

        $pGrowthText = round($pGrowth, 0) . 'k ' . ($pStatus == 'up' ? 'increase' : 'decrease') . ' than last ' . $period;
        $cGrowthText = round($cGrowth, 0) . 'k ' . ($cStatus == 'up' ? 'increase' : 'decrease') . ' than last ' . $period;
        $eGrowthText = round($eGrowth, 0) . 'k ' . ($eStatus == 'down' ? 'decrease' : 'increase') . ' than last ' . $period;

        $revenueData = DB::table('orders')
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m-%d") as date, SUM(amount) as revenue')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $chartData = [];
        if ($period == 'weekly') {
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $chartData[] = ['date' => $date->format('D'), 'revenue' => (float) ($revenueData[$date->toDateString()]->revenue ?? 0)];
            }
        } elseif ($period == 'monthly') {
            for ($month = 1; $month <= 12; $month++) {
                $date = Carbon::createFromDate(now()->year, $month, 1);
                $chartData[] = ['date' => $date->format('M'), 'revenue' => (float) ($revenueData[$date->toDateString()]->revenue ?? 0)];
            }
        } elseif ($period == 'yearly') {
            foreach ($years as $year) {
                $revenue = 0;
                foreach ($revenueData as $key => $item) {
                    if (Carbon::parse($key)->year == $year) {
                        $revenue = $item->revenue;
                        break;
                    }
                }
                $chartData[] = ['date' => (string) $year, 'revenue' => (float) $revenue];
            }
        }

        $totalRevenue = array_sum(array_column($chartData, 'revenue'));
        $prevRevenue = $prevStart ? Order::whereBetween('created_at', [$prevStart, $prevEnd])->sum('amount') ?? 0 : 0;
        $revenueDiff = $totalRevenue - $prevRevenue;
        $formattedDiff = ($revenueDiff >= 0 ? '+' : '-') . '$' . number_format(abs($revenueDiff) / 1000, 1) . 'k';

        $lastData = end($chartData);
        $tooltip = ['revenue' => '$' . number_format(($lastData['revenue'] ?? 0) / 1000, 1) . 'k', 'date' => $end->format('d M, H:i')];

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => $period,
                'providers' => ['total' => $providers, 'growth' => $pGrowthText, 'status' => $pStatus],
                'consumers' => ['total' => $consumers, 'growth' => $cGrowthText, 'status' => $cStatus],
                'earnings' => ['total' => $earnings, 'growth' => $eGrowthText, 'status' => $eStatus],
                'chartData' => $chartData,
                'totalRevenue' => '$' . number_format($totalRevenue / 1000, 1) . 'k',
                'revenueDifference' => $formattedDiff,
                'tooltipData' => $tooltip],
        ]);
    }
}
