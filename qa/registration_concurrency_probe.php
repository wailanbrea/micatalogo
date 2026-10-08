<?php

declare(strict_types=1);

use App\Models\Shop;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config()->set('services.turnstile.enabled', false);

$database = (string) config('database.connections.mysql.database');
if (! str_starts_with($database, 'micatalogo_qa_')) {
    throw new RuntimeException('Registration probe requires a database prefixed micatalogo_qa_.');
}

if (($argv[1] ?? null) === 'worker') {
    $barrier = (string) ($argv[2] ?? '');
    $email = (string) ($argv[3] ?? '');
    $slug = (string) ($argv[4] ?? '');
    $worker = (int) ($argv[5] ?? 1);

    while (! is_file($barrier)) {
        usleep(10_000);
    }

    try {
        $user = app(RegistrationService::class)->create([
            'name' => "QA Registro {$worker}",
            'email' => $email,
            'business_name' => "QA Registro {$worker}",
            'business_type' => 'general_retail',
            'slug' => $slug,
            'whatsapp_country_code' => '1809',
            'whatsapp_number' => '809'.str_pad((string) (1000000 + $worker), 7, '0', STR_PAD_LEFT),
            'password' => 'QaOnly-20261008!',
            'password_confirmation' => 'QaOnly-20261008!',
            'terms_accepted' => '1',
        ]);

        echo json_encode([
            'status' => 'success',
            'user_id' => $user->id,
            'shop_slug' => $user->shops()->value('slug'),
        ], JSON_THROW_ON_ERROR).PHP_EOL;
    } catch (Throwable $exception) {
        // Keep probe output useful without exposing SQL or environment data.
        echo json_encode([
            'status' => 'rejected',
            'class' => $exception::class,
            'code' => $exception->getCode(),
        ], JSON_THROW_ON_ERROR).PHP_EOL;
    }

    exit(0);
}

/** @return array{results: array<int, array<string, mixed>>, exit_codes: array<int, int>} */
$run = function (array $emails, string $slug) use (&$run): array {
    $barrier = tempnam(sys_get_temp_dir(), 'micatalogo-qa-registration-');
    if ($barrier === false) {
        throw new RuntimeException('Could not create the QA registration barrier.');
    }
    unlink($barrier);

    $workers = [];
    foreach ($emails as $index => $email) {
        $worker = $index + 1;
        $command = implode(' ', [
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__FILE__),
            'worker',
            escapeshellarg($barrier),
            escapeshellarg($email),
            escapeshellarg($slug),
            (string) $worker,
        ]);
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        if (! is_resource($process)) {
            throw new RuntimeException('Could not start a concurrent registration worker.');
        }
        $workers[] = [$process, $pipes];
    }
    touch($barrier);

    $results = [];
    $exitCodes = [];
    foreach ($workers as [$process, $pipes]) {
        $stdout = trim(stream_get_contents($pipes[1]));
        $stderr = trim(stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $exitCodes[] = $exitCode;
        $results[] = [
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr_present' => $stderr !== '',
        ];
    }
    @unlink($barrier);

    return ['results' => $results, 'exit_codes' => $exitCodes];
};

$token = Str::lower(Str::random(12));
$sameEmail = "qa-registration-{$token}@micatalogo.test";
$sameSlug = "qa-registro-{$token}";
$sameEmailRun = $run([$sameEmail, $sameEmail], $sameSlug);

$differentEmailA = "qa-registration-a-{$token}@micatalogo.test";
$differentEmailB = "qa-registration-b-{$token}@micatalogo.test";
$sameSlugRun = $run([$differentEmailA, $differentEmailB], $sameSlug.'-shared');

$createdEmails = [$sameEmail, $differentEmailA, $differentEmailB];
$createdUsers = User::withTrashed()->whereIn('email', $createdEmails)->get();
$createdShopIds = $createdUsers->flatMap(fn (User $user) => $user->shops()->withTrashed()->pluck('id'))->unique()->values();
$createdSlugs = Shop::withTrashed()->whereIn('id', $createdShopIds)->pluck('slug')->values()->all();
$sameEmailSuccesses = collect($sameEmailRun['results'])->filter(fn (array $result): bool => str_contains($result['stdout'], '"status":"success"'))->count();
$sameEmailRejections = collect($sameEmailRun['results'])->filter(fn (array $result): bool => str_contains($result['stdout'], '"status":"rejected"'))->count();
$sameSlugSuccesses = collect($sameSlugRun['results'])->filter(fn (array $result): bool => str_contains($result['stdout'], '"status":"success"'))->count();

DB::transaction(function () use ($createdShopIds, $createdUsers): void {
    foreach ($createdShopIds as $shopId) {
        Shop::withTrashed()->whereKey($shopId)->forceDelete();
    }
    foreach ($createdUsers as $user) {
        User::withTrashed()->whereKey($user->id)->forceDelete();
    }
});

$summary = [
    'database' => $database,
    'same_email' => [
        'successes' => $sameEmailSuccesses,
        'rejections' => $sameEmailRejections,
        'workers' => $sameEmailRun['results'],
    ],
    'same_slug_different_email' => [
        'successes' => $sameSlugSuccesses,
        'slugs_before_cleanup' => $createdSlugs,
        'workers' => $sameSlugRun['results'],
    ],
    'cleaned_user_ids' => $createdUsers->pluck('id')->values()->all(),
    'cleaned_shop_ids' => $createdShopIds->all(),
];
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;

if ($sameEmailSuccesses !== 1 || $sameEmailRejections !== 1 || $sameSlugSuccesses !== 2
    || count(array_unique($createdSlugs)) !== 3
    || ! in_array($sameSlug, $createdSlugs, true)
    || ! in_array($sameSlug.'-shared', $createdSlugs, true)) {
    exit(1);
}
