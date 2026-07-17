<?php
require __DIR__ . "/vendor/autoload.php";
$app = require __DIR__ . "/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$rows = App\Models\Institution::where("type", "university")->orderBy("id")->get(["id","name","address","location","latitude","longitude"]);
$path = dirname(__DIR__) . "/data-scrapers/output/institutions_for_geocode.json";
file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
$missing = $rows->filter(fn($r) => !$r->latitude || !$r->longitude)->count();
echo "exported={$rows->count()} missing={$missing} path={$path}\n";
