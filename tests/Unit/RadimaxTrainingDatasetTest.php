<?php

namespace Tests\Unit;

use App\Services\ConversationTrainingDataset;
use App\Services\RadimaxTrainingEvaluator;
use Tests\TestCase;

class RadimaxTrainingDatasetTest extends TestCase
{
    public function test_training_documents_are_valid_and_scenario_ids_are_unique(): void
    {
        $dataset = app(ConversationTrainingDataset::class);

        $this->assertCount(2, $dataset->documents());
        $this->assertCount(26, $dataset->scenarios());
        $this->assertCount(26, array_unique(array_column($dataset->scenarios(), 'id')));
    }

    public function test_every_executable_radimax_scenario_matches_its_expected_decision(): void
    {
        $dataset = app(ConversationTrainingDataset::class);
        $evaluator = app(RadimaxTrainingEvaluator::class);
        $scenarios = collect($dataset->scenarios())->whereNotNull('expected_decision');

        $this->assertCount(24, $scenarios);
        foreach ($scenarios as $scenario) {
            $this->assertSame(
                $scenario['expected_decision'],
                $evaluator->evaluate($scenario),
                $scenario['id'],
            );
        }
    }
}
