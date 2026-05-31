<?php

namespace App\Support;

use App\Models\ProjectArgument;

final class ProjectArgumentDepth
{
    public const MAX_DEPTH = 12;

    /**
     * Depth of an existing node: 1 = direct child of thesis (parent_id null).
     */
    public static function depthOfNode(ProjectArgument $node): int
    {
        $d = 1;
        $parentId = $node->parent_id;
        while ($parentId !== null) {
            $parent = ProjectArgument::query()
                ->whereKey($parentId)
                ->where('project_id', $node->project_id)
                ->firstOrFail();
            $d++;
            $parentId = $parent->parent_id;
        }

        return $d;
    }

    /**
     * Depth a new child would have under the given parent (null = top-level vs thesis).
     */
    public static function depthForNewChild(?int $parentId, int $projectId): int
    {
        if ($parentId === null) {
            return 1;
        }

        $parent = ProjectArgument::query()
            ->whereKey($parentId)
            ->where('project_id', $projectId)
            ->firstOrFail();

        return self::depthOfNode($parent) + 1;
    }
}
