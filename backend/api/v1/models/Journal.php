<?php
// backend/api/v1/models/Journal.php
namespace Vyper\Api\V1\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Journal extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'journals';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'patient_id',
        'title',
        'content',
        'category',
        'status',
        'entry_type',
        'medication_name',
        'medication_dose',
        'medication_time',
        'test_type',
        'test_method',
        'test_result',
        'positive_substances',
        'incident_severity',
        'incident_details',
        'created_by',
        'updated_by',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'medication_time' => 'datetime',
    ];
    
    /**
     * Get the patient that owns the journal.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
    
    /**
     * Get the user that created the journal.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Get the user that last updated the journal.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    
    /**
     * Scope a query to filter by entry type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('entry_type', $type);
    }
    
    /**
     * Check if this is a medication entry
     *
     * @return bool
     */
    public function isMedication(): bool
    {
        return $this->entry_type === 'medication';
    }
    
    /**
     * Check if this is a drug test entry
     *
     * @return bool
     */
    public function isDrugTest(): bool
    {
        return $this->entry_type === 'drug_test';
    }
    
    /**
     * Check if this is an incident entry
     *
     * @return bool
     */
    public function isIncident(): bool
    {
        return $this->entry_type === 'incident';
    }
    
    /**
     * Check if this is a standard note entry
     *
     * @return bool
     */
    public function isNote(): bool
    {
        return $this->entry_type === 'note';
    }
    
    /**
     * Get array of positive substances (if drug test)
     *
     * @return array
     */
    public function getPositiveSubstancesArray(): array
    {
        if (!$this->positive_substances) {
            return [];
        }
        
        return json_decode($this->positive_substances, true) ?? [];
    }
}