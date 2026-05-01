<?php

namespace Tests\Unit\WalletLedger;

use App\Support\WalletLedger\MerkleTree;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MerkleTree::class)]
final class MerkleTreeTest extends TestCase
{
    public function test_empty_tree_root_is_stable_digest(): void
    {
        $root = MerkleTree::rootHex([]);
        $this->assertSame(64, strlen($root));
        $this->assertSame(
            hash('sha256', 'wallet_ledger_merkle_empty_v1', false),
            $root,
        );
    }

    public function test_two_leaves_matches_manual_parent_hash(): void
    {
        $a = hash('sha256', 'leaf-a', false);
        $b = hash('sha256', 'leaf-b', false);
        $expected = hash('sha256', hex2bin($a).hex2bin($b), false);

        $this->assertSame($expected, MerkleTree::rootHex([$a, $b]));
    }

    public function test_odd_count_duplicates_last_leaf(): void
    {
        $a = hash('sha256', 'x', false);
        $b = hash('sha256', 'y', false);
        $c = hash('sha256', 'z', false);
        $ab = hash('sha256', hex2bin($a).hex2bin($b), false);
        $cc = hash('sha256', hex2bin($c).hex2bin($c), false);
        $expected = hash('sha256', hex2bin($ab).hex2bin($cc), false);

        $this->assertSame($expected, MerkleTree::rootHex([$a, $b, $c]));
    }
}
