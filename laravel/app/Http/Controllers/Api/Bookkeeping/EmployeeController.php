<?php

namespace App\Http\Controllers\Api\Bookkeeping;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    private function business(Request $request)
    {
        return $request->user()->businesses()->firstOrFail();
    }

    public function index(Request $request)
    {
        $employees = $this->business($request)
            ->employees()
            ->with(['branch', 'shifts' => fn($q) => $q->latest()->limit(1)])
            ->where('is_active', true)
            ->get()
            ->map(function ($e) {
                $e->is_clocked_in       = $e->isClockedIn();
                $e->monthly_sales       = $e->totalSalesThisMonth();
                $e->active_shift        = $e->activeShift();
                return $e;
            });

        return response()->json($employees);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'phone'     => 'nullable|string',
            'role'      => 'required|in:manager,cashier,stock_keeper',
            'branch_id' => 'nullable|exists:branches,id',
            'hired_at'  => 'nullable|date',
            'permissions' => 'nullable|array',
        ]);

        $employee = $this->business($request)->employees()->create($validated);
        return response()->json($employee, 201);
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'role'      => 'sometimes|in:manager,cashier,stock_keeper',
            'branch_id' => 'nullable|exists:branches,id',
            'is_active' => 'sometimes|boolean',
            'permissions' => 'nullable|array',
        ]);
        $employee->update($validated);
        return response()->json($employee);
    }

    public function clockIn(Request $request, Employee $employee)
    {
        if ($employee->isClockedIn()) {
            return response()->json([
                'message' => 'Employee is already clocked in.',
                'shift'   => $employee->activeShift(),
            ], 422);
        }

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'note'      => 'nullable|string',
        ]);

        $shift = Shift::create([
            'employee_id' => $employee->id,
            'branch_id'   => $validated['branch_id'],
            'business_id' => $employee->business_id,
            'clock_in'    => now(),
            'note'        => $validated['note'] ?? null,
        ]);

        return response()->json([
            'message'  => "{$employee->name} ameingia kazini.",
            'shift'    => $shift,
            'clock_in' => $shift->clock_in->format('H:i'),
        ], 201);
    }

    public function clockOut(Request $request, Employee $employee)
    {
        $shift = $employee->activeShift();

        if (!$shift) {
            return response()->json(['message' => 'Employee is not clocked in.'], 422);
        }

        $shift->update(['clock_out' => now()]);

        return response()->json([
            'message'   => "{$employee->name} ametoka kazini.",
            'shift'     => $shift->fresh(),
            'duration'  => $shift->durationFormatted(),
            'total_sales' => $shift->total_sales,
        ]);
    }

    public function shifts(Request $request, Employee $employee)
    {
        $shifts = $employee->shifts()
            ->orderBy('clock_in', 'desc')
            ->paginate(20);

        return response()->json($shifts);
    }

    public function performance(Request $request)
    {
        $business = $this->business($request);
        $month    = $request->query('month', now()->month);
        $year     = $request->query('year', now()->year);

        $performance = DB::table('shifts')
            ->join('employees', 'employees.id', '=', 'shifts.employee_id')
            ->where('shifts.business_id', $business->id)
            ->whereMonth('shifts.clock_in', $month)
            ->whereYear('shifts.clock_in', $year)
            ->whereNotNull('shifts.clock_out')
            ->groupBy('employees.id', 'employees.name', 'employees.role')
            ->selectRaw('
                employees.id,
                employees.name,
                employees.role,
                COUNT(shifts.id) as shifts_worked,
                SUM(EXTRACT(EPOCH FROM (shifts.clock_out - shifts.clock_in))/3600) as hours_worked,
                SUM(shifts.total_sales) as total_sales,
                SUM(shifts.sales_count) as total_transactions,
                AVG(shifts.total_sales) as avg_sales_per_shift
            ')
            ->orderByDesc('total_sales')
            ->get();

        return response()->json([
            'month'       => $month,
            'year'        => $year,
            'performance' => $performance,
        ]);
    }
}