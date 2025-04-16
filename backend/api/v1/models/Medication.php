<?php
// backend/api/v1/models/Medication.php
namespace Vyper\Api\V1\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Medication extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'medications';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'patient_id',
        'name',
        'standard_dose',
        'frequency',
        'start_date',
        'end_date',
        'instructions',
        'created_by',
        'updated_by',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get the patient that this medication belongs to.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
    
    /**
     * Get the user that created this medication.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Get the user that last updated this medication.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    
    /**
     * Check if medication is active (current)
     *
     * @return bool
     */
    public function isActive(): bool
    {
        $today = date('Y-m-d');
        return $this->start_date <= $today && 
               ($this->end_date === null || $this->end_date >= $today);
    }
    
    /**
     * Get medication journal entries
     */
    public function getJournalEntries()
    {
        return Journal::where('patient_id', $this->patient_id)
                      ->where('entry_type', 'medication')
                      ->where('medication_name', $this->name)
                      ->orderBy('created_at', 'desc')
                      ->get();
    }
    
    /**
     * Get today's medication journal entries
     */
    public function getTodaysEntries()
    {
        $today = date('Y-m-d');
        return Journal::where('patient_id', $this->patient_id)
                      ->where('entry_type', 'medication')
                      ->where('medication_name', $this->name)
                      ->whereDate('created_at', $today)
                      ->orderBy('created_at', 'desc')
                      ->get();
    }
}