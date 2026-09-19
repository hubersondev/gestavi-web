<?php

use App\Models\Leave;

test('leave fillable exposes attachment column', function () {
    expect((new Leave)->getFillable())->toContain('attachment');
});
