<?php
// backend/api/v1/models/Patient.php
namespace Vyper\Api\V1\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'patients';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        // Personal information
        'first_name',
        'last_name',
        'personal_number',
        'date_of_birth',
        'gender',
        
        // Contact information
        'email',
        'phone',
        'address',
        'postal_code',
        'city',
        'country',
        
        // Emergency contact
        'emergency_contact_name',
        'emergency_contact_phone',
        
        // Care information
        'admission_date',
        'expected_discharge_date',
        'actual_discharge_date',
        'daily_cost',
        'agreement',
        
        // Important note with type
        'important_note',
        'important_note_type',
        
        // Caseworker information (Handläggare)
        'caseworker_first_name',
        'caseworker_last_name',
        'caseworker_municipality',
        'caseworker_phone',
        'caseworker_email',
        
        // Additional notes and status
        'notes',
        'is_active',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
        'date_of_birth' => 'date',
        'admission_date' => 'date',
        'expected_discharge_date' => 'date',
        'actual_discharge_date' => 'date',
        'daily_cost' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get the journals for the patient.
     */
    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }
    
    /**
     * Get the patient's full name.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
    
    /**
     * Get the caseworker's full name.
     *
     * @return string|null
     */
    public function getCaseworkerFullNameAttribute(): ?string
    {
        if (empty($this->caseworker_first_name) && empty($this->caseworker_last_name)) {
            return null;
        }
        
        return "{$this->caseworker_first_name} {$this->caseworker_last_name}";
    }
    
    /**
     * Check if patient has an important note
     * 
     * @return bool
     */
    public function getHasImportantNoteAttribute(): bool
    {
        return !empty($this->important_note);
    }
}