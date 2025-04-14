<?php
// backend/api/v1/controllers/PatientController.php
namespace Vyper\Api\V1\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Vyper\Api\V1\Models\Patient;
use Vyper\Api\V1\Utils\ResponseUtils;
use Vyper\Api\V1\Utils\SecurityUtils;
use Vyper\Helpers;

class PatientController
{
    /**
     * Get all patients
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function getAllPatients(Request $request, Response $response): Response
    {
        // Get query parameters for filtering and sorting
        $params = $request->getQueryParams();
        $status = $params['status'] ?? 'all';
        $sortBy = $params['sortBy'] ?? 'lastName';
        $sortOrder = $params['sortOrder'] ?? 'asc';
        $search = $params['search'] ?? '';

        // Start with a base query
        $query = Patient::query();

        // Apply status filter
        if ($status !== 'all') {
            $isActive = $status === 'active';
            $query->where('is_active', $isActive);
        }

        // Apply search filter if provided
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhere('personal_number', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"]);
            });
        }

        // Determine sort field
        $sortField = match ($sortBy) {
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'personalNumber' => 'personal_number',
            'createdAt' => 'created_at',
            default => 'last_name'
        };

        // Apply sorting
        $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');

        // Get patients
        $patients = $query->get();

        // Transform data
        $patientData = [];
        foreach ($patients as $patient) {
            $patientData[] = [
                'id' => $patient->id,
                'firstName' => $patient->first_name,
                'lastName' => $patient->last_name,
                'personalNumber' => $patient->personal_number,
                'dateOfBirth' => $patient->date_of_birth,
                'gender' => $patient->gender,
                'email' => $patient->email,
                'phone' => $patient->phone,
                'address' => $patient->address,
                'postalCode' => $patient->postal_code,
                'city' => $patient->city,
                'country' => $patient->country,
                'emergencyContactName' => $patient->emergency_contact_name,
                'emergencyContactPhone' => $patient->emergency_contact_phone,
                'notes' => $patient->notes,
                'isActive' => (bool) $patient->is_active,
                'createdAt' => $patient->created_at,
                'updatedAt' => $patient->updated_at
            ];
        }

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'patients' => $patientData
        ]);
    }

    /**
     * Create a patient
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function createPatient(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // Validate input
        $errors = $this->validatePatientData($data);

        if (!empty($errors)) {
            return ResponseUtils::validationErrorResponse($response, $errors);
        }

        // Create patient
        $patient = new Patient();
        $this->mapPatientData($patient, $data);
        $patient->created_at = Helpers::now();
        $patient->save();

        // Return transformed data
        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'Patient created successfully',
            'patient' => $this->transformPatient($patient)
        ], 201);
    }

    /**
     * Get patient by ID
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function getPatientById(Request $request, Response $response, array $args): Response
    {
        $patientId = $args['id'];

        $patient = Patient::find($patientId);

        if (!$patient) {
            return ResponseUtils::errorResponse($response, 'Patient not found', 404);
        }

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'patient' => $this->transformPatient($patient)
        ]);
    }

    /**
     * Update patient
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function updatePatient(Request $request, Response $response, array $args): Response
    {
        $patientId = $args['id'];
        $data = $request->getParsedBody();

        $patient = Patient::find($patientId);

        if (!$patient) {
            return ResponseUtils::errorResponse($response, 'Patient not found', 404);
        }

        // Validate input
        $errors = $this->validatePatientData($data, false);

        if (!empty($errors)) {
            return ResponseUtils::validationErrorResponse($response, $errors);
        }

        // Update patient
        $this->mapPatientData($patient, $data);
        $patient->updated_at = Helpers::now();
        $patient->save();

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'Patient updated successfully',
            'patient' => $this->transformPatient($patient)
        ]);
    }

    /**
     * Delete patient
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function deletePatient(Request $request, Response $response, array $args): Response
    {
        $patientId = $args['id'];

        $patient = Patient::find($patientId);

        if (!$patient) {
            return ResponseUtils::errorResponse($response, 'Patient not found', 404);
        }

        // Check if patient has journals
        $journalCount = $patient->journals()->count();

        if ($journalCount > 0) {
            // Soft delete approach - mark as inactive instead of deleting
            $patient->is_active = false;
            $patient->updated_at = Helpers::now();
            $patient->save();

            return ResponseUtils::successResponse($response, [
                'success' => true,
                'message' => 'Patient has journals and was marked as inactive',
                'softDelete' => true
            ]);
        }

        // Hard delete if no journals
        $patient->delete();

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'Patient deleted successfully'
        ]);
    }

    /**
     * Validate patient data
     *
     * @param array $data Patient data
     * @param bool $isCreating Whether this is a create operation
     * @return array Validation errors
     */
    private function validatePatientData(array $data, bool $isCreating = true): array
    {
        $errors = [];

        // Only first name and last name are required fields
        if (!isset($data['firstName']) || empty(trim($data['firstName']))) {
            $errors['firstName'] = 'First name is required';
        }

        if (!isset($data['lastName']) || empty(trim($data['lastName']))) {
            $errors['lastName'] = 'Last name is required';
        }

        // Validate optional fields if provided
        if (isset($data['personalNumber']) && !empty($data['personalNumber'])) {
            // Swedish personal number format: YYYYMMDD-XXXX or YYYYMMDDXXXX
            if (!preg_match('/^(\d{8}[-+]?\d{4}|\d{12})$/', str_replace(' ', '', $data['personalNumber']))) {
                $errors['personalNumber'] = 'Invalid personal number format';
            }
        }

        if (isset($data['email']) && !empty($data['email'])) {
            if (!SecurityUtils::validateEmail($data['email'])) {
                $errors['email'] = 'Invalid email format';
            }
        }

        // Validate phone if provided
        if (isset($data['phone']) && !empty($data['phone'])) {
            // Simple phone format validation - can be expanded based on requirements
            if (!preg_match('/^[0-9\s\-\+\(\)]{5,20}$/', $data['phone'])) {
                $errors['phone'] = 'Invalid phone number format';
            }
        }

        // Validate postal code if provided
        if (isset($data['postalCode']) && !empty($data['postalCode'])) {
            // Swedish postal code format: XXXXX or XXX XX
            if (!preg_match('/^(\d{3}\s?\d{2}|\d{5})$/', $data['postalCode'])) {
                $errors['postalCode'] = 'Invalid postal code format';
            }
        }

        return $errors;
    }

    /**
     * Map request data to patient model
     *
     * @param Patient $patient Patient model
     * @param array $data Request data
     */
    private function mapPatientData(Patient $patient, array $data): void
    {
        // Map basic fields
        if (isset($data['firstName'])) {
            $patient->first_name = SecurityUtils::sanitizeInput($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $patient->last_name = SecurityUtils::sanitizeInput($data['lastName']);
        }

        if (isset($data['personalNumber'])) {
            $patient->personal_number = SecurityUtils::sanitizeInput($data['personalNumber']);
        }

        if (isset($data['dateOfBirth'])) {
            $patient->date_of_birth = $data['dateOfBirth'];
        }

        if (isset($data['gender'])) {
            $patient->gender = $data['gender'];
        }

        // Contact information
        if (isset($data['email'])) {
            $patient->email = SecurityUtils::sanitizeInput($data['email']);
        }

        if (isset($data['phone'])) {
            $patient->phone = SecurityUtils::sanitizeInput($data['phone']);
        }

        if (isset($data['address'])) {
            $patient->address = SecurityUtils::sanitizeInput($data['address']);
        }

        if (isset($data['postalCode'])) {
            $patient->postal_code = SecurityUtils::sanitizeInput($data['postalCode']);
        }

        if (isset($data['city'])) {
            $patient->city = SecurityUtils::sanitizeInput($data['city']);
        }

        if (isset($data['country'])) {
            $patient->country = SecurityUtils::sanitizeInput($data['country']);
        }

        // Emergency contact
        if (isset($data['emergencyContactName'])) {
            $patient->emergency_contact_name = SecurityUtils::sanitizeInput($data['emergencyContactName']);
        }

        if (isset($data['emergencyContactPhone'])) {
            $patient->emergency_contact_phone = SecurityUtils::sanitizeInput($data['emergencyContactPhone']);
        }

        // Notes
        if (isset($data['notes'])) {
            $patient->notes = SecurityUtils::sanitizeInput($data['notes']);
        }

        // Status
        if (isset($data['isActive'])) {
            $patient->is_active = (bool) $data['isActive'];
        }
    }

    /**
     * Transform patient model to API response format
     *
     * @param Patient $patient Patient model
     * @return array Transformed patient data
     */
    private function transformPatient(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'firstName' => $patient->first_name,
            'lastName' => $patient->last_name,
            'personalNumber' => $patient->personal_number,
            'dateOfBirth' => $patient->date_of_birth,
            'gender' => $patient->gender,
            'email' => $patient->email,
            'phone' => $patient->phone,
            'address' => $patient->address,
            'postalCode' => $patient->postal_code,
            'city' => $patient->city,
            'country' => $patient->country,
            'emergencyContactName' => $patient->emergency_contact_name,
            'emergencyContactPhone' => $patient->emergency_contact_phone,
            'notes' => $patient->notes,
            'isActive' => (bool) $patient->is_active,
            'createdAt' => $patient->created_at,
            'updatedAt' => $patient->updated_at
        ];
    }
}