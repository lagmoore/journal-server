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
        'first_name',
        'last_name',
        'personal_number',
        'date_of_birth',
        'gender',
        'email',
        'phone',
        'address',
        'postal_code',
        'city',
        'country',
        'emergency_contact_name',
        'emergency_contact_phone',
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
}