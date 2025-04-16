<?php
// backend/api/v1/controllers/MedicationController.php
namespace Vyper\Api\V1\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Vyper\Api\V1\Models\Medication;
use Vyper\Api\V1\Models\Patient;
use Vyper\Api\V1\Utils\ResponseUtils;
use Vyper\Api\V1\Utils\SecurityUtils;
use Vyper\Helpers;

class MedicationController
{
    /**
     * Get all medications for a patient
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function getPatientMedications(Request $request, Response $response, array $args): Response
    {
        $patientId = $args['id'];

        // Verify patient exists
        $patient = Patient::find($patientId);
        if (!$patient) {
            return ResponseUtils::errorResponse($response, 'Patient not found', 404);
        }

        // Get medications
        $medications = Medication::where('patient_id', $patientId)
                               ->orderBy('name')
                               ->get();

        // Transform data
        $medicationData = [];
        foreach ($medications as $medication) {
            $medicationData[] = $this->transformMedication($medication);
        }

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'medications' => $medicationData
        ]);
    }

    /**
     * Get active medications for a patient
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function getPatientActiveMedications(Request $request, Response $response, array $args): Response
    {
        $patientId = $args['id'];

        // Verify patient exists
        $patient = Patient::find($patientId);
        if (!$patient) {
            return ResponseUtils::errorResponse($response, 'Patient not found', 404);
        }

        $today = date('Y-m-d');
        
        // Get active medications
        $medications = Medication::where('patient_id', $patientId)
                               ->where('start_date', '<=', $today)
                               ->where(function($query) use ($today) {
                                   $query->whereNull('end_date')
                                         ->orWhere('end_date', '>=', $today);
                               })
                               ->orderBy('name')
                               ->get();

        // Transform data
        $medicationData = [];
        foreach ($medications as $medication) {
            $medicationData[] = $this->transformMedication($medication);
        }

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'medications' => $medicationData
        ]);
    }

    /**
     * Create a medication
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function createMedication(Request $request, Response $response, array $args): Response
    {
        $patientId = $args['id'];
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('userId');

        // Verify patient exists
        $patient = Patient::find($patientId);
        if (!$patient) {
            return ResponseUtils::errorResponse($response, 'Patient not found', 404);
        }

        // Validate input
        $errors = $this->validateMedicationData($data);

        if (!empty($errors)) {
            return ResponseUtils::validationErrorResponse($response, $errors);
        }

        // Create medication
        $medication = new Medication();
        $medication->patient_id = $patientId;
        $medication->created_by = $userId;

        $this->mapMedicationData($medication, $data);
        $medication->created_at = Helpers::now();
        $medication->save();

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'Medication created successfully',
            'medication' => $this->transformMedication($medication)
        ], 201);
    }

    /**
     * Get medication by ID
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function getMedicationById(Request $request, Response $response, array $args): Response
    {
        $medicationId = $args['id'];

        $medication = Medication::find($medicationId);

        if (!$medication) {
            return ResponseUtils::errorResponse($response, 'Medication not found', 404);
        }

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'medication' => $this->transformMedication($medication)
        ]);
    }

    /**
     * Update medication
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function updateMedication(Request $request, Response $response, array $args): Response
    {
        $medicationId = $args['id'];
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('userId');

        $medication = Medication::find($medicationId);

        if (!$medication) {
            return ResponseUtils::errorResponse($response, 'Medication not found', 404);
        }

        // Validate input
        $errors = $this->validateMedicationData($data, false);

        if (!empty($errors)) {
            return ResponseUtils::validationErrorResponse($response, $errors);
        }

        // Update medication
        $this->mapMedicationData($medication, $data);
        $medication->updated_by = $userId;
        $medication->updated_at = Helpers::now();
        $medication->save();

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'Medication updated successfully',
            'medication' => $this->transformMedication($medication)
        ]);
    }

    /**
     * Delete medication
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function deleteMedication(Request $request, Response $response, array $args): Response
    {
        $medicationId = $args['id'];

        $medication = Medication::find($medicationId);

        if (!$medication) {
            return ResponseUtils::errorResponse($response, 'Medication not found', 404);
        }

        // Check permissions based on user role
        $userRole = $request->getAttribute('role');
        $userId = $request->getAttribute('userId');

        // Only admin or manager can delete
        if (!in_array($userRole, ['admin', 'manager'])) {
            return ResponseUtils::errorResponse($response, 'You do not have permission to delete this medication', 403);
        }

        // Delete medication
        $medication->delete();

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'Medication deleted successfully'
        ]);
    }

    /**
     * Validate medication data
     *
     * @param array $data Medication data
     * @param bool $isCreating Whether this is a create operation
     * @return array Validation errors
     */
    private function validateMedicationData(array $data, bool $isCreating = true): array
    {
        $errors = [];

        // Required fields
        if (!isset($data['name']) || empty($data['name'])) {
            $errors['name'] = 'Medication name is required';
        }

        if (!isset($data['standardDose']) || empty($data['standardDose'])) {
            $errors['standardDose'] = 'Standard dose is required';
        }

        if (!isset($data['frequency']) || empty($data['frequency'])) {
            $errors['frequency'] = 'Frequency is required';
        }

        if (!isset($data['startDate']) || empty($data['startDate'])) {
            $errors['startDate'] = 'Start date is required';
        }

        // End date must be after start date if provided
        if (!empty($data['endDate']) && !empty($data['startDate'])) {
            $startDate = new \DateTime($data['startDate']);
            $endDate = new \DateTime($data['endDate']);
            
            if ($endDate < $startDate) {
                $errors['endDate'] = 'End date must be after start date';
            }
        }

        return $errors;
    }

    /**
     * Map request data to medication model
     *
     * @param Medication $medication Medication model
     * @param array $data Request data
     */
    private function mapMedicationData(Medication $medication, array $data): void
    {
        if (isset($data['name'])) {
            $medication->name = SecurityUtils::sanitizeInput($data['name']);
        }

        if (isset($data['standardDose'])) {
            $medication->standard_dose = SecurityUtils::sanitizeInput($data['standardDose']);
        }

        if (isset($data['frequency'])) {
            $medication->frequency = SecurityUtils::sanitizeInput($data['frequency']);
        }

        if (isset($data['startDate'])) {
            $medication->start_date = $data['startDate'];
        }

        if (isset($data['endDate'])) {
            $medication->end_date = empty($data['endDate']) ? null : $data['endDate'];
        }

        if (isset($data['instructions'])) {
            $medication->instructions = SecurityUtils::sanitizeInput($data['instructions']);
        }
    }

    /**
     * Transform medication model to API response format
     *
     * @param Medication $medication Medication model
     * @return array Transformed medication data
     */
    private function transformMedication(Medication $medication): array
    {
        return [
            'id' => $medication->id,
            'patientId' => $medication->patient_id,
            'name' => $medication->name,
            'standardDose' => $medication->standard_dose,
            'frequency' => $medication->frequency,
            'startDate' => $medication->start_date,
            'endDate' => $medication->end_date,
            'instructions' => $medication->instructions,
            'isActive' => $medication->isActive(),
            'createdBy' => $medication->created_by,
            'updatedBy' => $medication->updated_by,
            'createdAt' => $medication->created_at,
            'updatedAt' => $medication->updated_at
        ];
    }
}