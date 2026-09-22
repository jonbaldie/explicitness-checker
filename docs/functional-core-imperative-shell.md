# Functional core, imperative shell

Split each feature into two parts:

- The **shell** is a thin layer that gathers inputs (database, clock, options, config) and performs outputs (mail, console, writes).
- The **core** is where the decisions happen. It receives every input as an argument and returns its result.

Aim the checker at the core. Exempt the shell by directory, so an implicit input that slips into the core still fails the build.

## Tips

- **Exempt by directory.** Both the CLI and PHPStan select files, not classes. Keep shells in their own directories, such as `app/Console` or `src/Command`, and keep core code out of them.
- **Exclude a path, not a bare name.** `--exclude=src/Command` matches `/src/Command/` anywhere in a file's path. `--exclude=Command` matches every `Command` directory, including core ones such as `src/Billing/Command`, which might hold command-bus messages. Repeat `--exclude` for each shell directory, such as `app/Http/Controllers` or `app/Jobs`.
- **Keep the shell thin.** A shell has three jobs: gather inputs, call the core, and act on the result. Move every branch and calculation into the core, where the checker can see it.
- **Pass the clock in.** Give the core a `DateTimeImmutable $now` parameter. `Carbon::now()` or `Clock::get()` inside the core is reported as a static call. Laravel's `now()` helper is not reported (see the README's Laravel "Blind spot"), so keep it in the shell.
- **Gate the core.** `--min-explicitness=100` together with the shell excludes makes CI fail on the first implicit function in the core.

## Laravel worked example

This command is the shell. It gathers the unpaid invoices, the time and the `--grace` option, calls the core, then sends mail:

```php
<?php
// app/Console/Commands/SendOverdueReminders.php

namespace App\Console\Commands;

use App\Billing\OverdueReminders;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendOverdueReminders extends Command
{
    protected $signature = 'invoices:remind {--grace=7}';

    public function handle(): int
    {
        $reminders = OverdueReminders::due(
            Invoice::query()->whereNull('paid_at')->get()->all(),
            now()->toDateTimeImmutable(),
            (int) $this->option('grace'),
        );

        foreach ($reminders as $reminder) {
            Mail::to($reminder->email)->send(new \App\Mail\Overdue($reminder));
        }

        $this->info(count($reminders) . ' reminders sent.');

        return self::SUCCESS;
    }
}
```

The core decides which invoices are overdue, using only its arguments:

```php
<?php
// app/Billing/OverdueReminders.php

namespace App\Billing;

use DateTimeImmutable;

class OverdueReminders
{
    /**
     * @param list<Invoice> $unpaid
     *
     * @return list<Reminder>
     */
    public static function due(array $unpaid, DateTimeImmutable $now, int $graceDays): array
    {
        $cutoff = $now->modify("-{$graceDays} days");

        $reminders = [];
        foreach ($unpaid as $invoice) {
            if ($invoice->due_at < $cutoff) {
                $reminders[] = new Reminder($invoice->customer_email, $invoice->number);
            }
        }

        return $reminders;
    }
}
```

Checking all of `app` reports the shell. That is where the input belongs:

```console
$ ./vendor/bin/explicitness-checker app
Analyzing...

Results:

+-----------------------------------------------+------+---------------------------------------------------+-----------------------------------------------------+------------------+----------+
| File                                          | Line | Function                                          | Implicit Inputs                                     | Implicit Outputs | Severity |
+-----------------------------------------------+------+---------------------------------------------------+-----------------------------------------------------+------------------+----------+
| app/Console/Commands/SendOverdueReminders.php | 14   | App\Console\Commands\SendOverdueReminders::handle | read from static method App\Models\Invoice::query() |                  | Serious  |
+-----------------------------------------------+------+---------------------------------------------------+-----------------------------------------------------+------------------+----------+
...
```

The run exits with code 2. Exclude the shell and gate the core:

```console
$ ./vendor/bin/explicitness-checker --exclude=app/Console --min-explicitness=100 app
No implicit inputs or outputs found.
Explicit function-likes: 1 of 1 (100.0%, minimum 100%)
```

This run exits with code 0. Now suppose the clock moves into the core, like this:

```php
public static function due(array $unpaid, int $graceDays): array
{
    $cutoff = Carbon::now()->toDateTimeImmutable()->modify("-{$graceDays} days");
```

The same exempting run now catches it and exits with code 2:

```console
$ ./vendor/bin/explicitness-checker --exclude=app/Console app
...
| app/Billing/OverdueReminders.php | 14   | App\Billing\OverdueReminders::due | read from static method Illuminate\Support\Carbon::now() |                  | Serious  |
```

## Symfony worked example

The command is the shell. It reads the repository and the clock, calls the core, then downgrades accounts and writes output:

```php
<?php
// src/Command/ExpireTrialsCommand.php

namespace App\Command;

use App\Billing\TrialExpiry;
use App\Repository\AccountRepository;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:expire-trials')]
class ExpireTrialsCommand extends Command
{
    public function __construct(private AccountRepository $accounts)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $expired = TrialExpiry::expired($this->accounts->findOnTrial(), Clock::get()->now());

        foreach ($expired as $account) {
            $this->accounts->downgrade($account);
        }

        $output->writeln(count($expired) . ' trials expired.');

        return Command::SUCCESS;
    }
}
```

```php
<?php
// src/Billing/TrialExpiry.php

namespace App\Billing;

use App\Entity\Account;
use DateTimeImmutable;

class TrialExpiry
{
    /**
     * @param list<Account> $onTrial
     *
     * @return list<Account>
     */
    public static function expired(array $onTrial, DateTimeImmutable $now): array
    {
        return array_values(array_filter(
            $onTrial,
            static fn (Account $account): bool => $account->trialEndsAt <= $now,
        ));
    }
}
```

With `--strict --props`, the checker reports both of the shell's inputs on one row, and the run exits with code 2:

```console
$ ./vendor/bin/explicitness-checker --strict --props src
...
| src/Command/ExpireTrialsCommand.php | 21   | App\Command\ExpireTrialsCommand::execute | read from object property $this->accounts; read from static method Symfony\Component\Clock\Clock::get() |                  | Serious  |
```

Exclude the shell. This run exits with code 0:

```console
$ ./vendor/bin/explicitness-checker --strict --props --exclude=src/Command src
No implicit inputs or outputs found.
```

## PHPStan

To exempt the shell in PHPStan, use `ignoreErrors`, scoping each entry by `identifier` and `path`. PHPStan's other rules still check the shell. For example, a typo such as `$output->writeLine(...)` is still reported as `method.notFound`. Using `excludePaths` instead would turn off every rule for those files.

```neon
parameters:
    explicitness:
        strict: true
        props: true
    ignoreErrors:
        -
            identifier: explicitness.staticCall
            path: src/Command/*
        -
            identifier: explicitness.objectProperty
            path: src/Command/*
```

- Write one entry per identifier the shell actually reports. PHPStan fails the run on an entry that matches nothing ("Ignored error pattern explicitness.standardOutput in path … was not matched in reported errors"), so remove an entry once the shell stops reporting that identifier.
- `identifier` is matched as an exact string. A regex such as `'#^explicitness\.#'` matches nothing.
- The CLI reports a finding on the line where its function is declared. PHPStan reports it on the line of the offending expression.
