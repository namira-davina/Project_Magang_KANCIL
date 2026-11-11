<?php

namespace Tests\Unit;

use App\Support\Keywords;
use PHPUnit\Framework\TestCase;

class KeywordsTest extends TestCase
{
    public function test_splits_trims_and_dedupes(): void
    {
        $this->assertSame(['a', 'b', 'c'], Keywords::parse(' a , b,a,, c '));
    }

    public function test_empty_and_null_give_empty_list(): void
    {
        $this->assertSame([], Keywords::parse(''));
        $this->assertSame([], Keywords::parse(null));
        $this->assertSame([], Keywords::parse(' , ,, '));
    }

    public function test_keeps_multiword_keywords_and_is_case_sensitive(): void
    {
        $this->assertSame(['buat paspor', 'Paspor', 'paspor'], Keywords::parse('buat paspor, Paspor, paspor'));
    }

    public function test_result_is_reindexed(): void
    {
        $this->assertSame([0, 1], array_keys(Keywords::parse(',x,,y')));
    }
}
