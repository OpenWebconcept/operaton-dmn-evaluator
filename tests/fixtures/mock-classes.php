<?php

/**
 * Mock classes for testing
 */

namespace Operaton\DMN\Tests\Fixtures;

/**
 * Mock DMN API Manager
 */
class MockDmnApi
{
    private array $responses = [];
    private array $errors = [];

    public function setMockResponse(array $response): void
    {
        $this->responses[] = $response;
    }

    public function setMockError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function evaluateDmn(array $data): array
    {
        if (!empty($this->errors))
        {
            throw new \Exception(array_shift($this->errors));
        }

        if (empty($data))
        {
            throw new \InvalidArgumentException('Data cannot be empty');
        }

        if (!empty($this->responses))
        {
            return array_shift($this->responses);
        }

        // Default response based on input
        return $this->generateResponseFromInput($data);
    }

    public function validateApiKey(string $key): bool
    {
        return $key === 'test-valid-key';
    }

    public function testConnection(): bool
    {
        return !empty($this->responses) || empty($this->errors);
    }

    private function generateResponseFromInput(array $data): array
    {
        $age = (int)($data['age'] ?? 25);
        $income = (int)($data['income'] ?? 50000);
        $creditScore = $data['credit_score'] ?? 'good';

        // Simple decision logic for testing
        if ($age >= 18 && $income >= 30000 && in_array($creditScore, ['excellent', 'good']))
        {
            return [
                'decision' => 'approved',
                'confidence' => 0.9,
                'reasoning' => 'Meets all criteria'
            ];
        }
        elseif ($age >= 18 && $income >= 20000)
        {
            return [
                'decision' => 'conditional',
                'confidence' => 0.7,
                'reasoning' => 'Partial criteria met'
            ];
        }
        else
        {
            return [
                'decision' => 'rejected',
                'confidence' => 0.8,
                'reasoning' => 'Does not meet minimum criteria'
            ];
        }
    }
}

/**
 * Mock Database Manager
 */
class MockDmnDatabase
{
    private array $evaluations = [];
    private array $performance = [];
    private int $nextId = 1;

    public function logEvaluation(array $data): int
    {
        $id = $this->nextId++;
        $this->evaluations[$id] = array_merge($data, [
            'id' => $id,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $id;
    }

    public function logPerformance(array $data): int
    {
        $id = $this->nextId++;
        $this->performance[$id] = array_merge($data, [
            'id' => $id,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $id;
    }

    public function getEvaluations(array $criteria = []): array
    {
        $results = $this->evaluations;

        if (!empty($criteria['form_id']))
        {
            $results = array_filter($results, function ($eval) use ($criteria)
            {
                return $eval['form_id'] == $criteria['form_id'];
            });
        }

        return array_values($results);
    }

    public function getPerformanceMetrics(string $startDate, string $endDate): array
    {
        return array_filter($this->performance, function ($perf) use ($startDate, $endDate)
        {
            return $perf['created_at'] >= $startDate && $perf['created_at'] <= $endDate;
        });
    }

    public function cleanupOldData(int $daysOld): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
        $deletedCount = 0;

        foreach ($this->evaluations as $id => $eval)
        {
            if ($eval['created_at'] < $cutoffDate)
            {
                unset($this->evaluations[$id]);
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    public function reset(): void
    {
        $this->evaluations = [];
        $this->performance = [];
        $this->nextId = 1;
    }
}
