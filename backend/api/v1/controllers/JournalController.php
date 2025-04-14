<?php
// backend/api/v1/controllers/JournalController.php
namespace Vyper\Api\V1\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Vyper\Api\V1\Models\Journal;
use Vyper\Api\V1\Models\Patient;
use Vyper\Api\V1\Models\User;
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

        // Apply patient filter
        if ($patientId !== 'all') {
            $query->where('patient_id', $patientId);
        }

        // Apply search filter if provided
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%$search%")
                    ->orWhere('content', 'LIKE', "%$search%");
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
        $sortBy = $params['sortBy'] ?? 'newest';
        $search = $params['search'] ?? '';

        // Get current user ID for permissions
        $userId = $request->getAttribute('userId');

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

        // Apply search filter if provided
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%$search%")
                    ->orWhere('content', 'LIKE', "%$search%");
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

        // Transform data
        $journalData = [];
        foreach ($journals as $journal) {
            $journalData[] = $this->transformJournal($journal);
        }

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'journals' => $journalData,
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

        // Validate input
        $errors = $this->validateJournalData($data);

        if (!empty($errors)) {
            return ResponseUtils::validationErrorResponse($response, $errors);
        }

        // Verify patient exists
        if (!isset($data['patientId']) || empty($data['patientId'])) {
            return ResponseUtils::errorResponse($response, 'Patient ID is required', 400);
        }

        $patient = Patient::find($data['patientId']);
        if (!$patient) {
            return ResponseUtils::errorResponse($response, 'Patient not found', 404);
        }

        // Create journal
        $journal = new Journal();
        $journal->patient_id = $data['patientId'];
        $journal->created_by = $userId;
        $journal->status = $data['status'] ?? 'draft';

        $this->mapJournalData($journal, $data);
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

        // Validate input
        $errors = $this->validateJournalData($data);

        if (!empty($errors)) {
            return ResponseUtils::validationErrorResponse($response, $errors);
        }

        // Create journal
        $journal = new Journal();
        $journal->patient_id = $patientId;
        $journal->created_by = $userId;
        $journal->status = $data['status'] ?? 'draft';

        $this->mapJournalData($journal, $data);
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

        // Validate input
        $errors = $this->validateJournalData($data, false);

        if (!empty($errors)) {
            return ResponseUtils::validationErrorResponse($response, $errors);
        }

        // Update journal
        $this->mapJournalData($journal, $data);
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

        // Check permissions based on user role
        $userRole = $request->getAttribute('role');
        $userId = $request->getAttribute('userId');

        // Only admin or author can delete
        if ($userRole !== 'admin' && $journal->created_by !== $userId) {
            return ResponseUtils::errorResponse($response, 'You do not have permission to delete this journal', 403);
        }

        // Delete journal
        $journal->delete();

        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'Journal deleted successfully'
        ]);
    }

    /**
     * Validate journal data
     *
     * @param array $data Journal data
     * @param bool $isCreating Whether this is a create operation
     * @return array Validation errors
     */
    private function validateJournalData(array $data, bool $isCreating = true): array
    {
        $errors = [];

        // Required fields
        if (!isset($data['title']) || empty($data['title'])) {
            $errors['title'] = 'Title is required';
        }

        if (!isset($data['content']) || empty($data['content'])) {
            $errors['content'] = 'Content is required';
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
     * Map request data to journal model
     *
     * @param Journal $journal Journal model
     * @param array $data Request data
     */
    private function mapJournalData(Journal $journal, array $data): void
    {
        // Map basic fields
        if (isset($data['title'])) {
            $journal->title = SecurityUtils::sanitizeInput($data['title']);
        }

        if (isset($data['content'])) {
            $journal->content = SecurityUtils::sanitizeInput($data['content']);
        }

        if (isset($data['category'])) {
            $journal->category = empty($data['category']) ?
                null : SecurityUtils::sanitizeInput($data['category']);
        }

        if (isset($data['status'])) {
            $journal->status = $data['status'];
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

        return [
            'id' => $journal->id,
            'patientId' => $journal->patient_id,
            'title' => $journal->title,
            'content' => $journal->content,
            'category' => $journal->category,
            'status' => $journal->status,
            'createdBy' => $journal->created_by,
            'createdByName' => $createdByName,
            'updatedBy' => $journal->updated_by,
            'updatedByName' => $updatedByName,
            'createdAt' => $journal->created_at,
            'updatedAt' => $journal->updated_at
        ];
    }
}