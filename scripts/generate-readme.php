<?php

chdir(__DIR__.'/..');

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class);

// Try to get list via Symfony console JSON if available
$commands = [];

$artisan = $app->make(Illuminate\Contracts\Console\Kernel::class);
$output = new Symfony\Component\Console\Output\BufferedOutput;
$input = new Symfony\Component\Console\Input\ArrayInput(['command' => 'list', '--format' => 'json']);
$artisan->handle($input, $output);
$data = json_decode($output->fetch(), true);
if (is_array($data) && isset($data['commands'])) {
    foreach ($data['commands'] as $cmd) {
        $commands[] = [
            'name' => $cmd['name'] ?? '',
            'description' => $cmd['description'] ?? '',
        ];
    }
}

// Keep only custom commands starting with `app:` then filter unique and sort
$seen = [];
$commands = array_values(array_filter($commands, function ($c) use (&$seen) {
    $key = $c['name'] ?? '';
    if (! $key) {
        return false;
    }
    if (! str_starts_with($key, 'app:')) {
        return false;
    } // only user-defined commands
    if (isset($seen[$key])) {
        return false;
    }
    $seen[$key] = true;

    return true;
}));
usort($commands, function ($a, $b) {
    return strcmp($a['name'], $b['name']);
});

$readmePath = __DIR__.'/../README.md';
$existing = file_exists($readmePath) ? file_get_contents($readmePath) : '';

$start = '<!-- COMMANDS:START -->';
$end = '<!-- COMMANDS:END -->';

$table = "\n";
$table .= "| Command | Description |\n";
$table .= "|---|---|\n";
if ($commands) {
    foreach ($commands as $c) {
        $name = $c['name'];
        $desc = str_replace(["\n", "\r", '|'], [' ', ' ', '\\|'], $c['description']);
        $table .= "| `{$name}` | {$desc} |\n";
    }
} else {
    $table .= "| _(no commands found)_ |  |\n";
}
$table .= "\n";

$section = "\n## Console Commands\n\n".$start."\n".$table.$end."\n";

if (str_contains($existing, $start) && str_contains($existing, $end)) {
    $new = preg_replace('/'.preg_quote($start, '/').'.*?'.preg_quote($end, '/').'/s', $start."\n".$table.$end, $existing);
} else {
    $new = rtrim($existing)."\n\n".$section;
}

if ($new !== $existing) {
    file_put_contents($readmePath, $new);
}

echo "README.md updated.\n";
