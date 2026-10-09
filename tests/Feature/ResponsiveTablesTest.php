<?php

it('provides mobile labels for every table cell without removing desktop headers', function () {
    $paths = [...glob(resource_path('views/livewire/*.blade.php')), resource_path('views/dashboard.blade.php'), resource_path('views/receipts/show.blade.php')];
    $tables = 0;
    foreach ($paths as $path) {
        $source = file_get_contents($path);
        preg_match_all('/<table\b[^>]*>.*?<\/table>/s', $source, $matches);
        foreach ($matches[0] as $table) {
            $tables++;
            expect($table)->toContain('workshop-responsive-table')->toContain('<thead');
            preg_match_all('/<td\b[^>]*>/', $table, $cells);
            foreach ($cells[0] as $cell) {
                if (! str_contains($cell, 'colspan=')) {
                    expect($cell)->toContain('data-label=');
                }
            }
        }
    }
    expect($tables)->toBeGreaterThanOrEqual(16);
});
