<?php
// backend/api/v1/controllers/JournalController.php
namespace Vyper\Api\V1\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Vyper\Api\V1\Models\Journal;
use Vyper\Api\V1\Models\Patient;
use Vyper\Api\V1\Models\User;
use Vyper\Api\V1\Models\Medication;
use Vyper\Api\V1\Utils\ResponseUtils;
use Vyper\Api\V1\Utils\SecurityUtils;
use Vyper\Helpers;

class JournalController
{
    /**
     * Get all journals
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function getAllJournals(Request $request, Response $response): Response
    {
        // Get query parameters for filtering and sorting
        $params = $request->getQueryParams();
        $status = $params['status'] ?? 'all';
        $category = $params['category'] ?? 'all';
        $entryType = $params['entryType'] ?? 'all';
        $patientId = $params['patientId'] ?? 'all';
        $sortBy = $params['sortBy'] ?? 'newest';
        $search = $params['search'] ?? '';

        // Get current user ID for permissions
        $userId = $request->getAttribute('userId');

        // Start with a base query
        $query = Journal::query();

        // Apply status filter
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Apply category filter
        if ($category !== 'all') {
            $query->where('category', $category);
        }

        // Apply entry type filter
        if ($entryType !== 'all') {
            $query->where('entry_type', $entryType);
        }

        // Apply patient filter
        if ($patientId !== 'all') {
            $query->where('patient_id', $patientId);
        }

        // Apply search filter if provided
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%$search%")
                    ->orWhere('content', 'LIKE', "%$search%")
                    ->orWhere('medication_name', 'LIKE', "%$search%");
            });
        }

        // Apply sorting
        switch ($sortBy) {
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'title':
                $query->orderBy('title', 'asc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        // Get journals
        $journals = $query->get();

        // Transform data
        $journalData = [];
        foreach ($journals as $journal) {
            $journalData[] = $this->transformJournal($journal);
        }

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'journals' => $journalData
        ]);
    }

    /**
     * Get all journals for a specific patient
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function getPatientJournals(Request $request, Response $response, array $args): Response
    {
        $patientId = $args['id'];

        // Verify patient exists
        $patient = Patient::find($patientId);
        if (!$patient) {
            return ResponseUtils::errorResponse($response, 'Patient not found', 404);
        }

        // Get query parameters for filtering and sorting
        $params = $request->getQueryParams();
        $status = $params['status'] ?? 'all';
        $category = $params['category'] ?? 'all';
        $entryType = $params['entryType'] ?? 'all';
        $sortBy = $params['sortBy'] ?? 'newest';
        $search = $params['search'] ?? '';

        // Start with a base query for patient journals
        $query = Journal::where('patient_id', $patientId);

        // Apply status filter
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Apply category filter
        if ($category !== 'all') {
            $query->where('category', $category);
        }

        // Apply entry type filter
        if ($entryType !== 'all') {
            $query->where('entry_type', $entryType);
        }

        // Apply search filter if provided
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%$search%")
                    ->orWhere('content', 'LIKE', "%$search%")
                    ->orWhere('medication_name', 'LIKE', "%$search%");
            });
        }

        // Apply sorting
        switch ($sortBy) {
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'title':
                $query->orderBy('title', 'asc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        // Get patient journals
        $journals = $query->get();

        // Get patient medications
        $medications = Medication::where('patient_id', $patientId)
            ->orderBy('name')
            ->get();

        // Transform data
        $journalData = [];
        foreach ($journals as $journal) {
            $journalData[] = $this->transformJournal($journal);
        }

        // Transform medications
        $medicationData = [];
        foreach ($medications as $medication) {
            $medicationData[] = [
                'id' => $medication->id,
                'name' => $medication->name,
                'standardDose' => $medication->standard_dose,
                'frequency' => $medication->frequency,
                'startDate' => $medication->start_date,
                'endDate' => $medication->end_date,
                'instructions' => $medication->instructions,
                'isActive' => $medication->isActive(),
                'createdAt' => $medication->created_at,
                'updatedAt' => $medication->updated_at
            ];
        }

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'journals' => $journalData,
            'medications' => $medicationData,
            'patient' => [
                'id' => $patient->id,
                'firstName' => $patient->first_name,
                'lastName' => $patient->last_name
            ]
        ]);
    }

    /**
     * Create a journal
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function createJournal(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('userId');

        // Verify patient exists
        if (!isset($data['patientId']) || empty($data['patientId'])) {
            return ResponseUtils::errorResponse($response, 'Patient ID is required', 400);
        }

        $patient = Patient::find($data['patientId']);
        if (!$patient) {
            return ResponseUtils::errorResponse($response, 'Patient not found', 404);
        }

        // Determine entry type and validate
        $entryType = $data['entryType'] ?? 'note';
        $validationErrors = $this->validateJournalData($data, $entryType);

        if (!empty($validationErrors)) {
            return ResponseUtils::validationErrorResponse($response, $validationErrors);
        }

        // Create journal
        $journal = new Journal();
        $journal->patient_id = $data['patientId'];
        $journal->created_by = $userId;
        $journal->status = $data['status'] ?? 'draft';
        $journal->entry_type = $entryType;

        // Set common fields
        $this->mapJournalData($journal, $data);

        // Set type-specific fields
        if ($entryType === 'medication') {
            $this->mapMedicationData($journal, $data);
        } elseif ($entryType === 'drug_test') {
            $this->mapDrugTestData($journal, $data);
        } elseif ($entryType === 'incident') {
            $this->mapIncidentData($journal, $data);
        }

        $journal->created_at = Helpers::now();
        $journal->save();

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'Journal created successfully',
            'journal' => $this->transformJournal($journal)
        ], 201);
    }

    /**
     * Create a journal for a specific patient
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function createPatientJournal(Request $request, Response $response, array $args): Response
    {
        $patientId = $args['id'];
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('userId');

        // Verify patient exists
        $patient = Patient::find($patientId);
        if (!$patient) {
            return ResponseUtils::errorResponse($response, 'Patient not found', 404);
        }

        // Determine entry type and validate
        $entryType = $data['entryType'] ?? 'note';
        $validationErrors = $this->validateJournalData($data, $entryType);

        if (!empty($validationErrors)) {
            return ResponseUtils::validationErrorResponse($response, $validationErrors);
        }

        // Create journal
        $journal = new Journal();
        $journal->patient_id = $patientId;
        $journal->created_by = $userId;
        $journal->status = $data['status'] ?? 'draft';
        $journal->entry_type = $entryType;

        // Set common fields
        $this->mapJournalData($journal, $data);

        // Set type-specific fields
        if ($entryType === 'medication') {
            $this->mapMedicationData($journal, $data);
        } elseif ($entryType === 'drug_test') {
            $this->mapDrugTestData($journal, $data);
        } elseif ($entryType === 'incident') {
            $this->mapIncidentData($journal, $data);
        }

        $journal->created_at = Helpers::now();
        $journal->save();

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'Journal created successfully',
            'journal' => $this->transformJournal($journal)
        ], 201);
    }

    /**
     * Get journal by ID
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function getJournalById(Request $request, Response $response, array $args): Response
    {
        $journalId = $args['id'];

        $journal = Journal::find($journalId);

        if (!$journal) {
            return ResponseUtils::errorResponse($response, 'Journal not found', 404);
        }

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'journal' => $this->transformJournal($journal)
        ]);
    }

    /**
     * Update journal
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function updateJournal(Request $request, Response $response, array $args): Response
    {
        $journalId = $args['id'];
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('userId');

        $journal = Journal::find($journalId);

        if (!$journal) {
            return ResponseUtils::errorResponse($response, 'Journal not found', 404);
        }

        // Prevent updating completed journals (signed entries)
        if ($journal->status === 'completed') {
            return ResponseUtils::errorResponse($response, 'Signed journal entries cannot be modified', 403);
        }

        // Validate input based on entry type
        $entryType = $journal->entry_type;
        $validationErrors = $this->validateJournalData($data, $entryType, false, $journal->status);

        if (!empty($validationErrors)) {
            return ResponseUtils::validationErrorResponse($response, $validationErrors);
        }

        // Update common fields
        $this->mapJournalData($journal, $data);

        // Update type-specific fields
        if ($entryType === 'medication') {
            $this->mapMedicationData($journal, $data);
        } elseif ($entryType === 'drug_test') {
            $this->mapDrugTestData($journal, $data);
        } elseif ($entryType === 'incident') {
            $this->mapIncidentData($journal, $data);
        }

        $journal->updated_by = $userId;
        $journal->updated_at = Helpers::now();
        $journal->save();

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'Journal updated successfully',
            'journal' => $this->transformJournal($journal)
        ]);
    }

    /**
     * Sign journal (transition from draft to completed)
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function signJournal(Request $request, Response $response, array $args): Response
    {
        $journalId = $args['id'];
        $userId = $request->getAttribute('userId');

        $journal = Journal::find($journalId);

        if (!$journal) {
            return ResponseUtils::errorResponse($response, 'Journal not found', 404);
        }

        // Check if journal is already signed
        if ($journal->status === 'completed') {
            return ResponseUtils::errorResponse($response, 'Journal is already signed', 400);
        }

        // Check if journal is archived
        if ($journal->status === 'archived') {
            return ResponseUtils::errorResponse($response, 'Archived journals cannot be signed', 400);
        }

        // Update status to completed (signed)
        $journal->status = 'completed';
        $journal->updated_by = $userId;
        $journal->updated_at = Helpers::now();
        $journal->save();

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'Journal signed successfully',
            'journal' => $this->transformJournal($journal)
        ]);
    }

    /**
     * Archive journal (transition from completed to archived)
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function archiveJournal(Request $request, Response $response, array $args): Response
    {
        $journalId = $args['id'];
        $userId = $request->getAttribute('userId');

        $journal = Journal::find($journalId);

        if (!$journal) {
            return ResponseUtils::errorResponse($response, 'Journal not found', 404);
        }

        // Check if journal is already archived
        if ($journal->status === 'archived') {
            return ResponseUtils::errorResponse($response, 'Journal is already archived', 400);
        }

        // Update status to archived
        $journal->status = 'archived';
        $journal->updated_by = $userId;
        $journal->updated_at = Helpers::now();
        $journal->save();

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'Journal archived successfully',
            'journal' => $this->transformJournal($journal)
        ]);
    }

    /**
     * Delete journal
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function deleteJournal(Request $request, Response $response, array $args): Response
    {
        $journalId = $args['id'];

        $journal = Journal::find($journalId);

        if (!$journal) {
            return ResponseUtils::errorResponse($response, 'Journal not found', 404);
        }

        // Prevent deletion of any journal entries according to Swedish law
        return ResponseUtils::errorResponse(
            $response,
            'Journal entries cannot be deleted according to Swedish law',
            403
        );

        // The code below will never be executed, but kept as reference

        // Check permissions based on user role
        // $userRole = $request->getAttribute('role');
        // $userId = $request->getAttribute('userId');

        // Only admin or author can delete
        // if ($userRole !== 'admin' && $journal->created_by !== $userId) {
        //     return ResponseUtils::errorResponse($response, 'You do not have permission to delete this journal', 403);
        // }

        // Delete journal
        // $journal->delete();

        // return ResponseUtils::successResponse($response, [
        //     'success' => true,
        //     'message' => 'Journal deleted successfully'
        // ]);
    }

    /**
     * Validate journal data based on entry type
     *
     * @param array $data Journal data
     * @param string $entryType Entry type
     * @param bool $isCreating Whether this is a create operation
     * @param string $currentStatus Current journal status (if updating)
     * @return array Validation errors
     */
    private function validateJournalData(array $data, string $entryType, bool $isCreating = true, string $currentStatus = 'draft'): array
    {
        $errors = [];

        // Skip detailed validation for completed journals - only allow content and status changes
        if (!$isCreating && $currentStatus === 'completed') {
            // Only validate status if provided
            if (isset($data['status'])) {
                $validStatuses = ['completed', 'archived'];
                if (!in_array($data['status'], $validStatuses)) {
                    $errors['status'] = 'Signed journals can only be archived, not returned to draft status';
                }
            }
            return $errors;
        }

        // Common validation for all entry types
        if (!isset($data['title']) || empty($data['title'])) {
            $errors['title'] = 'Title is required';
        }

        // Entry type specific validation
        switch ($entryType) {
            case 'note':
                if (!isset($data['content']) || empty($data['content'])) {
                    $errors['content'] = 'Content is required';
                }
                break;

            case 'medication':
                if (!isset($data['medicationName']) || empty($data['medicationName'])) {
                    $errors['medicationName'] = 'Medication name is required';
                }

                if (!isset($data['medicationDose']) || empty($data['medicationDose'])) {
                    $errors['medicationDose'] = 'Medication dose is required';
                }

                if (!isset($data['medicationTime']) || empty($data['medicationTime'])) {
                    $errors['medicationTime'] = 'Medication time is required';
                }
                break;

            case 'drug_test':
                if (!isset($data['testType']) || empty($data['testType'])) {
                    $errors['testType'] = 'Test type is required';
                }

                if (!isset($data['testMethod']) || empty($data['testMethod'])) {
                    $errors['testMethod'] = 'Test method is required';
                }

                if (!isset($data['testResult']) || empty($data['testResult'])) {
                    $errors['testResult'] = 'Test result is required';
                }

                // If positive result and not breath test, require substances
                if (
                    isset($data['testResult']) &&
                    $data['testResult'] === 'positive' &&
                    isset($data['testMethod']) &&
                    $data['testMethod'] !== 'utandning' &&
                    empty($data['positiveSubstances'])
                ) {
                    $errors['positiveSubstances'] = 'Positive substances are required for positive non-breath test';
                }
                break;

            case 'incident':
                if (!isset($data['incidentSeverity']) || empty($data['incidentSeverity'])) {
                    $errors['incidentSeverity'] = 'Incident severity is required';
                }

                if (!isset($data['incidentDetails']) || empty($data['incidentDetails'])) {
                    $errors['incidentDetails'] = 'Incident details are required';
                }
                break;
        }

        // Validate status if provided
        if (isset($data['status'])) {
            $validStatuses = ['draft', 'completed', 'archived'];
            if (!in_array($data['status'], $validStatuses)) {
                $errors['status'] = 'Invalid status. Must be one of: ' . implode(', ', $validStatuses);
            }
        }

        return $errors;
    }

    /**
     * Map common journal data to model
     *
     * @param Journal $journal Journal model
     * @param array $data Request data
     */
    private function mapJournalData(Journal $journal, array $data): void
    {
        // Map basic fields
        if (isset($data['title'])) {
            // Only update title if journal is in draft status
            if ($journal->status === 'draft' || !$journal->exists) {
                $journal->title = SecurityUtils::sanitizeInput($data['title']);
            }
        }

        if (isset($data['content'])) {
            // For completed or archived journals, don't allow any content changes
            if ($journal->exists && ($journal->status === 'completed' || $journal->status === 'archived')) {
                // Do not modify content for completed/archived journals
                return;
            }

            // For draft journals, append new content rather than replacing
            if ($journal->exists && !empty($journal->content)) {
                $timestamp = date('Y-m-d H:i:s');
                $appendText = "\n\n--- {$timestamp} ---\n" . SecurityUtils::sanitizeInput($data['content']);
                $journal->content .= $appendText;
            } else {
                // For new entries, set content directly
                $journal->content = SecurityUtils::sanitizeInput($data['content']);
            }
        }

        if (isset($data['category'])) {
            // Only update category if journal is in draft status
            if ($journal->status === 'draft' || !$journal->exists) {
                $journal->category = empty($data['category']) ?
                    null : SecurityUtils::sanitizeInput($data['category']);
            }
        }

        if (isset($data['status'])) {
            // Prevent changing from 'completed' status back to 'draft'
            if ($journal->status === 'completed' && $data['status'] === 'draft') {
                // Do not allow changing from completed to draft
                return;
            }

            $journal->status = $data['status'];
        }
    }

    /**
     * Map medication data to journal model
     *
     * @param Journal $journal Journal model
     * @param array $data Request data
     */
    private function mapMedicationData(Journal $journal, array $data): void
    {
        // Only update specific fields if journal is in draft status or is new
        if ($journal->status !== 'completed') {
            if (isset($data['medicationName'])) {
                $journal->medication_name = SecurityUtils::sanitizeInput($data['medicationName']);
            }

            if (isset($data['medicationDose'])) {
                $journal->medication_dose = SecurityUtils::sanitizeInput($data['medicationDose']);
            }

            if (isset($data['medicationTime'])) {
                $journal->medication_time = $data['medicationTime'];
            }
        }
    }

    /**
     * Map drug test data to journal model
     *
     * @param Journal $journal Journal model
     * @param array $data Request data
     */
    private function mapDrugTestData(Journal $journal, array $data): void
    {
        // Only update specific fields if journal is in draft status or is new
        if ($journal->status !== 'completed') {
            if (isset($data['testType'])) {
                $journal->test_type = SecurityUtils::sanitizeInput($data['testType']);
            }

            if (isset($data['testMethod'])) {
                $journal->test_method = SecurityUtils::sanitizeInput($data['testMethod']);
            }

            if (isset($data['testResult'])) {
                $journal->test_result = $data['testResult'];
            }

            if (isset($data['positiveSubstances'])) {
                // Store as JSON string
                $journal->positive_substances = is_array($data['positiveSubstances'])
                    ? json_encode($data['positiveSubstances'])
                    : $data['positiveSubstances'];
            }
        }
    }

    /**
     * Map incident data to journal model
     *
     * @param Journal $journal Journal model
     * @param array $data Request data
     */
    private function mapIncidentData(Journal $journal, array $data): void
    {
        // Only update specific fields if journal is in draft status or is new
        if ($journal->status !== 'completed') {
            if (isset($data['incidentSeverity'])) {
                $journal->incident_severity = SecurityUtils::sanitizeInput($data['incidentSeverity']);
            }

            if (isset($data['incidentDetails'])) {
                $journal->incident_details = SecurityUtils::sanitizeInput($data['incidentDetails']);
            }
        }
    }

    /**
     * Transform journal model to API response format
     *
     * @param Journal $journal Journal model
     * @return array Transformed journal data
     */
    private function transformJournal(Journal $journal): array
    {
        // Get creator info
        $createdBy = User::find($journal->created_by);
        $createdByName = $createdBy ? $createdBy->full_name : 'Unknown';

        // Get updater info if available
        $updatedByName = null;
        if ($journal->updated_by) {
            $updatedBy = User::find($journal->updated_by);
            $updatedByName = $updatedBy ? $updatedBy->full_name : 'Unknown';
        }

        // Base journal data
        $journalData = [
            'id' => $journal->id,
            'patientId' => $journal->patient_id,
            'title' => $journal->title,
            'content' => $journal->content,
            'category' => $journal->category,
            'status' => $journal->status,
            'entryType' => $journal->entry_type,
            'createdBy' => $journal->created_by,
            'createdByName' => $createdByName,
            'updatedBy' => $journal->updated_by,
            'updatedByName' => $updatedByName,
            'createdAt' => $journal->created_at,
            'updatedAt' => $journal->updated_at
        ];

        // Add type-specific data
        switch ($journal->entry_type) {
            case 'medication':
                $journalData['medicationName'] = $journal->medication_name;
                $journalData['medicationDose'] = $journal->medication_dose;
                $journalData['medicationTime'] = $journal->medication_time;
                break;

            case 'drug_test':
                $journalData['testType'] = $journal->test_type;
                $journalData['testMethod'] = $journal->test_method;
                $journalData['testResult'] = $journal->test_result;
                $journalData['positiveSubstances'] = $journal->getPositiveSubstancesArray();
                break;

            case 'incident':
                $journalData['incidentSeverity'] = $journal->incident_severity;
                $journalData['incidentDetails'] = $journal->incident_details;
                break;
        }

        return $journalData;
    }
}