<?php
// backend/api/v1/models/Economy.php
namespace Vyper\Api\V1\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Economy extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'economy';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'year',
        'month',
        'actual_income',
        'budget',
        'created_by',
        'updated_by',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'actual_income' => 'decimal:2',
        'budget' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get the user that created the economy record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Get the user that last updated the economy record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    
    /**
     * Calculate predicted income for given year and month based on patient data
     * 
     * @param int $year
     * @param int $month
     * @return float
     */
    public static function calculatePredictedIncome($year, $month)
    {
        // Get all active patients
        $patients = Patient::where('is_active', true)->get();
        
        $totalIncome = 0;
        
        foreach ($patients as $patient) {
            if (!$patient->admission_date || !$patient->incomePerDay) {
                continue;
            }
            
            $admissionDate = new \DateTime($patient->admission_date);
            
            // Determine end date - either actual discharge, expected discharge, or continuing
            $endDate = null;
            if ($patient->actual_discharge_date) {
                $endDate = new \DateTime($patient->actual_discharge_date);
            } elseif ($patient->expected_discharge_date) {
                $endDate = new \DateTime($patient->expected_discharge_date);
            } else {
                // If no end date is set, use the end of current month
                $endDate = new \DateTime("{$year}-{$month}-01");
                $endDate->modify('last day of this month');
            }
            
            // Calculate if the patient stays during this month
            $firstDayOfMonth = new \DateTime("{$year}-{$month}-01");
            $lastDayOfMonth = clone $firstDayOfMonth;
            $lastDayOfMonth->modify('last day of this month');
            
            // Skip if patient was discharged before this month
            if ($endDate < $firstDayOfMonth) {
                continue;
            }
            
            // Skip if patient admission is after this month
            if ($admissionDate > $lastDayOfMonth) {
                continue;
            }
            
            // Calculate start date for income calculation (max of admission date or first day of month)
            $startCalculationDate = $admissionDate > $firstDayOfMonth ? $admissionDate : $firstDayOfMonth;
            
            // Calculate end date for income calculation (min of discharge date or last day of month)
            $endCalculationDate = $endDate < $lastDayOfMonth ? $endDate : $lastDayOfMonth;
            
            // Calculate days in this month
            $daysInterval = $startCalculationDate->diff($endCalculationDate);
            $days = $daysInterval->days + 1; // Include both start and end days
            
            // Add to total income
            $totalIncome += $patient->incomePerDay * $days;
        }
        
        return $totalIncome;
    }
}