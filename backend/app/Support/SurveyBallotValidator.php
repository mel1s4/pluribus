<?php

namespace App\Support;

use App\Models\Survey;
use App\Models\SurveyOption;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class SurveyBallotValidator
{
    /**
     * @param  list<array<string, mixed>>  $rawSelections
     * @return list<array{survey_option_id: int, rank: int|null}>
     */
    public function resolve(Survey $survey, User $user, array $rawSelections): array
    {
        $survey->loadMissing('options');
        $survey->normalizeModalityFlags();

        if ($rawSelections === []) {
            throw ValidationException::withMessages([
                'selections' => ['At least one selection is required.'],
            ]);
        }

        if (! $survey->allow_multiple && count($rawSelections) !== 1) {
            throw ValidationException::withMessages([
                'selections' => ['This survey allows only one selection.'],
            ]);
        }

        $customLabelCount = 0;
        $resolved = [];
        $usedOptionIds = [];
        $usedCustomKeys = [];

        foreach ($rawSelections as $index => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    "selections.{$index}" => ['Each selection must be an object.'],
                ]);
            }

            $optionId = isset($item['option_id']) ? (int) $item['option_id'] : null;
            $customLabel = isset($item['custom_label']) ? trim((string) $item['custom_label']) : '';
            $hasOptionId = $optionId !== null && $optionId > 0;
            $hasCustom = $customLabel !== '';

            if ($hasOptionId === $hasCustom) {
                throw ValidationException::withMessages([
                    "selections.{$index}" => ['Provide either option_id or custom_label, not both.'],
                ]);
            }

            if ($hasCustom) {
                if (! $survey->allow_add_options) {
                    throw ValidationException::withMessages([
                        "selections.{$index}.custom_label" => ['Custom options are not allowed for this survey.'],
                    ]);
                }
                if (mb_strlen($customLabel) > 255) {
                    throw ValidationException::withMessages([
                        "selections.{$index}.custom_label" => ['Custom label may not exceed 255 characters.'],
                    ]);
                }
                $customLabelCount++;
                $key = SurveyOption::normalizeLabel($customLabel);
                if (isset($usedCustomKeys[$key])) {
                    throw ValidationException::withMessages([
                        'selections' => ['Duplicate custom labels in the same ballot are not allowed.'],
                    ]);
                }
                $usedCustomKeys[$key] = true;
                $resolved[] = [
                    'custom_label' => $customLabel,
                    'rank' => $this->parseRank($item, $survey, $index),
                ];
            } else {
                if (isset($usedOptionIds[$optionId])) {
                    throw ValidationException::withMessages([
                        'selections' => ['The same option cannot be selected twice.'],
                    ]);
                }
                $option = $survey->options->firstWhere('id', $optionId);
                if ($option === null) {
                    throw ValidationException::withMessages([
                        "selections.{$index}.option_id" => ['The selected option is invalid for this survey.'],
                    ]);
                }
                $usedOptionIds[$optionId] = true;
                $resolved[] = [
                    'survey_option_id' => $optionId,
                    'rank' => $this->parseRank($item, $survey, $index),
                ];
            }
        }

        if ($customLabelCount > Survey::MAX_CUSTOM_LABELS_PER_BALLOT) {
            throw ValidationException::withMessages([
                'selections' => ['Too many custom options in this ballot.'],
            ]);
        }

        $existingCustomCount = $survey->options->where('is_custom', true)->count();
        $newCustomCount = count($usedCustomKeys);
        $reusedCustom = 0;
        foreach (array_keys($usedCustomKeys) as $key) {
            $exists = $survey->options->contains(
                fn (SurveyOption $o): bool => SurveyOption::normalizeLabel($o->label) === $key
            );
            if ($exists) {
                $reusedCustom++;
            }
        }
        $netNewCustom = $newCustomCount - $reusedCustom;
        if ($existingCustomCount + $netNewCustom > Survey::MAX_CUSTOM_OPTIONS_PER_SURVEY) {
            throw ValidationException::withMessages([
                'selections' => ['This survey has reached the maximum number of custom options.'],
            ]);
        }

        $this->validateRanks($survey, $resolved);

        return $this->materializeOptions($survey, $user, $resolved);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function parseRank(array $item, Survey $survey, int $index): ?int
    {
        if (! array_key_exists('rank', $item) || $item['rank'] === null || $item['rank'] === '') {
            if ($survey->require_ranked) {
                throw ValidationException::withMessages([
                    "selections.{$index}.rank" => ['Rank is required for this survey.'],
                ]);
            }

            return null;
        }

        if (! $survey->allow_multiple || ! $survey->require_ranked) {
            return null;
        }

        $rank = (int) $item['rank'];
        if ($rank < 1) {
            throw ValidationException::withMessages([
                "selections.{$index}.rank" => ['Rank must be at least 1.'],
            ]);
        }

        return $rank;
    }

    /**
     * @param  list<array<string, mixed>>  $resolved
     */
    private function validateRanks(Survey $survey, array $resolved): void
    {
        if (! $survey->allow_multiple || ! $survey->require_ranked) {
            return;
        }

        $ranks = array_map(static fn (array $row): int => (int) $row['rank'], $resolved);
        $expected = range(1, count($resolved));
        sort($ranks);
        if ($ranks !== $expected) {
            throw ValidationException::withMessages([
                'selections' => ['Ranks must be unique consecutive integers from 1 to the number of selections.'],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $resolved
     * @return list<array{survey_option_id: int, rank: int|null}>
     */
    private function materializeOptions(Survey $survey, User $user, array $resolved): array
    {
        $out = [];
        foreach ($resolved as $row) {
            if (isset($row['survey_option_id'])) {
                $out[] = [
                    'survey_option_id' => (int) $row['survey_option_id'],
                    'rank' => $row['rank'],
                ];

                continue;
            }

            $label = (string) $row['custom_label'];
            $key = SurveyOption::normalizeLabel($label);
            /** @var SurveyOption|null $existing */
            $existing = $survey->options->first(
                fn (SurveyOption $o): bool => SurveyOption::normalizeLabel($o->label) === $key
            );

            if ($existing === null) {
                $maxSort = (int) $survey->options->max('sort_order');
                $existing = SurveyOption::query()->create([
                    'survey_id' => $survey->id,
                    'label' => trim($label),
                    'sort_order' => $maxSort + 1,
                    'is_custom' => true,
                    'created_by_user_id' => $user->id,
                ]);
                $survey->options->push($existing);
            }

            $out[] = [
                'survey_option_id' => (int) $existing->id,
                'rank' => $row['rank'],
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function normalizeInputPayload(array $input): array
    {
        if (isset($input['selections']) && is_array($input['selections'])) {
            return array_values($input['selections']);
        }

        if (isset($input['option_id'])) {
            return [['option_id' => (int) $input['option_id']]];
        }

        return [];
    }
}
