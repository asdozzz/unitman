<?php

declare(strict_types=1);

namespace App\Utils\Cli\Command;

use App\Utils\Model\Locale\SupportedLocale;
use App\Utils\Service\ExceptionKeyScanner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Verifies that the exception dictionaries stay consistent with the code.
 *
 * Checks performed:
 *  1. every supported locale has exactly the canonical key set;
 *  2. every key thrown from the code exists in the canonical (en) dictionary;
 *  3. no translation is an empty string;
 *  4. informational: canonical keys that nothing in the code refers to.
 *
 * Checks 1-3 make the command fail, because they produce untranslated or
 * missing messages at runtime. Check 4 is a hint for cleanup only.
 */
#[AsCommand(name: 'app:translations:audit', description: 'Checks exception dictionaries against the code')]
final class TranslationsAuditCommand extends Command
{
    private const CANON_FILE = 'translations/messages.en.php';

    public function __construct(private ExceptionKeyScanner $scanner)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $canon = $this->scanner->loadDictionary(self::CANON_FILE);

        if ($canon === []) {
            $io->error(sprintf('Canonical dictionary %s is missing or empty.', self::CANON_FILE));

            return Command::FAILURE;
        }

        $hasErrors = false;
        $canonKeys = array_keys($canon);

        foreach (SupportedLocale::values() as $locale) {
            $file = sprintf('translations/messages.%s.php', $locale);
            $dictionary = $this->scanner->loadDictionary($file);

            if ($dictionary === [] && $locale !== SupportedLocale::canon()) {
                $io->error(sprintf('[%s] dictionary %s is missing or empty.', $locale, $file));
                $hasErrors = true;
                continue;
            }

            $missing = array_diff($canonKeys, array_keys($dictionary));
            $extra = array_diff(array_keys($dictionary), $canonKeys);
            $empty = array_keys(array_filter(
                $dictionary,
                static fn (string $message): bool => trim($message) === '',
            ));

            if ($missing !== []) {
                $io->error(sprintf('[%s] missing %d key(s): %s', $locale, count($missing), implode(', ', $missing)));
                $hasErrors = true;
            }

            if ($extra !== []) {
                $io->error(sprintf('[%s] unknown %d key(s): %s', $locale, count($extra), implode(', ', $extra)));
                $hasErrors = true;
            }

            if ($empty !== []) {
                $io->error(sprintf('[%s] empty translation(s): %s', $locale, implode(', ', $empty)));
                $hasErrors = true;
            }

            if ($missing === [] && $extra === [] && $empty === []) {
                $io->writeln(sprintf(' [OK] %s: %d keys', $locale, count($dictionary)), OutputInterface::VERBOSITY_VERBOSE);
            }
        }

        $thrown = $this->scanner->scanThrownKeys();
        $untranslated = array_values(array_diff($thrown, $canonKeys));

        if ($untranslated !== []) {
            $io->error(sprintf(
                '%d key(s) thrown from the code have no canonical translation: %s',
                count($untranslated),
                implode(', ', $untranslated),
            ));
            $hasErrors = true;
        }

        $referenced = $this->scanner->scanReferencedKeys();
        $unused = array_values(array_diff($canonKeys, $referenced, $thrown));

        if ($unused !== []) {
            $io->warning(sprintf(
                '%d canonical key(s) are not referenced in the code: %s',
                count($unused),
                implode(', ', $unused),
            ));
        }

        if ($hasErrors) {
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Translations are consistent: %d keys, %d locales, %d keys thrown from the code.',
            count($canon),
            count(SupportedLocale::values()),
            count($thrown),
        ));

        return Command::SUCCESS;
    }
}
