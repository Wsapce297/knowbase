<?php

use Illuminate\Support\Facades\DB;

it('has the pgvector extension enabled', function () {
    $enabled = DB::table('pg_extension')->where('extname', 'vector')->exists();

    expect($enabled)->toBeTrue();
});

it('can calculate the distance between two vectors', function () {
    // <-> is pgvector's Euclidean (straight-line) distance operator.
    // [0,0] to [3,4] is a 3-4-5 triangle, so the distance is exactly 5.
    $row = DB::selectOne("select '[0,0]'::vector <-> '[3,4]'::vector as distance");

    expect((float) $row->distance)->toEqualWithDelta(5.0, 0.0001);
});
