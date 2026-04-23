<?php

namespace Tests\Unit;

use Tests\TestCase;

class CapabilityDefinitionsTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function loadJson(string $relativeToDatabase): array
    {
        $path = database_path($relativeToDatabase);
        $this->assertFileExists($path);

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, true>
     */
    private function catalogIds(array $catalog): array
    {
        $ids = [];
        foreach ($catalog as $entry) {
            $this->assertIsArray($entry);
            $this->assertArrayHasKey('module', $entry);
            $this->assertArrayHasKey('capabilities', $entry);
            foreach ($entry['capabilities'] as $cap) {
                $this->assertIsArray($cap);
                $this->assertArrayHasKey('id', $cap);
                $this->assertArrayHasKey('name', $cap);
                $this->assertArrayHasKey('category', $cap);
                $this->assertArrayHasKey('isDangerous', $cap);
                $this->assertArrayHasKey('description', $cap);
                $ids[(string) $cap['id']] = true;
            }
        }

        return $ids;
    }

    public function test_user_type_capability_ids_exist_in_catalog(): void
    {
        $catalog = $this->loadJson('data/capabilities.json');
        $byType = $this->loadJson('data/user_type_capabilities.json');

        $valid = $this->catalogIds($catalog);

        foreach ($byType as $typeKey => $typeDef) {
            $this->assertIsArray($typeDef, $typeKey);
            $this->assertArrayHasKey('entity_type', $typeDef, $typeKey);
            $this->assertArrayHasKey('role', $typeDef, $typeKey);
            $this->assertArrayHasKey('description', $typeDef, $typeKey);
            $this->assertArrayHasKey('capabilities', $typeDef, $typeKey);
            $this->assertSame($typeKey, $typeDef['role'], $typeKey);
            foreach ($typeDef['capabilities'] as $capId) {
                $this->assertArrayHasKey(
                    $capId,
                    $valid,
                    "Missing catalog definition for capability: {$capId} (type {$typeKey})",
                );
            }
        }
    }

    public function test_catalog_capability_ids_are_assigned_to_at_least_one_user_type(): void
    {
        $catalog = $this->loadJson('data/capabilities.json');
        $byType = $this->loadJson('data/user_type_capabilities.json');

        $assigned = [];
        foreach ($byType as $typeDef) {
            foreach ($typeDef['capabilities'] as $capId) {
                $assigned[$capId] = true;
            }
        }

        foreach ($this->catalogIds($catalog) as $id => $_) {
            $this->assertArrayHasKey(
                $id,
                $assigned,
                "Capability never granted to any user type: {$id}",
            );
        }
    }
}
