<?php

namespace App\Services;

use RuntimeException;

class ConversationTrainingDataset
{
    /** @return list<string> */
    public function paths(): array
    {
        return [
            resource_path('chatbot/conversation_training_radimax.json'),
            resource_path('chatbot/conversation_training_radimax_extended.json'),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function documents(): array
    {
        return array_map(fn (string $path): array => $this->read($path), $this->paths());
    }

    /** @return list<array<string, mixed>> */
    public function scenarios(): array
    {
        $scenarios = [];
        $ids = [];

        foreach ($this->documents() as $document) {
            foreach ($document['scenarios'] as $scenario) {
                $id = (string) $scenario['id'];
                if (isset($ids[$id])) {
                    throw new RuntimeException("Duplicate training scenario id: {$id}");
                }

                $ids[$id] = true;
                $scenario['_dataset_name'] = $document['dataset_name'];
                $scenarios[] = $scenario;
            }
        }

        return $scenarios;
    }

    /** @return array<string, mixed> */
    private function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Training dataset is not readable: {$path}");
        }

        $document = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($document)
            || ! is_string($document['dataset_name'] ?? null)
            || ! is_array($document['scenarios'] ?? null)) {
            throw new RuntimeException("Invalid training dataset contract: {$path}");
        }

        foreach ($document['scenarios'] as $index => $scenario) {
            if (! is_array($scenario) || ! is_string($scenario['id'] ?? null)) {
                throw new RuntimeException("Invalid scenario at {$path} index {$index}");
            }
        }

        return $document;
    }
}
