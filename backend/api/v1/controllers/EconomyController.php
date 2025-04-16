<?php
// backend/api/v1/controllers/EconomyController.php
namespace Vyper\Api\V1\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Vyper\Api\V1\Models\Economy;
use Vyper\Api\V1\Models\Patient;
use Vyper\Api\V1\Utils\ResponseUtils;
use Vyper\Helpers;

class EconomyController
{
    /**
     * Get economy data for a specific year
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function getYearlyEconomyData(Request $request, Response $response): Response
    {
        // Get query parameters
        $params = $request->getQueryParams();
        $year = isset($params['year']) ? (int)$params['year'] : (int)date('Y');
        
        // Get current user ID for permissions check
        $userId = $request->getAttribute('userId');
        $userRole = $request->getAttribute('role');
        
        // Check if user has permission to view economy data
        if (!in_array($userRole, ['admin', 'manager'])) {
            return ResponseUtils::errorResponse($response, 'Permission denied', 403);
        }
        
        // Get economy data for the entire year
        $monthlyData = [];
        
        for ($month = 1; $month <= 12; $month++) {
            // Get actual income and budget from database
            $economyRecord = Economy::where('year', $year)
                ->where('month', $month)
                ->first();
            
            // Calculate predicted income
            $predictedIncome = Economy::calculatePredictedIncome($year, $month);
            
            $monthlyData[] = [
                'year' => $year,
                'month' => $month,
                'monthName' => date('F', mktime(0, 0, 0, $month, 10)),
                'actualIncome' => $economyRecord ? $economyRecord->actual_income : 0,
                'budget' => $economyRecord ? $economyRecord->budget : 0,
                'predictedIncome' => $predictedIncome
            ];
        }
        
        // Calculate totals
        $actualIncomeTotal = array_sum(array_column($monthlyData, 'actualIncome'));
        $budgetTotal = array_sum(array_column($monthlyData, 'budget'));
        $predictedIncomeTotal = array_sum(array_column($monthlyData, 'predictedIncome'));
        
        return ResponseUtils::successResponse($response, [
            'success' => true,
            'monthlyData' => $monthlyData,
            'totals' => [
                'actualIncome' => $actualIncomeTotal,
                'budget' => $budgetTotal,
                'predictedIncome' => $predictedIncomeTotal
            ]
        ]);
    }
    
    /**
     * Update economy data for a specific year and month
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function updateEconomyData(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        // Get current user ID for permissions check
        $userId = $request->getAttribute('userId');
        $userRole = $request->getAttribute('role');
        
        // Check if user has permission to update economy data
        if (!in_array($userRole, ['admin', 'manager'])) {
            return ResponseUtils::errorResponse($response, 'Permission denied', 403);
        }
        
        // Validate input
        $errors = [];
        
        if (!isset($data['year']) || !is_numeric($data['year'])) {
            $errors['year'] = 'Valid year is required';
        }
        
        if (!isset($data['month']) || !is_numeric($data['month']) || $data['month'] < 1 || $data['month'] > 12) {
            $errors['month'] = 'Valid month (1-12) is required';
        }
        
        if (!isset($data['actualIncome']) || !is_numeric($data['actualIncome']) || $data['actualIncome'] < 0) {
            $errors['actualIncome'] = 'Valid actual income is required';
        }
        
        if (!isset($data['budget']) || !is_numeric($data['budget']) || $data['budget'] < 0) {
            $errors['budget'] = 'Valid budget is required';
        }
        
        if (!empty($errors)) {
            return ResponseUtils::validationErrorResponse($response, $errors);
        }
        
        // Find or create economy record
        $economy = Economy::firstOrNew([
            'year' => (int)$data['year'],
            'month' => (int)$data['month']
        ]);
        
        // Update fields
        $economy->actual_income = (float)$data['actualIncome'];
        $economy->budget = (float)$data['budget'];
        
        // Set user who created/updated
        if (!$economy->exists) {
            $economy->created_by = $userId;
        }
        $economy->updated_by = $userId;
        
        // Save changes
        $economy->save();
        
        // Calculate predicted income
        $predictedIncome = Economy::calculatePredictedIncome($data['year'], $data['month']);
        
        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'Economy data updated successfully',
            'data' => [
                'year' => (int)$data['year'],
                'month' => (int)$data['month'],
                'monthName' => date('F', mktime(0, 0, 0, $data['month'], 10)),
                'actualIncome' => (float)$economy->actual_income,
                'budget' => (float)$economy->budget,
                'predictedIncome' => $predictedIncome
            ]
        ]);
    }
}