<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$unis = App\Models\Institution::where('type', 'university')->orderBy('id')->get(['id', 'name', 'website', 'location']);
$out = [];
foreach ($unis as $u) {
    $specs = DB::table('institution_specialties as is')
        ->join('specializations as s', 's.id', '=', 'is.university_specialization_id')
        ->leftJoin('qualifications as q', 'q.id', '=', 's.qualification_id')
        ->where('is.institution_id', $u->id)
        ->get([
            'is.id as link_id',
            's.id as spec_id',
            's.name',
            'is.cost',
            'is.duration',
            'q.qualification_name',
            'q.id as qualification_id',
        ]);
    $list = [];
    foreach ($specs as $s) {
        $list[] = [
            'link_id' => $s->link_id,
            'spec_id' => $s->spec_id,
            'name' => $s->name,
            'cost' => $s->cost,
            'duration' => $s->duration,
            'qualification_name' => $s->qualification_name,
            'code' => null,
        ];
    }
    $out[] = [
        'id' => $u->id,
        'name' => $u->name,
        'website' => $u->website,
        'location' => $u->location,
        'specialties' => $list,
    ];
}

$path = dirname(__DIR__) . '/data-scrapers/output/institutions_specs_export.json';
file_put_contents($path, json_encode(['institutions' => $out, 'exported_at' => date('c')], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo 'exported=' . count($out) . " path=$path\n";
$total = array_sum(array_map(fn ($i) => count($i['specialties']), $out));
echo "total_specs=$total\n";
